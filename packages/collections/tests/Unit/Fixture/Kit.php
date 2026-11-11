<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Daily\Daily;
use Tilttek\Collections\Daily\DailyCalendar;
use Tilttek\Collections\Daily\DailyProjection;
use Tilttek\Collections\Daily\DateRule;
use Tilttek\Collections\Daily\InMemoryDailyStore;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Dice\InMemoryChoiceStore;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\MemberData;
use Tilttek\Collections\Pack\InMemoryOpeningStore;
use Tilttek\Collections\Pack\Packs;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;

/**
 * Everything a test of the collections needs, wired together in memory: the journal gives the item of the day as
 * activities are recorded, at the moment the clock says.
 */
final readonly class Kit
{
    public Clock $clock;

    public Recorder $recorder;

    public InMemoryCopyStore $copies;

    public InMemoryCompletionStore $completions;

    public InMemoryOpeningStore $openings;

    public InMemoryChoiceStore $choices;

    public InMemoryDailyStore $picks;

    public Collections $collections;

    public Packs $packs;

    public DiceShelf $dice;

    public DailyCalendar $calendar;

    public Daily $daily;

    public DailyProjection $projection;

    public MemberData $data;

    public Projector $projector;

    public Journal $journal;

    /**
     * @param list<DateRule> $rules
     */
    public function __construct(
        public CatalogProvider $catalog,
        array $rules = [],
        string $now = '2026-11-07 12:00:00 UTC',
        FixedSeeds $seeds = new FixedSeeds(),
    ) {
        $this->clock = new Clock($now);
        $this->recorder = new Recorder();
        $this->copies = new InMemoryCopyStore();
        $this->completions = new InMemoryCompletionStore();
        $this->openings = new InMemoryOpeningStore();
        $this->choices = new InMemoryChoiceStore();
        $this->picks = new InMemoryDailyStore();
        $this->collections = new Collections($catalog, $this->copies, $this->completions, $this->clock, $this->recorder);
        $this->packs = new Packs($this->collections, $this->openings, $seeds, $this->clock);
        $this->dice = new DiceShelf($this->collections, $this->choices);
        $this->calendar = new DailyCalendar($catalog, $this->picks, new DateTimeZone('America/Toronto'), 'salt', $rules, ['crew']);
        $this->daily = new Daily($this->calendar, $this->collections, $this->clock);
        $this->projection = new DailyProjection($this->daily, ['page.viewed', 'member.signed_in']);
        $this->data = new MemberData($this->copies, $this->completions, $this->openings, $this->choices);
        $log = new InMemoryActivityLog();
        $this->projector = new Projector($log, new InMemoryCheckpoints());
        $this->journal = new Journal($log, $this->clock, observers: [new LiveProjections($this->projector, new Projections([$this->projection]))]);
    }

    /**
     * A kit over the catalog every test shares.
     *
     * @param list<DateRule> $rules
     */
    public static function standard(array $rules = [], FixedSeeds $seeds = new FixedSeeds()): self
    {
        return new self(FixedCatalog::fromArray(self::definition()), $rules, seeds: $seeds);
    }

    /**
     * Three rarities; a crew of three to collect, worth points and a badge; a base set of two dice, an earned set of
     * one; a bound item; a booster of three from every item and a crew pack weighted its own way.
     *
     * @return array{rarities: array<string, mixed>, items: array<string, array<string, mixed>>, sets: array<string, array<string, mixed>>, packs: array<string, array<string, mixed>>}
     */
    public static function definition(): array
    {
        return [
            'rarities' => ['common' => 70, 'rare' => ['weight' => 25], 'legendary' => 5],
            'items' => [
                'deckhand' => ['name' => ['en' => 'Deckhand', 'fr' => 'Matelot'], 'rarity' => 'common', 'sets' => ['crew'], 'image' => '/img/deckhand.webp'],
                'pirate' => ['name' => 'Pirate', 'rarity' => 'rare', 'sets' => ['crew']],
                'captain' => ['name' => 'Captain', 'rarity' => 'legendary', 'sets' => ['crew'], 'description' => ['en' => 'Rules the deck.']],
                'ember-d6' => ['name' => 'Ember d6', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 6, 'color' => '#c0392b', 'material' => 'resin']],
                'ember-d20' => ['name' => 'Ember d20', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 20]],
                'frost-d6' => ['name' => 'Frost d6', 'rarity' => 'rare', 'sets' => ['frost'], 'die' => ['sides' => 6, 'color' => '#5dade2']],
                'medal' => ['name' => 'Medal', 'rarity' => 'rare', 'tradable' => false],
            ],
            'sets' => [
                'crew' => ['name' => 'The crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50], ['kind' => 'badge', 'code' => 'full-crew']]],
                'ember' => ['name' => 'Ember dice', 'kind' => 'base'],
                'frost' => ['name' => 'Frost dice', 'kind' => 'earned'],
            ],
            'packs' => [
                'booster' => ['name' => 'Booster', 'size' => 3],
                'crew-pack' => ['name' => 'Crew pack', 'size' => 2, 'sets' => ['crew'], 'weights' => ['common' => 1, 'rare' => 1]],
            ],
        ];
    }

    public function record(string $key, string $member, string $verb, string $at): void
    {
        $this->clock->set($at);
        $this->journal->record(Activity::of($key, $member, $verb, new DateTimeImmutable($at)));
    }

    public function cancel(string $key, string $at): void
    {
        $this->clock->set($at);
        $this->journal->cancel($key);
    }

    public static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
