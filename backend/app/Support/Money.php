<?php

namespace Savv\Support;

use InvalidArgumentException;

/**
 * A fixed-precision money value: integer minor units (e.g. paise). Never
 * backed by a float, so it can be stored and compared exactly.
 */
final class Money
{
    private function __construct(
        public readonly int $minorUnits,
        public readonly string $currency,
    ) {}

    public static function fromMinorUnits(int $minorUnits, string $currency = 'INR'): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /**
     * Parse a human-entered decimal amount string (e.g. "1,234.50" or "₹1234.50")
     * into minor units. Rejects anything that isn't a plain decimal number.
     */
    public static function fromDecimalString(string $amount, string $currency = 'INR'): self
    {
        $cleaned = preg_replace('/[^\d.\-]/', '', $amount) ?? '';

        if ($cleaned === '' || ! preg_match('/^-?\d+(\.\d{1,2})?$/', $cleaned)) {
            throw new InvalidArgumentException("Amount \"{$amount}\" is not a valid decimal money value.");
        }

        [$whole, $fraction] = array_pad(explode('.', $cleaned, 2), 2, '00');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $minor = ((int) $whole) * 100 + ((int) ltrim($fraction, '-')) * (str_starts_with($whole, '-') ? -1 : 1);

        return new self($minor, strtoupper($currency));
    }

    public function toDecimalString(): string
    {
        return number_format($this->minorUnits / 100, 2, '.', '');
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot combine money in different currencies.');
        }
    }
}
