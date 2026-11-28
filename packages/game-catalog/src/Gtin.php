<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * The barcode of a box: a GTIN of 8, 12, 13 or 14 digits — an EAN, a UPC, the ISBN-13 of a book —, its check digit
 * verified, kept as 14 digits so that the same box scanned as a UPC or as an EAN is the same code.
 */
final readonly class Gtin
{
    /** The 14 digits, padded with zeros on the left. */
    public string $value;

    /**
     * @throws InvalidEntry when the code is not 8, 12, 13 or 14 digits, or its check digit is wrong
     */
    public function __construct(string $code)
    {
        $digits = str_replace([' ', '-'], '', $code);

        if (1 !== preg_match('/^(?:[0-9]{8}|[0-9]{12,14})$/D', $digits)) {
            throw InvalidEntry::because('A barcode is a GTIN of 8, 12, 13 or 14 digits.');
        }

        $digits = str_pad($digits, 14, '0', \STR_PAD_LEFT);
        $sum = 0;

        for ($i = 0; $i < 13; ++$i) {
            $sum += (int) $digits[$i] * (0 === $i % 2 ? 3 : 1);
        }

        if ((10 - $sum % 10) % 10 !== (int) $digits[13]) {
            throw InvalidEntry::because('The check digit of the barcode is wrong.');
        }

        $this->value = $digits;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
