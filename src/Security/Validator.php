<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Database\ConnectionInterface;
use Framework\Exception\ValidationException;

/**
 * Rule-based input validator.
 * Rules are declared per-field as a pipe-separated string, e.g.
 * 'required|email|max:255'. Field names support dot notation for nested
 * data and '*' wildcards for arrays of objects:
 *
 * ```php
 * $validator = Validator::make($request->all(), [
 *     'email'            => 'required|email',
 *     'password'         => 'required|min:8|confirmed',
 *     'address.city'     => 'required|string',
 *     'items'            => 'required|array|min:1',
 *     'items.*.sku'      => 'required|string',
 *     'items.*.quantity' => 'required|integer|gt:0',
 * ]);
 *
 * $data = $validator->validate(); // nested structure, only declared paths
 * ```
 *
 * validated() rebuilds the same nested shape as the input for every declared
 * path (wildcards expanded against the actual data) — unlisted keys are
 * dropped, same guarantee as before, now depth-aware.
 *
 * Custom rules, registered once (e.g. in a service provider):
 * ```php
 * Validator::extend('slug', function (mixed $value, array $params, array $data): bool {
 *     return is_string($value) && preg_match('/^[a-z0-9-]+$/', $value) === 1;
 * }, 'The :field must be a valid slug.');
 * ```
 *
 * @package Framework\Security
 */
final class Validator
{
    /** @var array Original input, keyed by field name (possibly nested). */
    private array $data;

    /** @var array<string, string[]> Field pattern (dot/wildcard) => parsed rule list. */
    private array $rules;

    /** @var array<string, string> Optional field-specific override messages, keyed 'field.rule'. */
    private array $customMessages;

    /** @var array<string, string> Optional human-readable field names for messages, keyed by field. */
    private array $attributes;

    /** @var array<string, array<int, string>>|null */
    private ?array $errors = null;

    private const string PATTERN_ALPHA     = '/^[a-zA-Z]+$/';
    private const string PATTERN_ALPHA_NUM = '/^[a-zA-Z0-9]+$/';
    private const string PATTERN_UUID      = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** Rules resolved before the generic per-rule loop — never dispatched through applyRule(). */
    private const array PRESENCE_RULES = [
        'required', 'nullable', 'bail',
        'required_if', 'required_unless', 'required_with', 'required_without',
    ];

    /** Sentinel distinguishing "rule passed" (null) from "rule name not recognized" (also null). */
    private const string UNKNOWN = "\0unknown-rule\0";

    /** @var array<string, array{callback: callable(mixed, string[], array): bool, message: string}> */
    private static array $customRules = [];

    /** @var callable(string, string): void|null */
    private static $unknownRuleHandler = null;

    /**
     * @param array $data
     * @param array<string, string> $rules
     * @param array<string, string> $customMessages
     * @param ConnectionInterface|null $db Required only if a field uses 'unique' or 'exists'.
     * @param array<string, string> $attributes Optional display-name overrides for :field in messages.
     */
    public function __construct(
        array $data,
        array $rules,
        array $customMessages = [],
        private readonly ?ConnectionInterface $db = null,
        array $attributes = [],
    ) {
        $this->data = $data;
        $this->customMessages = $customMessages;
        $this->attributes = $attributes;
        $this->rules = array_map(self::parseRuleString(...), $rules);
    }

    /**
     * Creates a new Validator instance.
     *
     * @param array $data
     * @param array<string, string> $rules
     * @param array<string, string> $customMessages
     * @param ConnectionInterface|null $db Required only if a field uses 'unique' or 'exists'.
     * @param array<string, string> $attributes Optional display-name overrides for :field in messages.
     */
    public static function make(
        array $data,
        array $rules,
        array $customMessages = [],
        ?ConnectionInterface $db = null,
        array $attributes = [],
    ): self {
        return new self($data, $rules, $customMessages, $db, $attributes);
    }

