<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Fixture;

use DateTimeZone;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Daily\Daily;
use Tilttek\Collections\Daily\DailyCalendar;
use Tilttek\Collections\Daily\FixedDate;
use Tilttek\Collections\Daily\InMemoryDailyStore;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Dice\InMemoryChoiceStore;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\MemberData;
use Tilttek\Collections\Pack\InMemoryOpeningStore;
use Tilttek\Collections\Pack\Packs;
use Tilttek\Collections\Pack\SecureSeeds;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;
use Tilttek\CollectionsBundle\Infrastructure\Event\DispatchingObserver;

/**
 * The library's services wired in memory over a small catalog, with an event dispatcher that writes down what it is
 * given: what the bundle's adapters need in a unit test.
 */
final readonly class Kit
{
    public Clock $clock;

    public HeardEvents $events;

    public InMemoryCopyStore $copies;

    public InMemoryDailyStore $picks;

    public Collections $collections;

    public Packs $packs;

    public DiceShelf $dice;

    public DailyCalendar $calendar;

    public Daily $daily;

    public MemberData $data;

    public function __construct(
        public CatalogProvider $catalog,
    ) {
        $this->clock = new Clock();
        $this->events = new HeardEvents();
        $this->copies = new InMemoryCopyStore();
        $this->picks = new InMemoryDailyStore();
        $completions = new InMemoryCompletionStore();
        $openings = new InMemoryOpeningStore();
        $choices = new InMemoryChoiceStore();
        $this->collections = new Collections($catalog, $this->copies, $completions, $this->clock, new DispatchingObserver($this->events));
        $this->packs = new Packs($this->collections, $openings, new SecureSeeds(), $this->clock);
        $this->dice = new DiceShelf($this->collections, $choices);
        $this->calendar = new DailyCalendar($catalog, $this->picks, new DateTimeZone('UTC'), 'salt', [new FixedDate('11-07', 'pirate')]);
        $this->daily = new Daily($this->calendar, $this->collections, $this->clock);
        $this->data = new MemberData($this->copies, $completions, $openings, $choices);
    }

    public static function standard(): self
    {
        return new self(FixedCatalog::fromArray(self::definition()));
    }

    /**
     * @return array{rarities: array<string, mixed>, items: array<string, array<string, mixed>>, sets: array<string, array<string, mixed>>, packs: array<string, array<string, mixed>>}
     */
    public static function definition(): array
    {
        return [
            'rarities' => ['common' => 3, 'rare' => 1],
            'items' => [
                'pirate' => ['name' => ['en' => 'Pirate', 'fr' => 'Corsaire'], 'rarity' => 'rare', 'sets' => ['crew'], 'image' => '/img/pirate.webp', 'description' => 'Sails.'],
                'deckhand' => ['name' => 'Deckhand', 'rarity' => 'common', 'sets' => ['crew']],
                'ember-d6' => ['name' => ['en' => 'Ember d6', 'fr' => 'd6 de braise'], 'rarity' => 'common', 'sets' => ['ember'], 'image' => '/img/ember-d6.webp', 'die' => ['sides' => 6, 'color' => '#c0392b', 'material' => 'resin']],
                'plain-d20' => ['name' => 'Plain d20', 'rarity' => 'common', 'die' => ['sides' => 20]],
            ],
            'sets' => [
                'crew' => ['name' => ['en' => 'The crew', 'fr' => "L'équipage"], 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]],
                'ember' => ['name' => 'Ember dice', 'kind' => 'base'],
            ],
            'packs' => ['booster' => ['name' => 'Booster', 'size' => 2, 'weights' => ['common' => 3, 'rare' => 1]]],
        ];
    }
}
