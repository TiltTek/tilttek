<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * What production, a receipt or a buyer reads of the draws of an order: the draws, the entries of each — with their
 * images and their slots —, the shop code, whether a verifiable draw checks out, and the draw rolled again from the
 * seed it revealed.
 */
final readonly class LootResults
{
    public function __construct(
        private Drawers $drawers,
        private LootDrawRepository $draws,
        private LootDrawer $drawer,
    ) {
    }

    /**
     * @return list<LootDraw>
     */
    public function ofOrder(string $orderNumber): array
    {
        return $this->draws->ofOrder($orderNumber);
    }

    /**
     * The draw of this identifier, or `null` when there is none.
     */
    public function draw(string $id): ?LootDraw
    {
        return $this->draws->get($id);
    }

    /**
     * The result of a draw revealed or entered, or `null` while it is not.
     */
    public function result(LootDraw $draw): ?LootResult
    {
        return $draw->result($this->drawers->boxOf($draw->drawer(), $draw->texts()));
    }

    /**
     * The shop code of a draw, or `null` while it is not revealed or when its box has no code.
     */
    public function shopCode(LootDraw $draw): ?string
    {
        $result = $this->result($draw);

        return $result instanceof LootResult && ShopCode::supports($result->box) ? ShopCode::write($result) : null;
    }

    /**
     * Whether the draw is verifiable and its proof checks out against its box and its commitment.
     */
    public function verify(LootDraw $draw): bool
    {
        $proof = $draw->proof();

        return null !== $proof && ($proof['commitment'] ?? null) === $draw->commitment() && $this->drawer->verify($this->drawers->boxOf($draw->drawer(), $draw->texts()), $proof);
    }

    /**
     * The draw rolled again from the seed it revealed and its identifier, as anyone holding its proof can: the result
     * should be the one kept. `null` while the seed is secret, and for physical dice.
     */
    public function replay(LootDraw $draw): ?LootResult
    {
        $seed = $draw->revealedSeed();

        return null === $seed ? null : $this->drawer->reveal($this->drawers->boxOf($draw->drawer(), $draw->texts()), PendingDraw::restore($seed), $draw->clientSeedOfReveal())->result;
    }
}
