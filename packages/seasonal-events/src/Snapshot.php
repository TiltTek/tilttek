<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What is on at one moment: the seasons in their time, the events running, and what they bring — each reference once,
 * those of the seasons first, then those of the events, in the order of the catalogue.
 */
final readonly class Snapshot
{
    /**
     * @param list<ActiveSeason> $seasons
     * @param list<ActiveEvent>  $events
     */
    public function __construct(
        public DateTimeImmutable $moment,
        public array $seasons,
        public array $events,
    ) {
    }

    public function isQuiet(): bool
    {
        return [] === $this->seasons;
    }

    public function isOn(string $season): bool
    {
        return array_any($this->seasons, static fn (ActiveSeason $active): bool => $active->season->id === $season);
    }

    /**
     * The references of one kind the seasons and events on bring, each once.
     *
     * @return list<ResourceReference>
     */
    public function contents(ContentKind $kind): array
    {
        $references = [];

        foreach ([...array_map(static fn (ActiveSeason $active): array => $active->season->contents, $this->seasons), ...array_map(static fn (ActiveEvent $active): array => $active->event->contents, $this->events)] as $contents) {
            foreach ($contents as $content) {
                if ($kind === $content->kind) {
                    $references[$content->reference->toString()] ??= $content->reference;
                }
            }
        }

        return array_values($references);
    }

    /**
     * @return list<ResourceReference>
     */
    public function quests(): array
    {
        return $this->contents(ContentKind::Quest);
    }

    /**
     * @return list<ResourceReference>
     */
    public function items(): array
    {
        return $this->contents(ContentKind::Item);
    }

    /**
     * @return list<ResourceReference>
     */
    public function decorations(): array
    {
        return $this->contents(ContentKind::Decoration);
    }

    /**
     * The multiplier of a ledger now: the highest of the events running, since multipliers never add up; null when no
     * event running multiplies it.
     */
    public function multiplier(string $ledger): ?Multiplier
    {
        $highest = null;

        foreach ($this->events as $active) {
            $multiplier = $active->event->multiplier;

            if ($multiplier instanceof Multiplier && $multiplier->ledger === $ledger && (!$highest instanceof Multiplier || $multiplier->percent > $highest->percent)) {
                $highest = $multiplier;
            }
        }

        return $highest;
    }

    /**
     * @return array{moment: string, seasons: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'moment' => $this->moment->format(\DATE_ATOM),
            'seasons' => array_map(static fn (ActiveSeason $active): array => $active->toArray(), $this->seasons),
            'events' => array_map(static fn (ActiveEvent $active): array => $active->toArray(), $this->events),
        ];
    }
}
