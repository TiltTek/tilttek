<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Psr\Clock\ClockInterface;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * Commits the draws of an order before it is paid: for each unit of each option left to draw here, a seed drawn from
 * the cryptographic generator of `SecureSeeds`, kept secret, and its commitment, shown to the buyer. The bundle calls
 * it when the order is placed, through `CommitOnPlace`; the draws are revealed once the order is paid, by
 * `LootOptionDrawer`.
 *
 * Calling it again commits nothing more: a unit keeps its first commitment.
 */
final readonly class LootCommitments
{
    public function __construct(
        private Drawers $drawers,
        private LineOptions $options,
        private LootDrawRepository $draws,
        private SecureSeeds $seeds,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<LootDraw> the draws of the order's options drawn here, committed now or before
     *
     * @throws LootDrawRefused when a text option has no list a die draws from
     */
    public function commit(Order $order): array
    {
        $draws = [];

        foreach ($order->lines() as $line) {
            foreach ($line->configuration->values as $option => $value) {
                $drawer = $value->drawer ?? '';

                if (!$value->isPending() || !$this->drawers->supports($drawer)) {
                    continue;
                }

                for ($unit = 1; $unit <= $line->quantity; ++$unit) {
                    $draws[] = $this->draws->get(LootDraw::idOf($order->number, $line->reference->value(), $option, $unit)) ?? $this->commitUnit($order, $line, $option, $unit, $drawer);
                }
            }
        }

        return $draws;
    }

    /**
     * Commits one unit now.
     *
     * @throws LootDrawRefused when a text option has no list a die draws from
     */
    public function commitUnit(Order $order, OrderLine $line, string $option, int $unit, string $drawer): LootDraw
    {
        $reference = $line->reference->value();
        $texts = $this->drawers->isText($drawer) ? $this->texts($order, $line, $option) : null;
        $draw = LootDraw::commit($order->number, $reference, $option, $unit, $drawer, $this->seeds->open(), $texts, $this->clock->now());
        $this->draws->save($draw);

        return $draw;
    }

    /**
     * @return list<string>
     */
    private function texts(Order $order, OrderLine $line, string $option): array
    {
        $texts = $this->options->optionsOf($line->reference, $order->currency)->option($option)?->text;
        $entries = $texts instanceof TextConstraints ? $texts->entries : [];

        try {
            $this->drawers->boxOf(Drawers::TEXT, $entries);
        } catch (InvalidLoot $invalidLoot) {
            throw LootDrawRefused::noEntries($line->reference->value(), $option, $invalidLoot->getMessage());
        }

        return $entries;
    }
}
