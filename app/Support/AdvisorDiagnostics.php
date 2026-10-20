<?php

namespace App\Support;

/** Finite, content-free diagnostics safe for production logs and operator output. */
final class AdvisorDiagnostics
{
    public static function safe(array $input): array
    {
        $enums = [
            'advisor_status' => ['completed', 'invalid_response', 'authentication', 'credit', 'rate_limit', 'provider_error', 'request_error', 'timeout', 'tls_error', 'transport_error'],
            'failure_stage' => ['none', 'transport', 'http', 'envelope', 'binding', 'provider', 'output'],
            'validation_reason' => ['accepted', 'connection_failed', 'tls_failed', 'timed_out', 'http_rejected', 'response_too_large', 'invalid_envelope_json', 'invalid_envelope', 'missing_thread', 'assistant_mismatch', 'provider_failed', 'provider_incomplete', 'tools_requested', 'model_mismatch', 'unexpected_context', 'content_type', 'invalid_content_json', 'content_schema', 'content_length', 'unsafe_content', 'unsupported_claim', 'aggregate_mismatch'],
            'cleanup_status' => ['confirmed', 'failed', 'no_thread'],
            'response_status' => ['COMPLETED', 'FAILED', 'REQUIRES_ACTION', 'IN_PROGRESS', 'CANCELLED'],
        ];
        $safe = [];
        foreach ($enums as $field => $allowed) {
            $safe[$field] = in_array($input[$field] ?? null, $allowed, true) ? $input[$field] : null;
        }
        foreach (['http_status', 'cleanup_http_status'] as $field) {
            $value = $input[$field] ?? null;
            $safe[$field] = is_int($value) && $value >= 100 && $value <= 599 ? $value : null;
        }
        $safe['elapsed_ms'] = is_int($input['elapsed_ms'] ?? null) ? max(0, min(120000, $input['elapsed_ms'])) : null;
        $safe['generation_attempted'] = ($input['generation_attempted'] ?? false) === true;

        return $safe;
    }
}
