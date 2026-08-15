<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionInterface;
use Framework\Exception\ValidationException;

/**
 * Rule-based input validator.
 *
 * Rules are declared per-field as a pipe-separated string, e.g.
 * 'required|email|max:255' — same shape as the fillable/guarded whitelist
 * idiom used elsewhere in the framework: you declare what's allowed, and
 * anything not declared is dropped rather than silently passed through.
 *
 * validated() is safe by default: it returns ONLY the fields that have a
 * rule declared for them, even if the raw input array had extra keys. A
 * controller that does:
 * ```php
 * $data = Validator::make($request->all(), ['title' => 'required|max:255'])
 *     ->validate();
 * $this->trip->create($data);
 * ```
 * can never accidentally pass an unexpected field into create()/update() —
 * mirrors how Model::filterFillable() already protects mass-assignment,
 * just one layer earlier, before the data even reaches the Model.
 *
 * Usage:
 * ```php
 * $validator = Validator::make($request->all(), [
 *     'email'    => 'required|email',
 *     'password' => 'required|min:8|confirmed',
 *     'age'      => 'nullable|numeric|min:18',
 * ]);
 *
 * if ($validator->fails()) {
 *     return Response::json(['errors' => $validator->errors()], 422);
 * }
 *
 * $data = $validator->validated(); // only email/password/age — nothing else
 * ```
 *
 * Or, to throw instead of branching on fails() — Handler.php already knows
 * how to turn any FrameworkException into the right HTTP response:
 * ```php
 * $data = Validator::make($request->all(), [...])->validate();
 * ```
 *
 * @package Framework\Validation
 */
final class Validator
{
    /** @var array Original input, keyed by field name. */
    private array $data;

    /** @var array<string, string[]> Field name => parsed list of rule strings. */
    private array $rules;

    /** @var array<string, string> Optional field-specific override messages, e.g. ['email.required' => '...']. */
    private array $customMessages;

    /** @var array<string, array<int, string>>|null Populated on first fails()/errors()/validate() call; null means "not yet run". */
    private ?array $errors = null;

    /** @var string[] Rules that stop the field from running further checks when the value is null/absent and the rule list contains 'nullable' (and doesn't also contain 'required'). */
    private const string PATTERN_ALPHA     = '/^[a-zA-Z]+$/';
    private const string PATTERN_ALPHA_NUM = '/^[a-zA-Z0-9]+$/';

    /**
     * @param array $data                 Raw input to validate — e.g. $request->all().
     * @param array<string, string> $rules Field name => pipe-separated rule string.
     * @param array<string, string> $customMessages Optional overrides, keyed 'field.rule',
     *                              e.g. ['email.required' => 'We need your email address.'].
     * @param ConnectionInterface|null $db Only required if a field uses the 'unique' rule.
     */
    public function __construct(
        array $data,
        array $rules,
        array $customMessages = [],
        private readonly ?ConnectionInterface $db = null,
    ) {
        $this->data = $data;
        $this->customMessages = $customMessages;

        $this->rules = array_map(
            static fn(string $ruleString): array => array_filter(explode('|', $ruleString), strlen(...)),
            $rules
        );
    }

    /**
     * @param array $data
     * @param array<string, string> $rules
     * @param array<string, string> $customMessages
     * @param ConnectionInterface|null $db
     * @return self
     */
    public static function make(
        array $data,
        array $rules,
        array $customMessages = [],
        ?ConnectionInterface $db = null,
    ): self {
        return new self($data, $rules, $customMessages, $db);
    }

    /**
     * Runs validation (if not already run) and returns whether anything failed.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return $this->run() !== [];
    }

    /**
     * Inverse of fails() — convenience for readable if-conditions.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Runs validation (if not already run) and returns the full error map.
     * Empty array means everything passed.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->run();
    }

    /**
     * Returns ONLY the input fields that have a rule declared for them —
     * unlisted keys in the original $data are silently dropped. Does not
     * check pass/fail; call fails() or validate() first if you need that.
     *
     * @return array
     */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    /**
     * Runs validation and returns validated() if it passes, or throws
     * ValidationException (422, carrying the full error map) if it doesn't.
     * The one-call convenience path — no separate fails()/errors() dance
     * needed when you just want "give me clean data or blow up".
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
     * Executes every field's rule list exactly once and caches the result —
     * fails()/errors()/validate() all funnel through here so re-checking
     * doesn't re-run rules (relevant for 'unique', which hits the DB).
     *
     * @return array<string, array<int, string>>
     */
    private function run(): array
    {
        if ($this->errors !== null) {
            return $this->errors;
        }

        $errors = [];

        foreach ($this->rules as $field => $ruleList) {
            $fieldErrors = $this->runField($field, $ruleList);

            if ($fieldErrors !== []) {
                $errors[$field] = $fieldErrors;
            }
        }

        return $this->errors = $errors;
    }

