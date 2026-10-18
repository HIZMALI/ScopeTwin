<?php

namespace App\Domain;

/** Pure: no persistence, time, network, credentials, or business tools. */
final class PolicyEvaluator
{
    public function evaluate(array $action, array $snapshots): array
    {
        $priority = ['DENY' => 0, 'REVIEW' => 1, 'ALLOW' => 2];
        usort($snapshots, fn ($a, $b) => [$priority[$a['effect']] ?? -1, $a['policy_id'], $a['version']]
            <=> [$priority[$b['effect']] ?? -1, $b['policy_id'], $b['version']]);
        foreach ($snapshots as $rule) {
            if ($this->matches($action, $rule['conditions'])) {
                return ['decision' => $rule['effect'], 'reason_code' => $rule['reason_code'], 'policy_version_id' => $rule['id']];
            }
        }

        return ['decision' => 'DENY', 'reason_code' => 'DEFAULT_DENY', 'policy_version_id' => null];
    }

    private function matches(array $action, array $conditions): bool
    {
        foreach ($conditions as $field => $expression) {
            if (! array_key_exists($field, $action)) {
                return false;
            }
            $op = array_key_first($expression);
            $expected = $expression[$op];
            $actual = $action[$field];
            $matches = match ($op) {
                'eq' => $actual === $expected,
                'in' => in_array($actual, $expected, true),
                'gt' => $actual > $expected,
                'gte' => $actual >= $expected,
                'lt' => $actual < $expected,
                'lte' => $actual <= $expected,
                default => false,
            };
            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
