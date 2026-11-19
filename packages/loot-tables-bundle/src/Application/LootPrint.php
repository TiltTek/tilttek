<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;

/**
 * Composes what is printed of an order's draws, read from `LootResults`: for each draw, by line, option and unit, its
 * result, its shop code and where it stands — verified against its commitment, not checking out, entered from physical
 * dice, or not drawn yet. The production sheet `LootSheetPdf` writes it out; an application that sends print files to
 * a workshop reads the images of each unit from it.
 */
final readonly class LootPrint
{
    public function __construct(
        private LootResults $results,
    ) {
    }

    /**
     * @return list<PrintedDraw>
     */
    public function compose(string $orderNumber): array
    {
        return array_map($this->printed(...), $this->results->ofOrder($orderNumber));
    }

    private function printed(LootDraw $draw): PrintedDraw
    {
        $result = $this->results->result($draw);

        if (!$result instanceof LootResult) {
            return new PrintedDraw($draw, PrintState::Pending);
        }

        $state = match (true) {
            null === $draw->proof() => PrintState::Physical,
            $this->results->verify($draw) => PrintState::Verified,
            default => PrintState::NotVerified,
        };

        return new PrintedDraw($draw, $state, $result, $this->results->shopCode($draw));
    }
}
