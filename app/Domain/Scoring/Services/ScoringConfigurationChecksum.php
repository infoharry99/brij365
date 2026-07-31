<?php

namespace App\Domain\Scoring\Services;

final class ScoringConfigurationChecksum
{
    /** @param array<string, mixed> $configuration */
    public function make(array $configuration): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($configuration),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $child): mixed => $this->canonicalize($child), $value);
    }
}
