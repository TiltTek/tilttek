<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;

/**
 * A named step of a scale: it holds the standings from its lowest value up to the lowest value of the next tier.
 */
final readonly class Tier
{
    /** The longest label, in characters. */
    public const int MAX_LABEL = 60;

    public string $label;

    /**
     * @param string $key   the tier's key, as a page or a translation names it
     * @param string $label one line of 1 to `MAX_LABEL` characters
     * @param int    $from  the lowest standing of the tier
     *
     * @throws InvalidRoster when the key or the label is out of its shape or its bounds
     */
    public function __construct(public string $key, string $label, public int $from)
    {
        Identifier::key($key, 'a tier');
        $this->label = Text::line($label, self::MAX_LABEL, 'label of a tier');
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key && $this->label === $other->label && $this->from === $other->from;
    }
}
