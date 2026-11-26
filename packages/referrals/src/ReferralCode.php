<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use Random\Randomizer;
use Tilttek\Referrals\Exception\InvalidReferral;

/**
 * The code a member shares to refer others, in a link or by hand: random, so that it tells nothing of the member, and
 * in Crockford's base 32 — digits and capital letters without I, L, O and U —, read back whatever its case and with
 * I and L as 1, O as 0, and hyphens left out, as a person copies it.
 */
final readonly class ReferralCode
{
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * The length of a code the library draws: 50 random bits.
     */
    public const int LENGTH = 10;

    public const int MIN_LENGTH = 8;

    public const int MAX_LENGTH = 32;

    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(Randomizer $randomizer): self
    {
        return new self($randomizer->getBytesFromString(self::ALPHABET, self::LENGTH));
    }

    /**
     * @throws InvalidReferral when the text is not a code
     */
    public static function fromString(string $code): self
    {
        $normalized = strtr(strtoupper(str_replace('-', '', $code)), ['I' => '1', 'L' => '1', 'O' => '0']);

        if (1 !== preg_match('/^[0-9A-HJKMNP-TV-Z]{'.self::MIN_LENGTH.','.self::MAX_LENGTH.'}$/D', $normalized)) {
            throw InvalidReferral::because(\sprintf('A referral code is %d to %d letters and digits of base 32.', self::MIN_LENGTH, self::MAX_LENGTH));
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
