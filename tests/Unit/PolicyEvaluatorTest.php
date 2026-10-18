<?php

namespace Tests\Unit;

use App\Domain\PolicyEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PolicyEvaluatorTest extends TestCase
{
    private function rule(string $effect, string $id = 'a', array $conditions = ['action_name' => ['eq' => 'refund.issue']]): array
    {
        return ['id' => $id, 'policy_id' => $id, 'version' => 1, 'effect' => $effect, 'reason_code' => $id, 'conditions' => $conditions];
    }

    public function test_precedence_and_ties_are_deterministic_regardless_of_input_order(): void
    {
        $engine = new PolicyEvaluator;
        $rules = [$this->rule('ALLOW'), $this->rule('REVIEW', 'b'), $this->rule('DENY', 'z'), $this->rule('DENY', 'c')];
        $a = $engine->evaluate(['action_name' => 'refund.issue'], $rules);
        $this->assertSame($a, $engine->evaluate(['action_name' => 'refund.issue'], array_reverse($rules)));
        $this->assertSame('DENY', $a['decision']);
        $this->assertSame('c', $a['policy_version_id']);
        $this->assertSame('REVIEW', $engine->evaluate(['action_name' => 'refund.issue'], [$this->rule('ALLOW'), $this->rule('REVIEW')])['decision']);
        $this->assertSame('DEFAULT_DENY', $engine->evaluate([], [$this->rule('ALLOW')])['reason_code']);
    }

    #[DataProvider('comparators')]
    public function test_comparators_and_and_matching(string $op, mixed $expected, int $actual, bool $matches): void
    {
        $rule = $this->rule('ALLOW', 'a', ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => [$op => $expected], 'currency' => ['in' => ['USD', 'EUR']]]);
        $engine = new PolicyEvaluator;
        $this->assertSame($matches ? 'ALLOW' : 'DENY', $engine->evaluate(['action_name' => 'refund.issue', 'amount_minor' => $actual, 'currency' => 'USD'], [$rule])['decision']);
        $this->assertSame('DENY', $engine->evaluate(['action_name' => 'refund.issue', 'amount_minor' => $actual, 'currency' => 'GBP'], [$rule])['decision']);
    }

    public static function comparators(): array
    {
        return [['eq', 5, 5, true], ['eq', 5, 6, false], ['in', [4, 5], 5, true], ['in', [4, 5], 6, false], ['gt', 5, 6, true], ['gt', 5, 5, false], ['gte', 5, 5, true], ['gte', 5, 4, false], ['lt', 5, 4, true], ['lt', 5, 5, false], ['lte', 5, 5, true], ['lte', 5, 6, false]];
    }
}
