<?php

declare(strict_types=1);

namespace Tilttek\Factions\View;

use Tilttek\Factions\Tier;

/**
 * Where a subject stands with a faction, as shown: the value and the tier that holds it.
 */
final readonly class StandingView
{
    /**
     * @param string $subject the subject's key: "party", or "character:" and an identifier
     */
    public function __construct(public string $subject, public int $value, public Tier $tier)
    {
    }

    /**
     * @return array{subject: string, value: int, tier: string, label: string}
     */
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'value' => $this->value, 'tier' => $this->tier->key, 'label' => $this->tier->label];
    }
}
