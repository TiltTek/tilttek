<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Psr\Clock\ClockInterface;
use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * Enters the physical dice a buyer rolled in front of a cashier, at a convention: one shop code per unit of the line,
 * read against the box its option draws, recorded on the order line as the draws and kept as draws of physical dice.
 * A unit committed and not revealed takes the dice in place of its seed. The application stores the order after.
 * `toEnter()` gives the box and the number of units the form of the dice, `PhysicalDiceType`, asks for.
 */
final readonly class PhysicalDice
{
    public function __construct(
        private Drawers $drawers,
        private LootDrawRepository $draws,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The box the option of the line draws and the number of its units: what the form of the dice asks for, one code
     * per unit, before anything is entered.
     *
     * @return array{box: LootBox, units: int}
     *
     * @throws LootDrawRefused when the line is not the order's or its option is not a box left to draw
     */
    public function toEnter(Order $order, SellableReference $reference, string $option): array
    {
        [$line, $drawer] = $this->line($order, $reference, $option);

        return ['box' => $this->drawers->boxOf($drawer, null), 'units' => $line->quantity];
    }

    /**
     * @param list<string> $codes one shop code per unit of the line, in the order of the units
     *
     * @throws LootDrawRefused when the line is not the order's, its option is not a box left to draw, or the codes are
     *                         not one per unit
     * @throws InvalidCode     when a code does not read as the dice of the box
     */
    public function enter(Order $order, SellableReference $reference, string $option, array $codes): void
    {
        [$line, $drawer] = $this->line($order, $reference, $option);

        if (\count($codes) !== $line->quantity) {
            throw LootDrawRefused::unitCount($reference->value(), $line->quantity, \count($codes));
        }

        $box = $this->drawers->boxOf($drawer, null);
        $results = array_map(static fn (string $code): LootResult => ShopCode::read($box, $code), $codes);
        $now = $this->clock->now();
        $drawn = [];

        foreach ($results as $index => $result) {
            $text = DrawText::write($result);
            $draw = $this->draws->get(LootDraw::idOf($order->number, $reference->value(), $option, $index + 1));

            if ($draw instanceof LootDraw) {
                $draw->enter($result, $text, $now);
            } else {
                $draw = LootDraw::physical($order->number, $reference->value(), $option, $index + 1, $drawer, $result, $text, $now);
            }

            $this->draws->save($draw);
            $drawn[] = $text;
        }

        $order->recordDraws($reference, $option, $drawn);
    }

    /**
     * The line and the drawer of its option, a box left to draw here.
     *
     * @return array{OrderLine, string}
     *
     * @throws LootDrawRefused when the line is not the order's or its option is not a box left to draw
     */
    private function line(Order $order, SellableReference $reference, string $option): array
    {
        $line = $order->line($reference) ?? throw LootDrawRefused::unknownLine($order->number, $reference->value());
        $value = $line->configuration->value($option);
        $drawer = $value->drawer ?? '';

        if (true !== $value?->isPending() || $this->drawers->isText($drawer) || !$this->drawers->supports($drawer)) {
            throw LootDrawRefused::notToDraw($reference->value(), $option);
        }

        return [$line, $drawer];
    }
}
