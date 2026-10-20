<?php

namespace App\Services;

use JsonException;

/** Bounded untrusted prose. Numeric facts are checked against saved replay aggregates. */
final class ReplayAdviceOutput
{
    public function validate(mixed $content, array $summary): array
    {
        if (! is_string($content) || strlen($content) > 8192) {
            return $this->reject('content_type');
        }
        try {
            // Backboard content is a JSON string. Also tolerate a JSON-encoded JSON string.
            $data = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
            if (is_string($data)) {
                $data = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException) {
            return $this->reject('invalid_content_json');
        }
        if (! is_array($data) || ! is_string($data['explanation'] ?? null) || ! is_array($data['questions'] ?? null)
            || ! array_is_list($data['questions']) || count($data['questions']) > 2) {
            return $this->reject('content_schema');
        }
        $advice = ['explanation' => trim($data['explanation']), 'questions' => []];
        foreach ($data['questions'] as $question) {
            if (! is_string($question)) {
                return $this->reject('content_schema');
            }
            $advice['questions'][] = trim($question);
        }
        foreach ([$advice['explanation'], ...$advice['questions']] as $index => $text) {
            if ($text === '' || ! mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > ($index === 0 ? 1600 : 240)) {
                return $this->reject('content_length');
            }
            foreach (['api_key', 'assistant_id'] as $private) {
                $secret = config('backboard.'.$private);
                if (is_string($secret) && $secret !== '' && stripos($text, $secret) !== false) {
                    return $this->reject('unsafe_content');
                }
            }
            // No credentials, identifiers, contact data, links or executable markup.
            if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]|<[^>]*>|https?:|www\.|[\w.+-]+@[\w.-]+\.[a-z]{2,}|\b[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\b|\bst_[a-z0-9_]+|\b(?:\d{1,3}\.){3}\d{1,3}\b/iu', $text)) {
                return $this->reject('unsafe_content');
            }
            // Reject invented business/causal claims and action instructions, not harmless nouns
            // such as "refund" or "approval" in an explanation of this simulated workflow.
            if (preg_match('/[$€£%]|\b(?:savings|guarantee\w*|compliant|certified|fraud|pii)\b|\b(?:prevent\w*|reduc\w*|eliminat\w*)\s+(?:\w+\s+){0,3}(?:loss\w*|risk\w*|cost\w*)\b/iu', $text)
                || preg_match('/\b(?:(?:you|we|the system|the advisor)\s+(?:should|must|can|will)|please|recommend(?:s|ed)?(?:\s+that\s+you)?)\s+(?:\w+\s+){0,2}(?:approv\w*|reject\w*|execut\w*|publish\w*|authoriz\w*|transfer\w*|refund\w*|allow\w*|den(?:y|ial)\w*|disabl\w*)\b|\b(?:approve|execute|publish|authorize|transfer|disable)\s+(?:the|this|all|these|a)\b|\b(?:permission|approval|authorization)\s+(?:is|has been)\s+granted\b|\b(?:disable|remove|bypass|ignore|relax|turn off)\b[\w\s]{0,40}\b(?:checks?|authorization|authentication|csrf|scope|quotas?|security|isolation|validation)\b/iu', $text)) {
                return $this->reject('unsupported_claim');
            }
            if (! $this->factsMatch($text, $summary)) {
                return $this->reject('aggregate_mismatch');
            }
        }

        return ['advice' => $advice, 'reason' => 'accepted'];
    }

    private function factsMatch(string $text, array $summary): bool
    {
        $facts = ['changed' => $summary['changed'], 'newly denied' => $summary['newly_denied'],
            'newly reviewed' => $summary['newly_reviewed'], 'unchanged' => $summary['unchanged'],
            'sampled' => $summary['sample_count'], 'total' => $summary['sample_count']];
        // Numbers are welcome when supplied by the deterministic aggregate, never invented.
        preg_match_all('/\d+(?:\.\d+)?/', $text, $numbers);
        $values = array_merge(array_values($facts), array_values($summary['transitions']));
        foreach ($numbers[0] as $number) {
            if (! ctype_digit($number) || ! in_array((int) $number, $values, true)) {
                return false;
            }
        }
        foreach ($facts as $label => $expected) {
            $pattern = $label === 'changed' ? '(?:changed|changes)' : preg_quote($label, '/');
            if (preg_match_all('/\b(\d+)\s+'.$pattern.'\b|\b'.$pattern.'\s*(?::|=|is|are)?\s*(\d+)\b/iu', $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if ((int) (($match[1] ?? '') !== '' ? $match[1] : $match[2]) !== $expected) {
                        return false;
                    }
                }
            }
        }
        if (preg_match_all('/\b(\d+)\s*(?:of|out of|\/)\s*(\d+)\s+(?:(?:sampled|recorded|historical)\s+)?(?:requests|decisions|outcomes)\s+(?:have\s+)?changed\b/iu', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if ((int) $match[1] !== $summary['changed'] || (int) $match[2] !== $summary['sample_count']) {
                    return false;
                }
            }
        }

        return true;
    }

    private function reject(string $reason): array
    {
        return ['advice' => null, 'reason' => $reason];
    }
}
