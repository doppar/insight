<?php

declare(strict_types=1);

namespace Doppar\Insight\Support;

use JsonException;

class SensitiveDataSanitizer
{
    public const REDACTED = '[REDACTED]';

    /** @var array<int, string> */
    private array $sensitiveKeys;

    /**
     * @param array<int, string> $sensitiveKeys
     */
    public function __construct(
        array $sensitiveKeys = [],
        private readonly bool $redactSqlBindings = true,
        private readonly bool $redactRawBody = true
    ) {
        $defaults = [
            'password',
            'passwd',
            'pwd',
            'token',
            'secret',
            'authorization',
            'cookie',
            'api_key',
            'apikey',
            'access_token',
            'refresh_token',
            'client_secret',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];

        $keys = array_merge($defaults, $sensitiveKeys);
        $this->sensitiveKeys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => strtolower(trim((string) $key)),
            $keys
        ))));
    }

    public static function fromConfig(): self
    {
        $keys = [];
        $redactSqlBindings = true;
        $redactRawBody = true;

        if (function_exists('config')) {
            try {
                $configuredKeys = config('insight.sensitive_keys', []);
                $keys = is_array($configuredKeys) ? $configuredKeys : [];
                $redactSqlBindings = (bool) config('insight.redact_sql_bindings', true);
                $redactRawBody = (bool) config('insight.redact_raw_body', true);
            } catch (\Throwable) {
            }
        }

        return new self($keys, $redactSqlBindings, $redactRawBody);
    }

    public function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, mixed>
     */
    public function sanitizeSqlBindings(array $bindings): array
    {
        if (! $this->redactSqlBindings) {
            return $this->sanitize($bindings);
        }

        return array_fill_keys(array_keys($bindings), self::REDACTED);
    }

    public function sanitizeRawBody(string $body): mixed
    {
        return $this->sanitizeJsonOrText($body, $this->redactRawBody);
    }

    public function sanitizeJsonOrText(string $value, bool $redactNonJson = false): mixed
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return $this->sanitize($decoded);
        } catch (JsonException) {
            return $redactNonJson ? self::REDACTED : $this->sanitizeText($value);
        }
    }

    public function sanitizeText(string $value): string
    {
        if ($value === '' || $this->sensitiveKeys === []) {
            return $value;
        }

        $keys = array_map(
            static fn (string $key): string => preg_quote($key, '/'),
            $this->sensitiveKeys
        );
        $pattern = "/((?:\"|')?(?:" . implode('|', $keys) . ")(?:\"|')?\\s*[:=]\\s*(?:\"|')?)([^\"'\\s,;&}]+(?:\\s+[^\"'\\s,;&}]+)*)/i";

        $sanitized = preg_replace_callback(
            $pattern,
            static fn (array $match): string => $match[1] . self::REDACTED,
            $value
        );

        return $sanitized ?? self::REDACTED;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $key) ?? $key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            $normalizedSensitiveKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $sensitiveKey) ?? $sensitiveKey);
            if ($normalizedSensitiveKey !== '' && str_contains($normalizedKey, $normalizedSensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
