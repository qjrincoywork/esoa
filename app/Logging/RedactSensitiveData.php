<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Defense-in-depth log processor that scrubs sensitive values from the log
 * message and context before they are written (F-05).
 *
 * The primary leak vector — plaintext passwords captured as function arguments
 * inside exception stack traces — is closed at the PHP level via
 * `zend.exception_ignore_args` (see Dockerfile). This processor additionally
 * prevents credentials from leaking through any log message/context payload
 * (e.g. an accidentally logged request body).
 */
class RedactSensitiveData implements ProcessorInterface
{
    private const REPLACEMENT = '[REDACTED]';

    /**
     * Context/extra keys whose values must never be logged.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'authorization',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrubArray($record->context),
            extra: $this->scrubArray($record->extra),
        );
    }

    /**
     * Recursively redact sensitive keys and inline credential patterns.
     */
    private function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = self::REPLACEMENT;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->scrubString($value);
            }
        }

        return $data;
    }

    /**
     * Redact inline credential shapes such as
     * `passes('password', '<value>')` or `password=<value>`.
     */
    private function scrubString(string $value): string
    {
        $keys = implode('|', self::SENSITIVE_KEYS);

        $patterns = [
            "/(passes\\(\\s*'(?:{$keys})'\\s*,\\s*')[^']*(')/i",
            "/(\"?(?:{$keys})\"?\\s*[:=]\\s*\"?)[^\"\\s,&}]+/i",
        ];

        return preg_replace($patterns, '${1}' . self::REPLACEMENT, $value) ?? $value;
    }
}
