<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\Security\Csrf;
use Framework\Security\Encrypt;
use Framework\Security\Hash;
use Framework\Security\Jwt;
use Framework\Security\Validator;
use Framework\Session\SessionInterface;
use Framework\Exception\DecryptException;
use Framework\Exception\InvalidTokenException;

final class SecurityTest extends TestCase
{
    private function createMockSession(): SessionInterface
    {
        return new class implements SessionInterface {
            private array $data = [];
            public function start(): void {}
            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
            public function has(string $key): bool { return isset($this->data[$key]); }
            public function remove(string $key): void { unset($this->data[$key]); }
            public function all(): array { return $this->data; }
            public function flash(string $key, mixed $value): void {}
            public function getFlash(string $key, mixed $default = null): mixed { return $default; }
            public function regenerate(): void {}
            public function destroy(): void { $this->data = []; }
        };
    }

    public function testCsrfTokenGenerationAndVerification(): void
    {
        $session = $this->createMockSession();
        $csrf = new Csrf($session);

        $token = $csrf->token();
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $this->assertTrue($csrf->verify($token));
        $this->assertFalse($csrf->verify('invalid-token'));
        $this->assertFalse($csrf->verify(''));
        $this->assertFalse($csrf->verify(null));
    }

    public function testAuthenticatedEncryptionAes256Gcm(): void
    {
        $key = random_bytes(32);
        $encryptor = new Encrypt($key);

        $plain = 'Sensitive Payload Data: 12345';
        $encrypted = $encryptor->encrypt($plain);

        $this->assertNotSame($plain, $encrypted);
        $this->assertSame($plain, $encryptor->decrypt($encrypted));
    }

    public function testTamperedCiphertextThrowsDecryptException(): void
    {
        $key = random_bytes(32);
        $encryptor = new Encrypt($key);

        $encrypted = $encryptor->encrypt('Confidential Info');
        $raw = base64_decode($encrypted);
        $tampered = base64_encode(substr($raw, 0, -2) . 'FF');

        $this->expectException(DecryptException::class);
        $encryptor->decrypt($tampered);
    }

    public function testPasswordHashingBcrypt(): void
    {
        $password = 'MyStrongPassword123!';
        $hash = Hash::make($password);

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(Hash::verify($password, $hash));
        $this->assertFalse(Hash::verify('WrongPassword', $hash));
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function testJwtSigningAndDecoding(): void
    {
        $jwt = new Jwt('test-secret-key-that-is-long-enough-32', defaultTtl: 3600);
        $token = $jwt->encode(['sub' => 101, 'role' => 'admin']);

        $decoded = $jwt->decode($token);
        $this->assertSame(101, $decoded['sub']);
        $this->assertSame('admin', $decoded['role']);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testJwtTamperedSignatureThrowsException(): void
    {
        $jwt = new Jwt('test-secret-key-that-is-long-enough-32', defaultTtl: 3600);
        $token = $jwt->encode(['sub' => 101]);

        $parts = explode('.', $token);
        $tamperedPayload = rtrim(strtr(base64_encode(json_encode(['sub' => 999])), '+/', '-_'), '=');
        $tamperedToken = $parts[0] . '.' . $tamperedPayload . '.' . $parts[2];

        $this->expectException(InvalidTokenException::class);
        $jwt->decode($tamperedToken);
    }

    public function testValidatorRules(): void
    {
        $validator = Validator::make([
            'email' => 'test@example.com',
            'age'   => '25',
            'ip'    => '127.0.0.1',
        ], [
            'email' => 'required|email',
            'age'   => 'required|integer|min:18',
            'ip'    => 'required|ipv4',
        ]);

        $this->assertTrue($validator->passes());
        $this->assertFalse($validator->fails());

        $badValidator = Validator::make([
            'email' => 'invalid-email',
            'age'   => '15',
        ], [
            'email' => 'required|email',
            'age'   => 'required|integer|min:18',
        ]);

        $this->assertTrue($badValidator->fails());
        $this->assertArrayHasKey('email', $badValidator->errors());
        $this->assertArrayHasKey('age', $badValidator->errors());
    }
}
