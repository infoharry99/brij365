<?php

namespace App\Support;

use Illuminate\Validation\Validator;

class QueryFilterPolicy
{
    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $allowed
     */
    public function rejectUnexpected(
        Validator $validator,
        array $query,
        array $allowed,
        string $message = 'The selected filter is not available for this endpoint.',
    ): void {
        $allowedLookup = array_fill_keys($allowed, true);

        foreach (array_keys($query) as $key) {
            if (! isset($allowedLookup[$key])) {
                $validator->errors()->add((string) $key, $message);
            }
        }
    }
}
