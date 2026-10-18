<?php

namespace App\Domain;

final class ReplayComparison
{
    public function __construct(private PolicyEvaluator $evaluator) {}

    public static function draft(array $row): array
    {
        $rule = SnapshotManifest::rule($row);
        $fingerprint = SnapshotManifest::fingerprint($rule);

        return [...$rule, 'id' => 'draft:'.$fingerprint, 'source_version_id' => $rule['id'], 'source' => 'DRAFT', 'fingerprint' => $fingerprint];
    }

    public function compare(array $action, array $baseline, array $draft): array
    {
        $candidate = array_values(array_filter($baseline, fn ($rule) => $rule['policy_id'] !== $draft['policy_id']));
        $candidate[] = $draft;

        return ['baseline' => $this->outcome($action, $baseline), 'candidate' => $this->outcome($action, $candidate)];
    }

    private function outcome(array $action, array $rules): array
    {
        $result = $this->evaluator->evaluate($action, $rules);
        $matched = null;
        foreach ($rules as $rule) {
            if ($result['policy_version_id'] === $rule['id']) {
                $isDraft = ($rule['source'] ?? '') === 'DRAFT';
                $matched = ['source' => $isDraft ? 'DRAFT' : 'PUBLISHED', 'policy_id' => $rule['policy_id'],
                    'policy_version_id' => $isDraft ? null : $rule['id'], 'draft_version_id' => $isDraft ? $rule['source_version_id'] : null,
                    'fingerprint' => $isDraft ? $rule['fingerprint'] : SnapshotManifest::fingerprint($rule), 'version' => $rule['version']];
                break;
            }
        }

        return ['decision' => $result['decision'], 'reason_code' => $result['reason_code'], 'match' => $matched];
    }
}
