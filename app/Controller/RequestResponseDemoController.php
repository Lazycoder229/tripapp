<?php

declare(strict_types=1);

namespace App\Controller;

use Framework\Routing\Attribute\Route;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Database\ConnectionInterface;

/**
 * Demo controller showcasing the extended Request/Response API
 * (files, cookies, client IP, redirect, download, JSON negotiation).
 *
 * @package App\Controller
 */
#[Route('/demo')]
class RequestResponseDemoController
{
    /**
     * The Container auto-wires this via reflection when the controller is resolved —
     * ConnectionInterface is bound to MySQLConnection in Application::run().
     */
    public function __construct(
        private ConnectionInterface $db
    ) {
    }

    /**
     * Sanity-checks the database connection/binding.
     */
    #[Route('/db-test', 'GET')]
    public function dbTest(Request $request): array
    {
        return $this->db->query('SELECT 1 AS ok');
    }

    /**
     * Shows request introspection helpers: client IP, scheme/host, full URL,
     * and content-negotiation checks (isJson / wantsJson).
     */
    #[Route('/request-info', 'GET')]
    public function requestInfo(Request $request): Response
    {
        return Response::json([
            'client_ip'  => $request->getClientIp(),
            'scheme'     => $request->getScheme(),
            'host'       => $request->getHost(),
            'full_url'   => $request->fullUrl(),
            'is_json'    => $request->isJson(),
            'wants_json' => $request->wantsJson(),
            'headers'    => $request->headers(),
        ]);
    }

    /**
     * Reads a cookie ("visits") off the request, increments it, and writes it
     * back on the response. Demonstrates Request::cookie() and Response::withCookie().
     */
    #[Route('/visits', 'GET')]
    public function visits(Request $request): Response
    {
        $visits = (int) $request->cookie('visits', 0) + 1;

        return Response::json(['visits' => $visits])
            ->withCookie(
                name: 'visits',
                value: (string) $visits,
                expires: time() + 3600,
                httpOnly: true,
                sameSite: 'Lax'
            );
    }

    /**
     * Clears the "visits" cookie. Demonstrates Response::withoutCookie().
     */
    #[Route('/visits/reset', 'POST')]
    public function resetVisits(Request $request): Response
    {
        return Response::json(['status' => 'reset'])
            ->withoutCookie('visits');
    }

    /**
     * Accepts a single uploaded file field named "avatar" and reports what was received.
     * Demonstrates Request::file() / Request::files().
     *
     * Test with: curl -F "avatar=@/path/to/photo.jpg" http://localhost:3000/demo/upload
     */
    #[Route('/upload', 'POST')]
    public function upload(Request $request): Response
    {
        $file = $request->file('avatar');

        if ($file === null) {
            return Response::json(['error' => 'No file uploaded under field "avatar".'], 422);
        }

        // A multi-file field (e.g. avatar[]) normalizes to a list of entries instead of one.
        if (isset($file[0])) {
            return Response::json(['error' => 'Expected a single file, got multiple.'], 422);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'Upload failed', 'code' => $file['error']], 422);
        }

        return Response::json([
            'original_name' => $file['name'],
            'mime_type'     => $file['type'],
            'size_bytes'    => $file['size'],
            // In a real handler you'd move it out of the tmp dir with move_uploaded_file(),
            // not just report on it.
            'tmp_path'      => $file['tmp_name'],
        ]);
    }

    /**
     * Redirects the caller to the home page. Demonstrates Response::redirect().
     */
    #[Route('/go-home', 'GET')]
    public function goHome(Request $request): Response
    {
        return Response::redirect('/home');
    }

    /**
     * Streams a file back as a download. Demonstrates Response::download().
     *
     * Expects a readable file at storage/downloads/sample.txt relative to the project root;
     * swap the path for a real file before testing.
     */
    #[Route('/download-sample', 'GET')]
    public function downloadSample(Request $request): Response
    {
        $filePath = __DIR__ . '/../../storage/downloads/sample.txt';

        if (!is_file($filePath)) {
            return Response::json(['error' => 'Sample file not found. Place one at storage/downloads/sample.txt.'], 404);
        }

        return Response::download($filePath, 'trip-sample.txt');
    }
}