<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use Random\Randomizer;
use Tilttek\Referrals\Exception\InvalidReferral;

/**
 * The identifier of a referral, drawn at random: the keys of its movements of points carry it rather than the members,
 * so that the ledger keeps no link between them once the referral is erased.
 */
final readonly class ReferralId
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(Randomizer $randomizer): self
    {
        return new self($randomizer->getBytesFromString('0123456789abcdef', 32));
    }

    /**
     * @throws InvalidReferral when the text is not 32 lower-case hexadecimal digits
     */
    public static function fromString(string $id): self
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidReferral::because('A referral identifier is 32 lower-case hexadecimal digits.');
        }

        return new self($id);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
