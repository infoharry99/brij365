<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $confirmed = true): array
    {
        $diagnostics = self::diagnostics();
        $rule = Password::min($diagnostics['min_length'])
            ->max($diagnostics['max_length']);

        if ($diagnostics['require_mixed_case']) {
            $rule->mixedCase();
        }

        if ($diagnostics['require_numbers']) {
            $rule->numbers();
        }

        if ($diagnostics['require_symbols']) {
            $rule->symbols();
        }

        if ($diagnostics['uncompromised']) {
            $rule->uncompromised($diagnostics['max_compromised_threshold']);
        }

        return array_values(array_filter([
            'required',
            'string',
            $confirmed ? 'confirmed' : null,
            $rule,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function diagnostics(): array
    {
        return [
            'min_length' => max(1, (int) config('security.password_policy.min_length', 10)),
            'max_length' => max(1, (int) config('security.password_policy.max_length', 255)),
            'require_mixed_case' => (bool) config('security.password_policy.require_mixed_case', true),
            'require_numbers' => (bool) config('security.password_policy.require_numbers', true),
            'require_symbols' => (bool) config('security.password_policy.require_symbols', true),
            'uncompromised' => (bool) config('security.password_policy.uncompromised', false),
            'max_compromised_threshold' => max(0, (int) config('security.password_policy.max_compromised_threshold', 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function readiness(): array
    {
        $diagnostics = self::diagnostics();
        $acceptable = [
            'min_length_at_least_10' => $diagnostics['min_length'] >= 10,
            'max_length_at_least_min_length' => $diagnostics['max_length'] >= $diagnostics['min_length'],
            'mixed_case_required' => $diagnostics['require_mixed_case'] === true,
            'numbers_required' => $diagnostics['require_numbers'] === true,
            'symbols_required' => $diagnostics['require_symbols'] === true,
            'compromised_threshold_valid' => $diagnostics['max_compromised_threshold'] >= 0,
        ];

        return $diagnostics + [
            'requirements' => $acceptable,
            'acceptable' => ! in_array(false, $acceptable, true),
            'failure' => self::failureReason($acceptable),
        ];
    }

    /**
     * @param  array<string, bool>  $requirements
     */
    private static function failureReason(array $requirements): ?string
    {
        foreach ($requirements as $key => $passes) {
            if (! $passes) {
                return 'password_policy_'.$key.'_failed';
            }
        }

        return null;
    }
}
