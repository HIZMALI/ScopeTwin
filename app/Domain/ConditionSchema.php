<?php

namespace App\Domain;

use Illuminate\Validation\ValidationException;

final class ConditionSchema
{
    public static function validate(mixed $conditions): array
    {
        $fail = fn () => throw ValidationException::withMessages(['conditions' => ['Use 1–5 typed fields, one supported comparator per field, and valid values. An action_name condition is required.']]);
        if (! is_array($conditions) || ! $conditions || count($conditions) > 5 || ! isset($conditions['action_name'])) {
            $fail();
        }
        foreach ($conditions as $field => $expression) {
            if (! in_array($field, ActionSchema::FIELDS, true) || ! is_array($expression) || count($expression) !== 1) {
                $fail();
            }
            $op = array_key_first($expression);
            $value = $expression[$op];
            $operators = $field === 'amount_minor' ? ['eq', 'in', 'gt', 'gte', 'lt', 'lte'] : ['eq', 'in'];
            if (! in_array($op, $operators, true)) {
                $fail();
            }
            $values = $op === 'in' ? $value : [$value];
            if (! is_array($values) || ! array_is_list($values) || count($values) < 1 || count($values) > 20) {
                $fail();
            }
            foreach ($values as $item) {
                if (! ActionSchema::validValue($field, $item)) {
                    $fail();
                }
            }
        }
        ksort($conditions);

        return $conditions;
    }
}