    /**
     * Registers a custom rule usable by name in any rule string. Stored
     * statically — register once at bootstrap, not per-request.
     *
     * @param callable(mixed $value, string[] $params, array $data): bool $callback
     * @param string $message Default message; ':field' is replaced with the display name.
     */
    public static function extend(string $name, callable $callback, string $message): void
    {
        self::$customRules[$name] = ['callback' => $callback, 'message' => $message];
    }

    /**
     * Wire a handler for unknown rule names (typos) instead of silently
     * skipping them. The rule still doesn't block the request either way —
     * this just gives you visibility.
     *
     * @param callable(string $field, string $rule): void $handler
     */
    public static function onUnknownRule(callable $handler): void
    {
        self::$unknownRuleHandler = $handler;
    }

    /**
     * Returns true if the validation fails.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return $this->run() !== [];
    }

    /**
     * Returns true if the validation passes.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Returns the validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->run();
    }

    /**
     * Rebuilds the nested shape of $data, restricted to declared paths.
     * Wildcard patterns are expanded against the actual data first.
     *
     * @return array
     */
    public function validated(): array
    {
        $result = [];

        foreach ($this->rules as $pattern => $ruleList) {
            foreach ($this->expandPattern($pattern) as $path) {
                if ($this->hasValue($this->data, $path)) {
                    $this->setNested($result, $path, $this->getValue($this->data, $path));
                }
            }
        }

        return $result;
    }

    /**
     * Validates the input and returns the validated data, or throws a ValidationException.
     *
     * @return array
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors());
        }

        return $this->validated();
    }

    /** 
     * Parses a rule string into an array of individual rules.
     *
     * @param string $ruleString
     * @return string[]
     */
    private static function parseRuleString(string $ruleString): array
    {
        return array_filter(explode('|', $ruleString), strlen(...));
    }

    /**
     * Runs the validation rules on the data.
     *
     * @return array<string, array<int, string>>
     */
    private function run(): array
    {
        if ($this->errors !== null) {
            return $this->errors;
        }

        $errors = [];

        foreach ($this->rules as $pattern => $ruleList) {
            foreach ($this->expandPattern($pattern) as $path) {
                $fieldErrors = $this->runField($path, $ruleList);

                if ($fieldErrors !== []) {
                    $errors[$path] = $fieldErrors;
                }
            }
        }

        return $this->errors = $errors;
    }

    /**
     * A plain pattern (no '*') expands to itself. A wildcard pattern like
     * 'items.*.sku' expands to every concrete path found in $data —
     * 'items.0.sku', 'items.1.sku', etc. Nothing found under the array
     * position means that concrete path simply isn't generated (an empty
     * 'items' array validates fine unless 'items' itself has 'required').
     *
     * @return string[]
     */
    private function expandPattern(string $pattern): array
    {
        if (!str_contains($pattern, '*')) {
            return [$pattern];
        }

        return $this->expandSegments(explode('.', $pattern), [], $this->data);
    }

    /**
     * Expands a pattern with wildcards into concrete paths.
     *
     * @param string[] $segments
     * @param string[] $prefix
     * @param mixed $node
     * @return string[]
     */
    private function expandSegments(array $segments, array $prefix, mixed $node): array
    {
        if ($segments === []) {
            return [implode('.', $prefix)];
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (!is_array($node)) {
                return [];
            }

            $results = [];
            foreach ($node as $key => $child) {
                $results = [...$results, ...$this->expandSegments($segments, [...$prefix, (string) $key], $child)];
            }
            return $results;
        }

        $child = is_array($node) && array_key_exists($segment, $node) ? $node[$segment] : null;
        return $this->expandSegments($segments, [...$prefix, $segment], $child);
    }