    /**
     * Runs one field's full rule list against its value.
     *
     * @param  string   $field
     * @param  string[] $ruleList
     * @return string[] Messages for this field; empty means it passed.
     */
    private function runField(string $field, array $ruleList): array
    {
        $value      = $this->data[$field] ?? null;
        $isMissing  = !array_key_exists($field, $this->data) || $value === null || $value === '';
        $isRequired = in_array('required', $ruleList, true);
        $isNullable = in_array('nullable', $ruleList, true);

        if ($isMissing) {
            if ($isRequired) {
                return [$this->message($field, 'required', "The {$field} field is required.")];
            }

            // Not required, and either explicitly nullable or simply absent —
            // nothing else to check against an empty value.
            if ($isNullable || !$isRequired) {
                return [];
            }
        }

        $errors = [];

        foreach ($ruleList as $rule) {
            if ($rule === 'required' || $rule === 'nullable') {
                continue; // already handled above
            }

            [$name, $params] = self::parseRule($rule);
            $error = $this->applyRule($field, $value, $name, $params);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Splits 'min:8' into ['min', ['8']], or 'email' into ['email', []].
     *
     * @param  string $rule
     * @return array{0: string, 1: string[]}
     */
    private static function parseRule(string $rule): array
    {
        [$name, $paramString] = array_pad(explode(':', $rule, 2), 2, null);
        $params = $paramString !== null ? explode(',', $paramString) : [];

        return [$name, $params];
    }

    /**
     * Dispatches to the matching rule check. Returns a message string on
     * failure, null on success. Unknown rule names are ignored (fail-open
     * on typos rather than fail-closed and reject every request — a rule
     * name that doesn't match anything here is a developer bug to catch
     * in testing, not something that should lock out real users).
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  string $rule
     * @param  string[] $params
     * @return string|null
     */
    private function applyRule(string $field, mixed $value, string $rule, array $params): ?string
    {
        return match ($rule) {
            'email'     => $this->checkEmail($field, $value),
            'numeric'   => $this->checkNumeric($field, $value),
            'integer'   => $this->checkInteger($field, $value),
            'string'    => $this->checkString($field, $value),
            'boolean'   => $this->checkBoolean($field, $value),
            'array'     => $this->checkArray($field, $value),
            'alpha'     => $this->checkPattern($field, $value, self::PATTERN_ALPHA, 'alpha', 'only contain letters'),
            'alpha_num' => $this->checkPattern($field, $value, self::PATTERN_ALPHA_NUM, 'alpha_num', 'only contain letters and numbers'),
            'url'       => $this->checkUrl($field, $value),
            'date'      => $this->checkDate($field, $value),
            'min'       => $this->checkMin($field, $value, $params),
            'max'       => $this->checkMax($field, $value, $params),
            'in'        => $this->checkIn($field, $value, $params),
            'regex'     => $this->checkRegex($field, $value, $params),
            'same'      => $this->checkSame($field, $value, $params),
            'different' => $this->checkDifferent($field, $value, $params),
            'confirmed' => $this->checkConfirmed($field, $value),
            'unique'    => $this->checkUnique($field, $value, $params),
            default     => null,
        };
    }

    private function checkEmail(string $field, mixed $value): ?string
    {
        return filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false
            ? $this->message($field, 'email', "The {$field} must be a valid email address.")
            : null;
    }

    private function checkNumeric(string $field, mixed $value): ?string
    {
        return is_numeric($value)
            ? null
            : $this->message($field, 'numeric', "The {$field} must be a number.");
    }

    private function checkInteger(string $field, mixed $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? null
            : $this->message($field, 'integer', "The {$field} must be an integer.");
    }

    private function checkString(string $field, mixed $value): ?string
    {
        return is_string($value)
            ? null
            : $this->message($field, 'string', "The {$field} must be a string.");
    }

    private function checkBoolean(string $field, mixed $value): ?string
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true)
            ? null
            : $this->message($field, 'boolean', "The {$field} must be true or false.");
    }

    private function checkArray(string $field, mixed $value): ?string
    {
        return is_array($value)
            ? null
            : $this->message($field, 'array', "The {$field} must be an array.");
    }

    private function checkPattern(string $field, mixed $value, string $pattern, string $ruleName, string $description): ?string
    {
        return is_string($value) && preg_match($pattern, $value) === 1
            ? null
            : $this->message($field, $ruleName, "The {$field} must {$description}.");
    }

