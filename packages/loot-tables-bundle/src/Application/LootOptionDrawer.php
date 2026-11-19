<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OptionDrawer;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Psr\Clock\ClockInterface;
use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * Draws the boxes and the texts of a paid order, as the commerce core asks once its payments are `Paid`: each unit is
 * revealed from the seed committed when the order was placed, once; asked again, it gives the same draw.
 *
 * What the order line keeps is the `DrawText` of a box — its faces and the names of its entries — or the text drawn.
 * Each unit rolls from its seed alone: the client seed the library mixes in is the identifier of the draw, public and
 * fixed before the seed was drawn, so nothing is asked of the buyer's browser. A unit never committed is refused,
 * unless `require_commitment` is off: it is then committed and revealed at once, still verifiable, but its commitment
 * was never shown before the payment.
 */
final readonly class LootOptionDrawer implements OptionDrawer
{
    public function __construct(
        private Drawers $drawers,
        private LootCommitments $commitments,
        private LootDrawRepository $draws,
        private LootDrawer $drawer,
        private ClockInterface $clock,
        private bool $requireCommitment = true,
    ) {
    }

    #[Override]
    public function supports(string $drawer): bool
    {
        return $this->drawers->supports($drawer);
    }

    /**
     * @throws LootDrawRefused when the unit was never committed and a commitment is required
     */
    #[Override]
    public function draw(string $drawer, Order $order, OrderLine $line, string $option, int $unit): string
    {
        $reference = $line->reference->value();
        $draw = $this->draws->get(LootDraw::idOf($order->number, $reference, $option, $unit));

        if (!$draw instanceof LootDraw) {
            $draw = $this->requireCommitment
                ? throw LootDrawRefused::notCommitted($order->number, $reference, $option, $unit) : $this->commitments->commitUnit($order, $line, $option, $unit, $drawer);
        }

        $drawn = $draw->drawn();

        if (null !== $drawn) {
            return $drawn;
        }

        $revealed = $this->drawer->reveal($this->drawers->boxOf($draw->drawer(), $draw->texts()), $draw->pending(), $draw->clientSeedOfReveal());
        $drawn = $this->drawers->isText($draw->drawer()) ? $revealed->result->entries()[0]->name : DrawText::write($revealed->result);
        $draw->reveal($revealed, $drawn, $this->clock->now());
        $this->draws->save($draw);

        return $drawn;
    }
}
