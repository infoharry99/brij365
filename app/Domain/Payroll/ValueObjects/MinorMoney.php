<?php

namespace App\Domain\Payroll\ValueObjects;

use InvalidArgumentException;

final readonly class MinorMoney
{
    private function __construct(public int $minor)
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money values may not be negative.');
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromMinor(int $minor): self
    {
        return new self($minor);
    }

    public static function fromDecimal(int|string $amount): self
    {
        $value = trim((string) $amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Money must be a non-negative decimal value.');
        }

        $maximumMajor = (string) intdiv(PHP_INT_MAX - 99, 100);
        $normalizedMajor = ltrim($matches[1], '0');
        $normalizedMajor = $normalizedMajor === '' ? '0' : $normalizedMajor;
        if (strlen($normalizedMajor) > strlen($maximumMajor)
            || (strlen($normalizedMajor) === strlen($maximumMajor) && strcmp($normalizedMajor, $maximumMajor) > 0)) {
            throw new InvalidArgumentException('Money exceeds the supported integer minor-unit range.');
        }

        $major = (int) $normalizedMajor;
        $fraction = str_pad($matches[2] ?? '', 3, '0');
        $minor = self::safeAdd(self::safeMultiply($major, 100), (int) substr($fraction, 0, 2));

        if ((int) $fraction[2] >= 5) {
            $minor++;
        }

        return new self($minor);
    }

    public function add(self $other): self
    {
        return new self(self::safeAdd($this->minor, $other->minor));
    }

    public function subtract(self $other): self
    {
        if ($other->minor > $this->minor) {
            throw new InvalidArgumentException('Money subtraction may not produce a negative result.');
        }

        return new self($this->minor - $other->minor);
    }

    public function multiplyPpm(int $ratePpm): self
    {
        if ($ratePpm < 0 || $ratePpm > 1_000_000) {
            throw new InvalidArgumentException('Rate must be between 0 and 1,000,000 parts per million.');
        }

        return new self(self::divideHalfUp(self::safeMultiply($this->minor, $ratePpm), 1_000_000));
    }

    public function multiplyRatio(int $numerator, int $denominator): self
    {
        if ($numerator < 0 || $denominator <= 0) {
            throw new InvalidArgumentException('Money ratios require a non-negative numerator and positive denominator.');
        }

        return new self(self::divideHalfUp(self::safeMultiply($this->minor, $numerator), $denominator));
    }

    public function min(self $limit): self
    {
        return new self(min($this->minor, $limit->minor));
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->minor, 100), $this->minor % 100);
    }

    private static function divideHalfUp(int $numerator, int $denominator): int
    {
        return intdiv(self::safeAdd($numerator, intdiv($denominator, 2)), $denominator);
    }

    private static function safeAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('Money arithmetic exceeds the supported integer range.');
        }

        return $left + $right;
    }

    private static function safeMultiply(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new InvalidArgumentException('Money arithmetic exceeds the supported integer range.');
        }

        return $left * $right;
    }
}
