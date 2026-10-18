<?php

namespace App\Domain;

use Illuminate\Validation\ValidationException;

/** Canonical, minimal, validated definitions; no timestamps, names or credentials. */
final class SnapshotManifest
{
    public static function rule(array $row): array
    {
        if (! in_array($row['effect'] ?? null, ['DENY', 'REVIEW', 'ALLOW'], true)
            || ! is_string($row['reason_code'] ?? null) || ! preg_match('/^[A-Z][A-Z0-9_]{0,63}$/D', $row['reason_code'])) {
            throw ValidationException::withMessages(['draft_id' => ['The policy snapshot is malformed. Save a valid draft first.']]);
        }

        return ['id' => $row['id'], 'policy_id' => $row['policy_id'], 'version' => (int) $row['version'],
            'conditions' => ConditionSchema::validate($row['conditions']), 'effect' => $row['effect'], 'reason_code' => $row['reason_code']];
    }

    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    public static function fingerprint(array $manifest): string
    {
        return hash('sha256', json_encode(self::canonical($manifest), JSON_THROW_ON_ERROR));
    }

    public static function bounded(array $value): array
    {
        if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 1048576) {
            throw ValidationException::withMessages(['policies' => ['Policy snapshot metadata exceeds the 1 MiB safety limit. Reduce the policy set.']]);
        }

        return $value;
    }
}
