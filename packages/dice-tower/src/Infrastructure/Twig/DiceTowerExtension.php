<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Twig;

use Phil\Resource\ResourceReference;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\DiceTower\Application\Contract\TableAccess;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;
use Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The tower in templates:
 *
 * - `dice_tower(table, names, title, announce)` draws a table's tower for a viewer the application's `TableAccess`
 *   allows, and nothing for another: its last entries, and the Stimulus controller that adds each new one from the hub
 *   and reads what was missed from the log when the connection comes back; with `announce` false, screen readers are
 *   left to an accessible dice interface on the same page;
 * - `dice_tower_topic(table)` gives the topic of a table's tower;
 * - `dice_tower_kit(component)` names a component of the UX kit the overlay draws with.
 *
 * The names are those of the rollers the page may show, by reference: they reach the viewer's page only, never the
 * hub.
 */
final readonly class DiceTowerExtension
{
    public const string TEMPLATE = '@TilttekDiceTower/overlay.html.twig';

    public function __construct(
        private DiceTower $tower,
        private TableAccess $access,
        private TowerTopics $topics,
        private TowerSubscriptions $subscriptions,
        private TowerView $view,
        private KitComponents $kit = new KitComponents(),
        private ?UrlGeneratorInterface $urls = null,
    ) {
    }

    /**
     * @param array<string, string> $names
     */
    #[AsTwigFunction('dice_tower', needsEnvironment: true, isSafe: ['html'])]
    public function tower(Environment $twig, ResourceReference|string $table, array $names = [], ?string $title = null, bool $announce = true): string
    {
        $view = $this->view($table, $names);

        return null === $view ? '' : $twig->render(self::TEMPLATE, ['tower' => $view, 'title' => $title, 'announce' => $announce]);
    }

    /**
     * What the overlay draws, or null for a viewer who may not watch the table.
     *
     * @param array<string, string> $names
     *
     * @return ?array{table: string, hub: ?string, entries_url: ?string, last: int, faces: array<string, array<string, string>>, names: array<string, string>, entries: list<array{position: int, roller: ?string, name: ?string, results: list<array{expression: string, total: ?int, text: ?string, dice: list<array{label: string, kept: bool}>}>}>}
     */
    public function view(ResourceReference|string $table, array $names = []): ?array
    {
        $reference = $table instanceof ResourceReference ? $table : ResourceReference::fromString($table);

        if (!$this->access->canWatch($reference)) {
            return null;
        }

        $entries = $this->tower->entries($reference);
        $last = [] === $entries ? 0 : $entries[\count($entries) - 1]->position;

        return [
            'table' => $reference->toString(),
            'hub' => $this->subscriptions->subscribe($reference),
            'entries_url' => $this->entriesUrl($reference),
            'last' => $last,
            'faces' => $this->view->faces(),
            'names' => $names,
            'entries' => array_map(fn (TowerEntry $entry): array => $this->view->entry($entry, $names), $entries),
        ];
    }

    #[AsTwigFunction('dice_tower_topic')]
    public function topic(ResourceReference|string $table): string
    {
        return $this->topics->topic($table instanceof ResourceReference ? $table : ResourceReference::fromString($table));
    }

    #[AsTwigFunction('dice_tower_kit')]
    public function kit(string $component): string
    {
        return $this->kit->name($component);
    }

    private function entriesUrl(ResourceReference $table): ?string
    {
        try {
            return $this->urls?->generate(TowerEntriesController::ROUTE, ['table' => $table->toString()]);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
