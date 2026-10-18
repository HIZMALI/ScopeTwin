<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

final class RedactSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            // Production exception messages/SQL bindings may contain user data. Keep type only.
            if (app()->isProduction()) {
                return $record->with(message: 'Application event', context: [
                    'exception_type' => isset($record->context['exception']) ? get_debug_type($record->context['exception']) : null,
                    'advisor_status' => in_array($record->context['advisor_status'] ?? '', ['cleanup_unconfirmed', 'completed', 'invalid_response', 'authentication', 'credit', 'rate_limit', 'provider_error', 'timeout', 'transport_error'], true) ? $record->context['advisor_status'] : null,
                    'elapsed_ms' => isset($record->context['elapsed_ms']) && is_int($record->context['elapsed_ms']) ? max(0, min(60000, $record->context['elapsed_ms'])) : null,
                ], extra: []);
            }

            return $record->with(message: self::clean($record->message), context: self::clean($record->context), extra: self::clean($record->extra));
        });
    }

    public static function clean(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return get_class($value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = preg_match('/password|token|authorization|secret|key_hash|cookie|email/i', (string) $key) ? '[REDACTED]' : self::clean($item);
            }

            return $value;
        }
        if (is_string($value)) {
            return preg_replace(['/Bearer\s+\S+/i', '/st_[a-f0-9]{16}_[a-f0-9]{64}/i'], '[REDACTED]', $value);
        }

        return is_scalar($value) || $value === null ? $value : '[OBJECT]';
    }
}
