<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

use Tilttek\Heraldry\Exception\InvalidCoat;

/**
 * The ground of the shield: one tincture, or two divided by a partition.
 */
final readonly class Field
{
    /**
     * @param list<Tincture> $tinctures one for a plain field, two for a divided one
     */
    private function __construct(
        public ?Partition $partition,
        public array $tinctures,
    ) {
    }

    public static function plain(Tincture $tincture): self
    {
        return new self(null, [$tincture]);
    }

    /**
     * @throws InvalidCoat when both tinctures are the same
     */
    public static function divided(Partition $partition, Tincture $first, Tincture $second): self
    {
        if ($first === $second) {
            throw InvalidCoat::because('A divided field has two different tinctures.');
        }

        return new self($partition, [$first, $second]);
    }

    public function isPlain(): bool
    {
        return !$this->partition instanceof Partition;
    }

    /** The tincture of a plain field; the first of a divided one. */
    public function tincture(): Tincture
    {
        return $this->tinctures[0];
    }

    public function has(Tincture $tincture): bool
    {
        return \in_array($tincture, $this->tinctures, true);
    }
}