    private function checkUrl(string $field, mixed $value): ?string
    {
        return filter_var((string) $value, FILTER_VALIDATE_URL) === false
            ? $this->message($field, 'url', "The {$field} must be a valid URL.")
            : null;
    }

    private function checkDate(string $field, mixed $value): ?string
    {
        if (!is_string($value)) {
            return $this->message($field, 'date', "The {$field} must be a valid date.");
        }

        $parsed = date_parse($value);
        return ($parsed['error_count'] ?? 1) === 0 && ($parsed['warning_count'] ?? 1) === 0
            ? null
            : $this->message($field, 'date', "The {$field} must be a valid date.");
    }

    /**
     * min: for strings checks character length, for numeric values checks the
     * value itself, for arrays checks item count — matches how 'max' below
     * and most validators in other frameworks split the same three ways.
     */
    private function checkMin(string $field, mixed $value, array $params): ?string
    {
        $bound = (float) ($params[0] ?? 0);

        $actual = match (true) {
            is_array($value)                    => count($value),
            is_numeric($value) && !is_string($value) => (float) $value,
            is_string($value) && is_numeric($value)  => (float) $value,
            default                              => mb_strlen((string) $value),
        };

        return $actual >= $bound
            ? null
            : $this->message($field, 'min', "The {$field} must be at least {$params[0]}.");
    }

    private function checkMax(string $field, mixed $value, array $params): ?string
    {
        $bound = (float) ($params[0] ?? 0);

        $actual = match (true) {
            is_array($value)                    => count($value),
            is_numeric($value) && !is_string($value) => (float) $value,
            is_string($value) && is_numeric($value)  => (float) $value,
            default                              => mb_strlen((string) $value),
        };

        return $actual <= $bound
            ? null
            : $this->message($field, 'max', "The {$field} must not be greater than {$params[0]}.");
    }

    private function checkIn(string $field, mixed $value, array $params): ?string
    {
        return in_array((string) $value, $params, true)
            ? null
            : $this->message($field, 'in', "The selected {$field} is invalid.");
    }

    private function checkRegex(string $field, mixed $value, array $params): ?string
    {
        $pattern = $params[0] ?? null;

        return $pattern !== null && is_string($value) && preg_match($pattern, $value) === 1
            ? null
            : $this->message($field, 'regex', "The {$field} format is invalid.");
    }

    private function checkSame(string $field, mixed $value, array $params): ?string
    {
        $other = $params[0] ?? null;

        return $other !== null && ($this->data[$other] ?? null) === $value
            ? null
            : $this->message($field, 'same', "The {$field} must match {$other}.");
    }

    private function checkDifferent(string $field, mixed $value, array $params): ?string
    {
        $other = $params[0] ?? null;

        return $other !== null && ($this->data[$other] ?? null) !== $value
            ? null
            : $this->message($field, 'different', "The {$field} must be different from {$other}.");
    }

    /** 'confirmed' expects a sibling '{field}_confirmation' input to match, e.g. password/password_confirmation. */
    private function checkConfirmed(string $field, mixed $value): ?string
    {
        return ($this->data["{$field}_confirmation"] ?? null) === $value
            ? null
            : $this->message($field, 'confirmed', "The {$field} confirmation does not match.");
    }

    /**
     * unique:table,column — requires a ConnectionInterface to have been passed
     * to Validator::make(). Table/column names go through the same identifier
     * pattern check Model.php uses, since they're interpolated into raw SQL
     * (there's no ORM column whitelist to lean on here — this runs standalone,
     * before any Model is involved).
     */
    private function checkUnique(string $field, mixed $value, array $params): ?string
    {
        [$table, $column] = array_pad($params, 2, null);
        $column ??= $field;

        if ($this->db === null) {
            throw new \Framework\Exception\FrameworkException(
                "500 The 'unique' rule on '{$field}' requires a ConnectionInterface — " .
                "pass one as Validator::make()'s 4th argument."
            );
        }

        foreach ([$table, $column] as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $identifier)) {
                throw new \Framework\Exception\FrameworkException(
                    "500 Invalid table/column name in 'unique' rule on '{$field}'."
                );
            }
        }

        $existing = $this->db->queryOne(
            "SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1",
            [$value]
        );

        return $existing === null
            ? null
            : $this->message($field, 'unique', "The {$field} has already been taken.");
    }

    /**
     * Looks up a custom override ('field.rule') before falling back to the
     * default message passed in by the caller.
     */
    private function message(string $field, string $rule, string $default): string
    {
        return $this->customMessages["{$field}.{$rule}"] ?? $default;
    }
}
