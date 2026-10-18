<?php

namespace App\Domain;

use Illuminate\Validation\ValidationException;

final class ActionSchema
{
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'TRY', 'CAD', 'AUD', 'NZD', 'JPY', 'CHF', 'CNY', 'INR', 'BRL', 'MXN', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'SGD', 'HKD', 'KRW', 'ZAR', 'AED', 'SAR', 'ILS'];

    public const FIELDS = ['action_name', 'amount_minor', 'currency', 'destination_domain', 'resource_type'];

    public const MAX_AMOUNT = 100000000000;

    public static function validValue(string $field, mixed $value): bool
    {
        return match ($field) {
            'amount_minor' => is_int($value) && $value >= 0 && $value <= self::MAX_AMOUNT,
            'currency' => is_string($value) && in_array($value, self::CURRENCIES, true),
            'destination_domain' => is_string($value) && strlen($value) <= 253 && (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $value),
            'action_name' => is_string($value) && strlen($value) <= 128 && (bool) preg_match('/^[a-z][a-z0-9_.:-]*$/D', $value),
            'resource_type' => is_string($value) && strlen($value) <= 64 && (bool) preg_match('/^[a-z][a-z0-9_.:-]*$/D', $value),
            default => false,
        };
    }

    public static function normalize(array $input): array
    {
        $errors = [];
        foreach ($input as $field => $value) {
            if (! in_array($field, [...self::FIELDS, 'resource_label'], true)) {
                $errors[$field] = ['Unsupported field. Tenant context cannot be supplied.'];
            } elseif ($field === 'resource_label') {
                if (! is_string($value) || strlen($value) > 80) {
                    $errors[$field] = ['Use a string of at most 80 bytes.'];
                }
            } else {
                if ($field === 'destination_domain' && is_string($value)) {
                    $input[$field] = $value = strtolower($value);
                }
                if (! self::validValue($field, $value)) {
                    $errors[$field] = ['Invalid type or value. Amounts must be JSON integers; currency must be supported.'];
                }
            }
        }
        if (! isset($input['action_name'])) {
            $errors['action_name'] = ['The action name field is required.'];
        }
        if (array_key_exists('amount_minor', $input) && ! isset($input['currency'])) {
            $errors['currency'] = ['Currency is required with an amount.'];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        // Free text labels can contain PII. They never affect a policy or enter storage.
        unset($input['resource_label']);
        ksort($input);

        return $input;
    }
}
