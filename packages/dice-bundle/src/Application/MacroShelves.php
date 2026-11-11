<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\MacroLevel;

/**
 * Where the macros of an input are looked for: the member's first, then the table's, then the site's — whichever the
 * application gives, opaque references it owns.
 */
final readonly class MacroShelves
{
    public function __construct(
        public ?ResourceReference $site = null,
        public ?ResourceReference $table = null,
        public ?ResourceReference $member = null,
    ) {
    }

    /**
     * Each level given and its owner, in the order a name is looked for.
     *
     * @return list<array{MacroLevel, ResourceReference}>
     */
    public function shelves(): array
    {
        $shelves = [];

        foreach ([MacroLevel::Member, MacroLevel::Table, MacroLevel::Site] as $level) {
            $owner = $this->owner($level);

            if ($owner instanceof ResourceReference) {
                $shelves[] = [$level, $owner];
            }
        }

        return $shelves;
    }

    public function owner(MacroLevel $level): ?ResourceReference
    {
        return match ($level) {
            MacroLevel::Site => $this->site,
            MacroLevel::Table => $this->table,
            MacroLevel::Member => $this->member,
        };
    }

    /**
     * The shelves from this level on, in the order a name is looked for: what a macro kept at this level may name.
     */
    public function from(MacroLevel $level): self
    {
        return match ($level) {
            MacroLevel::Site => new self($this->site),
            MacroLevel::Table => new self($this->site, $this->table),
            MacroLevel::Member => $this,
        };
    }
}
