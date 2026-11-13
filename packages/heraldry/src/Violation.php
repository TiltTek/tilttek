<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * One place where a coat breaks the rule of tincture: what lies on what.
 */
final readonly class Violation
{
    /**
     * @param string $layer what lies on the ground: `piece` for the ordinary, `charges` for the charges
     */
    public function __construct(
        public string $layer,
        public Tincture $tincture,
        public Tincture $ground,
    ) {
    }

    /** The fault in a sentence: "The charges lie or on argent: metal on metal." */
    public function describe(): string
    {
        $kind = $this->tincture->kind();
        $fault = $this->tincture === $this->ground ? 'a tincture on itself' : $kind->value.' on '.$kind->value;

        return \sprintf('%s %s on %s: %s.', 'piece' === $this->layer ? 'The ordinary lies' : 'The charges lie', $this->tincture->value, $this->ground->value, $fault);
    }
}
