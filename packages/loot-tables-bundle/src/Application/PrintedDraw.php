<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\Pick;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;

/**
 * What goes on one unit when an order is printed, as `LootPrint` composes it from `LootResults`: the draw, its result
 * — the entry of each table, with its face, its image and its slot —, the text drawn for a text, the shop code, and
 * where the draw stands.
 */
final readonly class PrintedDraw
{
    public function __construct(
        public LootDraw $draw,
        public PrintState $state,
        public ?LootResult $result = null,
        public ?string $shopCode = null,
    ) {
    }

    /**
     * The text drawn, for a draw of a text once drawn; `null` otherwise.
     */
    public function text(): ?string
    {
        return null !== $this->draw->texts() && $this->result instanceof LootResult ? $this->result->entries()[0]->name : null;
    }

    /**
     * The entry of each table, in the order of the tables, for a draw of a box once drawn; none otherwise.
     *
     * @return list<Pick>
     */
    public function picks(): array
    {
        return null === $this->draw->texts() && $this->result instanceof LootResult ? $this->result->picks : [];
    }

    /**
     * The images of the entries drawn, in the order of the tables, for the print files of the unit; an entry without
     * an image is left out.
     *
     * @return list<string>
     */
    public function images(): array
    {
        $images = [];

        foreach ($this->picks() as $pick) {
            if (null !== $pick->entry->image) {
                $images[] = $pick->entry->image;
            }
        }

        return $images;
    }
}
