<?php

declare(strict_types=1);

namespace Tilttek\Rating\OpenSkill;

use Override;
use Tilttek\Rating\Contract\Rating;
use Tilttek\Rating\Exception\InvalidRating;

/**
 * A skill as OpenSkill estimates it: its mean `mu` and its uncertainty `sigma`, in millionths.
 */
final readonly class SkillRating implements Rating
{
    /** The fixed point of `mu` and `sigma`. */
    public const int SCALE = 1_000_000;

    /**
     * @throws InvalidRating when sigma is not above 0
     */
    public function __construct(
        public int $mu,
        public int $sigma,
    ) {
        if ($sigma < 1) {
            throw InvalidRating::because('The uncertainty of a skill is above 0.');
        }
    }

    public static function of(float $mu, float $sigma): self
    {
        return new self((int) round($mu * self::SCALE), max(1, (int) round($sigma * self::SCALE)));
    }

    public function mean(): float
    {
        return $this->mu / self::SCALE;
    }

    public function deviation(): float
    {
        return $this->sigma / self::SCALE;
    }

    /**
     * The conservative estimate of the skill, `mu − z × sigma`: a skill the player has at least, with the confidence
     * `z` gives — about 99.9 % for 3.
     */
    public function conservative(float $z = 3.0): float
    {
        return $this->mean() - $z * $this->deviation();
    }

    #[Override]
    public function toArray(): array
    {
        return ['mu' => $this->mu, 'sigma' => $this->sigma];
    }
}
