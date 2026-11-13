<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * A mark of difference laid over the arms, in its tincture: a label across the chief, or a bendlet over everything.
 * The rule of tincture does not apply to it, as it lies over several tinctures at once.
 */
final readonly class Brisure
{
    public function __construct(
        public BrisureMark $mark,
        public Tincture $tincture,
    ) {
    }
}