    private function getValue(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private function hasValue(array $data, string $path): bool
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }
        return true;
    }

    private function setNested(array &$result, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        $ref = &$result;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref[$last] = $value;
    }

    /**
     * Runs the validation rules on a single field.
     *
     * @return string[]
     */
    private function runField(string $field, array $ruleList): array
    {
        $value      = $this->getValue($this->data, $field);
        $isMissing  = !$this->hasValue($this->data, $field) || $value === null || $value === '';
        $isRequired = $this->resolveRequired($ruleList);
        $isNullable = in_array('nullable', $ruleList, true);
        $bail       = in_array('bail', $ruleList, true);

        if ($isMissing) {
            return $isRequired
                ? [$this->message($field, 'required', 'The :field field is required.')]
                : [];
        }

        if ($isNullable && $value === null) {
            return [];
        }

        $errors = [];

        foreach ($ruleList as $rule) {
            $ruleName = explode(':', $rule, 2)[0];

            if (in_array($ruleName, self::PRESENCE_RULES, true)) {
                continue;
            }

            [$name, $params] = self::parseRule($rule);
            $error = $this->applyRule($field, $value, $name, $params);

            if ($error !== null) {
                $errors[] = $error;
                if ($bail) {
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Resolves whether a field counts as required, including the
     * conditional variants — any one of them triggering makes it required.
     *
     * @param string[] $ruleList
     */
    private function resolveRequired(array $ruleList): bool
    {
        foreach ($ruleList as $rule) {
            [$name, $params] = self::parseRule($rule);

            $triggered = match ($name) {
                'required' => true,
                'required_if' => isset($params[0], $params[1])
                    && (string) $this->getValue($this->data, $params[0]) === $params[1],
                'required_unless' => isset($params[0], $params[1])
                    && (string) $this->getValue($this->data, $params[0]) !== $params[1],
                'required_with' => (bool) array_filter(
                    $params,
                    fn(string $f) => $this->hasValue($this->data, $f) && $this->getValue($this->data, $f) !== null
                ),
                'required_without' => (bool) array_filter(
                    $params,
                    fn(string $f) => !$this->hasValue($this->data, $f) || $this->getValue($this->data, $f) === null
                ),
                default => false,
            };

            if ($triggered) {
                return true;
            }
        }

        return false;
    }

    /**
     * Splits 'min:8' into ['min', ['8']]. Rules that take one opaque param
     * (regex, same, different, before, after, gt/lt/gte/lte) keep everything
     * after the first colon intact rather than comma-splitting it, so e.g.
     * 'regex:/^[a,b]+$/' parses correctly.
     *
     * @return array{0: string, 1: string[]}
     */
    private static function parseRule(string $rule): array
    {
        [$name, $paramString] = array_pad(explode(':', $rule, 2), 2, null);

        if ($paramString === null) {
            return [$name, []];
        }

        $singleParamRules = ['regex', 'same', 'different', 'before', 'after', 'gt', 'lt', 'gte', 'lte'];

        if (in_array($name, $singleParamRules, true)) {
            return [$name, [$paramString]];
        }

        return [$name, explode(',', $paramString)];
    }

    /**
     * Runs a single rule on a field value, returning an error message if it fails.
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param string[] $params
     * @return string|null Error message if the rule fails, or null if it passes.
     */
    private function applyRule(string $field, mixed $value, string $rule, array $params): ?string
    {
        if (isset(self::$customRules[$rule])) {
            return self::$customRules[$rule]['callback']($value, $params, $this->data)
                ? null
                : $this->message($field, $rule, self::$customRules[$rule]['message']);
        }

        $result = match ($rule) {
            'email'          => $this->checkEmail($field, $value),
            'numeric'        => $this->checkNumeric($field, $value),
            'integer'        => $this->checkInteger($field, $value),
            'string'         => $this->checkString($field, $value),
            'boolean'        => $this->checkBoolean($field, $value),
            'array'          => $this->checkArray($field, $value),
            'alpha'          => $this->checkPattern($field, $value, self::PATTERN_ALPHA, 'alpha', 'only contain letters'),
            'alpha_num'      => $this->checkPattern($field, $value, self::PATTERN_ALPHA_NUM, 'alpha_num', 'only contain letters and numbers'),
            'url'            => $this->checkUrl($field, $value),
            'date'           => $this->checkDate($field, $value),
            'date_format'    => $this->checkDateFormat($field, $value, $params),
            'before'         => $this->checkDateCompare($field, $value, $params, true),
            'after'          => $this->checkDateCompare($field, $value, $params, false),
            'uuid'           => $this->checkUuid($field, $value),
            'json'           => $this->checkJson($field, $value),
            'ip'             => $this->checkIp($field, $value, null),
            'ipv4'           => $this->checkIp($field, $value, FILTER_FLAG_IPV4),
            'ipv6'           => $this->checkIp($field, $value, FILTER_FLAG_IPV6),
            'distinct'       => $this->checkDistinct($field, $value),
            'min'            => $this->checkSize($field, $value, $params, 'min', fn($a, $b) => $a >= $b, 'be at least :param'),
            'max'            => $this->checkSize($field, $value, $params, 'max', fn($a, $b) => $a <= $b, 'not be greater than :param'),
            'size'           => $this->checkSize($field, $value, $params, 'size', fn($a, $b) => $a === $b, 'be exactly :param'),
            'between'        => $this->checkBetween($field, $value, $params),
            'digits'         => $this->checkDigits($field, $value, $params),
            'digits_between' => $this->checkDigitsBetween($field, $value, $params),
            'in'             => $this->checkIn($field, $value, $params),
            'regex'          => $this->checkRegex($field, $value, $params),
            'same'           => $this->checkSame($field, $value, $params),
            'different'      => $this->checkDifferent($field, $value, $params),
            'gt'             => $this->compareField($field, $value, $params, 'gt', fn($a, $b) => $a > $b, 'greater than'),
            'lt'             => $this->compareField($field, $value, $params, 'lt', fn($a, $b) => $a < $b, 'less than'),
            'gte'            => $this->compareField($field, $value, $params, 'gte', fn($a, $b) => $a >= $b, 'greater than or equal to'),
            'lte'            => $this->compareField($field, $value, $params, 'lte', fn($a, $b) => $a <= $b, 'less than or equal to'),
            'confirmed'      => $this->checkConfirmed($field, $value),
            'unique'         => $this->checkUnique($field, $value, $params),
            'exists'         => $this->checkExists($field, $value, $params),
            default          => self::UNKNOWN,
        };

        if ($result === self::UNKNOWN) {
            if (self::$unknownRuleHandler !== null) {
                (self::$unknownRuleHandler)($field, $rule);
            }
            return null;
        }

        return $result;
    }

    /**
     * Returns a formatted error message for an email field.
     *
     * @param string $field
     * @param string $rule
     * @param string $defaultMessage
     * @param array<string, string> $replacements
     * @return string
     */
    private function checkEmail(string $field, mixed $value): ?string
    {
        return filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false
            ? $this->message($field, 'email', 'The :field must be a valid email address.')
            : null;
    }

    /**
     * Returns a formatted error message for a numeric field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkNumeric(string $field, mixed $value): ?string
    {
        return is_numeric($value)
            ? null
            : $this->message($field, 'numeric', 'The :field must be a number.');
    }

    /**
     * Returns a formatted error message for an integer field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkInteger(string $field, mixed $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? null
            : $this->message($field, 'integer', 'The :field must be an integer.');
    }

    /**
     * Returns a formatted error message for a string field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkString(string $field, mixed $value): ?string
    {
        return is_string($value)
            ? null
            : $this->message($field, 'string', 'The :field must be a string.');
    }

    /**
     * Returns a formatted error message for a boolean field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkBoolean(string $field, mixed $value): ?string
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true)
            ? null
            : $this->message($field, 'boolean', 'The :field must be true or false.');
    }

    /**
     * Returns a formatted error message for an array field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkArray(string $field, mixed $value): ?string
    {
        return is_array($value)
            ? null
            : $this->message($field, 'array', 'The :field must be an array.');
    }

    /**
     * Returns a formatted error message for a field that must match a regex pattern.
     *
     * @param string $field
     * @param mixed $value
     * @param array<string> $params
     * @return string|null
     */
    private function checkPattern(string $field, mixed $value, string $pattern, string $ruleName, string $description): ?string
    {
        return is_string($value) && preg_match($pattern, $value) === 1
            ? null
            : $this->message($field, $ruleName, "The :field must {$description}.");
    }

    /**
     * Returns a formatted error message for a URL field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkUrl(string $field, mixed $value): ?string
    {
        return filter_var((string) $value, FILTER_VALIDATE_URL) === false
            ? $this->message($field, 'url', 'The :field must be a valid URL.')
            : null;
    }

    /**
     * Returns a formatted error message for a date field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkDate(string $field, mixed $value): ?string
    {
        if (!is_string($value)) {
            return $this->message($field, 'date', 'The :field must be a valid date.');
        }

        $parsed = date_parse($value);
        return ($parsed['error_count'] ?? 1) === 0 && ($parsed['warning_count'] ?? 1) === 0
            ? null
            : $this->message($field, 'date', 'The :field must be a valid date.');
    }


    /**
     * Returns a formatted error message for a date field with a specific format.
     *
     * @param string $field
     * @param mixed $value
     * @param array<string> $params
     * @return string|null
     */
    private function checkDateFormat(string $field, mixed $value, array $params): ?string
    {
        $format = $params[0] ?? null;

        if ($format === null || !is_string($value)) {
            return $this->message($field, 'date_format', 'The :field must be a valid date.');
        }

        $parsed = \DateTime::createFromFormat($format, $value);

        return $parsed !== false && $parsed->format($format) === $value
            ? null
            : $this->message($field, 'date_format', "The :field must match the format {$format}.");
    }

    /**
     * before:date_or_field / after:date_or_field — the param is resolved
     * against another declared field first; if that field doesn't exist in
     * the input, it's treated as a literal date string (matches Laravel).
     */
    private function checkDateCompare(string $field, mixed $value, array $params, bool $before): ?string
    {
        $other = $params[0] ?? null;

        if ($other === null || !is_string($value)) {
            return null;
        }

        $otherRaw = $this->hasValue($this->data, $other) ? (string) $this->getValue($this->data, $other) : $other;

        $valueTs = strtotime($value);
        $otherTs = strtotime($otherRaw);

        if ($valueTs === false || $otherTs === false) {
            return $this->message($field, $before ? 'before' : 'after', 'The :field must be a valid date.');
        }

        $passes = $before ? $valueTs < $otherTs : $valueTs > $otherTs;
        $word   = $before ? 'before' : 'after';

        return $passes
            ? null
            : $this->message($field, $word, "The :field must be a date {$word} :other.", [':other' => $this->displayName($other)]);
    }

    /**
     * Returns a formatted error message for a UUID field.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkUuid(string $field, mixed $value): ?string
    {
        return is_string($value) && preg_match(self::PATTERN_UUID, $value) === 1
            ? null
            : $this->message($field, 'uuid', 'The :field must be a valid UUID.');
    }

    /**
     * Returns a formatted error message for a JSON.
     *
     * @param string $field
     * @param mixed $value
     * @return string|null
     */
    private function checkJson(string $field, mixed $value): ?string
    {
        if (!is_string($value)) {
            return $this->message($field, 'json', 'The :field must be a valid JSON string.');
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE
            ? null
            : $this->message($field, 'json', 'The :field must be a valid JSON string.');
    }

    /**
     * Returns a formatted error message for an IP address.
     *
     * @param string $field
     * @param mixed $value
     * @param int|null $flag Optional filter_var flag for IPv4/IPv6.
     * @return string|null
     */
    private function checkIp(string $field, mixed $value, ?int $flag): ?string
    {
        $options = $flag !== null ? ['flags' => $flag] : [];

        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, $options) !== false
            ? null
            : $this->message($field, 'ip', 'The :field must be a valid IP address.');
    }

    /** Applied directly to an array field — no duplicate values among its elements. */
    private function checkDistinct(string $field, mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        $stringified = array_map(static fn($v) => is_scalar($v) ? (string) $v : serialize($v), $value);

        return count($stringified) === count(array_unique($stringified))
            ? null
            : $this->message($field, 'distinct', 'The :field field has a duplicate value.');
    }

    /** Shared sizing for min/max/size/between — string length, numeric value, or array count. */
    private function sizeOf(mixed $value): float
    {
        return match (true) {
            is_array($value)                          => (float) count($value),
            is_numeric($value) && !is_string($value)  => (float) $value,
            is_string($value) && is_numeric($value)   => (float) $value,
            default                                    => (float) mb_strlen((string) $value),
        };
    }

    /** Shared sizing for min/max/size/between — string length, numeric value, or array count. */
    private function checkSize(string $field, mixed $value, array $params, string $ruleName, callable $compare, string $descriptor): ?string
    {
        $bound = (float) ($params[0] ?? 0);
        $descriptor = str_replace(':param', (string) ($params[0] ?? ''), $descriptor);

        return $compare($this->sizeOf($value), $bound)
            ? null
            : $this->message($field, $ruleName, "The :field must {$descriptor}.");
    }

    /** between:min,max — string length, numeric value, or array count must be within the range. */
    private function checkBetween(string $field, mixed $value, array $params): ?string
    {
        $min = (float) ($params[0] ?? 0);
        $max = (float) ($params[1] ?? 0);
        $actual = $this->sizeOf($value);

        return $actual >= $min && $actual <= $max
            ? null
            : $this->message($field, 'between', "The :field must be between {$params[0]} and {$params[1]}.");
    }

    /** digits:length — string must be exactly length digits. */
    private function checkDigits(string $field, mixed $value, array $params): ?string
    {
        $length = $params[0] ?? '0';
        $str = (string) $value;

        return ctype_digit($str) && strlen($str) === (int) $length
            ? null
            : $this->message($field, 'digits', "The :field must be {$length} digits.");
    }

    /** digits_between:min,max — string must be between min and max digits. */
    private function checkDigitsBetween(string $field, mixed $value, array $params): ?string
    {
        [$min, $max] = array_pad($params, 2, '0');
        $str = (string) $value;
        $len = strlen($str);

        return ctype_digit($str) && $len >= (int) $min && $len <= (int) $max
            ? null
            : $this->message($field, 'digits_between', "The :field must be between {$min} and {$max} digits.");
    }

    /** in:val1,val2,... — the value must be one of the listed values. */
    private function checkIn(string $field, mixed $value, array $params): ?string
    {
        return in_array((string) $value, $params, true)
            ? null
            : $this->message($field, 'in', 'The selected :field is invalid.');
    }

    /** regex:pattern — the value must match the given regex pattern. */
    private function checkRegex(string $field, mixed $value, array $params): ?string
    {
        $pattern = $params[0] ?? null;

        return $pattern !== null && is_string($value) && preg_match($pattern, $value) === 1
            ? null
            : $this->message($field, 'regex', 'The :field format is invalid.');
    }

    /** same:other_field — the value must match another field's value. */
    private function checkSame(string $field, mixed $value, array $params): ?string
    {
        $other = $params[0] ?? null;

        return $other !== null && $this->getValue($this->data, $other) === $value
            ? null
            : $this->message($field, 'same', 'The :field must match :other.', [':other' => $this->displayName((string) $other)]);
    }

    /** different:other_field — the value must be different from another field's value. */
    private function checkDifferent(string $field, mixed $value, array $params): ?string
    {
        $other = $params[0] ?? null;

        return $other !== null && $this->getValue($this->data, $other) !== $value
            ? null
            : $this->message($field, 'different', 'The :field must be different from :other.', [':other' => $this->displayName((string) $other)]);
    }

    /** gt/lt/gte/lte:field_or_number — compares sizeOf(value) against another field's value, or a literal number. */
    private function compareField(string $field, mixed $value, array $params, string $ruleName, callable $compare, string $descriptor): ?string
    {
        $other = $params[0] ?? null;

        if ($other === null) {
            return null;
        }

        $otherValue = $this->hasValue($this->data, $other)
            ? $this->getValue($this->data, $other)
            : (is_numeric($other) ? (float) $other : null);

        if ($otherValue === null) {
            return null;
        }

        $label = $this->hasValue($this->data, $other) ? $this->displayName($other) : (string) $other;

        return $compare($this->sizeOf($value), $this->sizeOf($otherValue))
            ? null
            : $this->message($field, $ruleName, "The :field must be {$descriptor} :other.", [':other' => $label]);
    }

    private function checkConfirmed(string $field, mixed $value): ?string
    {
        return $this->getValue($this->data, "{$field}_confirmation") === $value
            ? null
            : $this->message($field, 'confirmed', 'The :field confirmation does not match.');
    }

    /**
     * unique:table,column,exceptValue,exceptColumn — exceptColumn defaults
     * to 'id'. Use on update forms so the record being edited doesn't fail
     * uniqueness against itself: "unique:users,email,{$user->id}".
     */
    private function checkUnique(string $field, mixed $value, array $params): ?string
    {
        [$table, $column, $exceptValue, $exceptColumn] = array_pad($params, 4, null);
        $column ??= $field;
        $exceptColumn ??= 'id';

        $this->assertDb($field, 'unique');
        $this->assertIdentifiers($field, 'unique', [$table, $column, $exceptColumn]);

        $sql = "SELECT 1 FROM {$table} WHERE {$column} = ?";
        $bindings = [$value];

        if ($exceptValue !== null) {
            $sql .= " AND {$exceptColumn} != ?";
            $bindings[] = $exceptValue;
        }

        $sql .= ' LIMIT 1';

        return $this->db->queryOne($sql, $bindings) === null
            ? null
            : $this->message($field, 'unique', 'The :field has already been taken.');
    }

    /** exists:table,column — the value must already be present, e.g. for foreign-key inputs. */
    private function checkExists(string $field, mixed $value, array $params): ?string
    {
        [$table, $column] = array_pad($params, 2, null);
        $column ??= $field;

        $this->assertDb($field, 'exists');
        $this->assertIdentifiers($field, 'exists', [$table, $column]);

        $existing = $this->db->queryOne("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1", [$value]);

        return $existing !== null
            ? null
            : $this->message($field, 'exists', 'The selected :field is invalid.');
    }

    /**
     * Asserts that a database connection is available.
     *
     * @param string $field
     * @param string $rule
     * @return void
     */
    private function assertDb(string $field, string $rule): void
    {
        if ($this->db === null) {
            throw new \Framework\Exception\FrameworkException(
                "500 The '{$rule}' rule on '{$field}' requires a ConnectionInterface — " .
                "pass one as Validator::make()'s 4th argument."
            );
        }
    }

    /**
     * Asserts that the provided identifiers are valid.
     *
     * @param array<int, string|null> $identifiers
     * @param string $field
     * @param string $rule
     * @return void
     */
    private function assertIdentifiers(string $field, string $rule, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $identifier)) {
                throw new \Framework\Exception\FrameworkException(
                    "500 Invalid table/column name in '{$rule}' rule on '{$field}'."
                );
            }
        }
    }

    /**
     * Returns the display name for a field.
     *
     * @param string $field
     * @return string
     */
    private function displayName(string $field): string
    {
        return $this->attributes[$field] ?? str_replace('_', ' ', $field);
    }

    /** 
     * Returns a formatted error message for a field.
     *
     * @param string $field
     * @param string $rule
     * @param string $default
     * @param array<string, string> $replacements Extra :token => value substitutions beyond :field.
     * @return string
     */
    private function message(string $field, string $rule, string $default, array $replacements = []): string
    {
        $template = $this->customMessages["{$field}.{$rule}"] ?? $default;
        $replacements[':field'] = $this->displayName($field);

        return strtr($template, $replacements);
    }
}