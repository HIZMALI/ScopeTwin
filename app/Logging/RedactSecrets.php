<?php

namespace App\Logging;

use App\Support\AdvisorDiagnostics;
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
                $context = ['exception_type' => isset($record->context['exception']) ? get_debug_type($record->context['exception']) : null];
                if (isset($record->context['advisor_status'])) {
                    $context = array_merge($context, ['event' => 'replay_advisor'], AdvisorDiagnostics::safe($record->context));
                }
                if (($record->context['deployment_event'] ?? null) === 'tls_state') {
                    $context['deployment_event'] = 'tls_state';
                    foreach (['secure', 'hsts_configured', 'proxy_trusted', 'forwarded_https'] as $field) {
                        $context[$field] = ($record->context[$field] ?? null) === true;
                    }
                }
                $cipher = $record->context['peer_cipher'] ?? null;
                if (($record->context['deployment_event'] ?? null) === 'proxy_peer_encrypted'
                    && is_string($cipher) && strlen($cipher) <= 512 && preg_match('/^[A-Za-z0-9+\/=]+$/D', $cipher)) {
                    $context = array_merge($context, ['deployment_event' => 'proxy_peer_encrypted', 'peer_cipher' => $cipher]);
                }
                $peer = $record->context['proxy_peer'] ?? null;
                if (($record->context['deployment_event'] ?? null) === 'proxy_peer' && is_string($peer)
                    && filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && ! filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $context = array_merge($context, ['deployment_event' => 'proxy_peer', 'proxy_peer' => $peer]);
                }

                return $record->with(message: 'Application event', context: $context, extra: []);
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
