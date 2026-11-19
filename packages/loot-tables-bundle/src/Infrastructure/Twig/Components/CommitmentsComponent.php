<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Twig\Components;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Twig\KitComponents;

/**
 * The draws of an order on the buyer's page, `<twig:TilttekLootTables:Commitments order="1042" />`, drawn with the
 * recipes `Card` and `Badge` of the UX kit and without JavaScript: the commitment of each draw, made when the order was
 * placed; once revealed, its seed, the entries drawn, the shop code and whether it checks out against the commitment.
 *
 * It only reads: the draws are committed when the order is placed, never by showing them. The page that renders it
 * decides who may see the order.
 */
#[AsTwigComponent(name: 'TilttekLootTables:Commitments', template: '@TilttekLootTables/components/Commitments.html.twig', exposePublicProps: false)]
final class CommitmentsComponent
{
    /** The number of the order. */
    public string $order = '';

    /** @var ?list<LootDraw> */
    private ?array $draws = null;

    /**
     * What each line and option of the order is called, by line and option.
     *
     * @var array<string, array{line: string, option: string}>
     */
    private array $names = [];

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly LootResults $results,
        private readonly KitComponents $kit = new KitComponents(),
    ) {
    }

    /**
     * The draws of the order; none for an order that does not exist.
     *
     * @return list<LootDraw>
     */
    public function draws(): array
    {
        if (null !== $this->draws) {
            return $this->draws;
        }

        $order = '' === $this->order ? null : $this->orders->find($this->order);

        if (!$order instanceof Order) {
            return $this->draws = [];
        }

        foreach ($order->lines() as $line) {
            foreach ($line->configuration->values as $option => $value) {
                $this->names[$line->reference->value().' '.$option] = ['line' => $line->description, 'option' => $value->label ?? $option];
            }
        }

        return $this->draws = $this->results->ofOrder($order->number);
    }

    /**
     * What the line and the option of a draw are called: the description of the line and the label of the option.
     *
     * @return array{line: string, option: string}
     */
    public function name(LootDraw $draw): array
    {
        $this->draws();

        return $this->names[$draw->line().' '.$draw->option()] ?? ['line' => $draw->line(), 'option' => $draw->option()];
    }

    public function result(LootDraw $draw): ?LootResult
    {
        return $this->results->result($draw);
    }

    public function shopCode(LootDraw $draw): ?string
    {
        return $this->results->shopCode($draw);
    }

    public function verified(LootDraw $draw): bool
    {
        return $this->results->verify($draw);
    }

    /**
     * The name of a recipe of the UX kit, as the application installed it.
     */
    public function kit(string $component): string
    {
        return $this->kit->name($component);
    }
}
