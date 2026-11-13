<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Benchmark;

use DateTimeImmutable;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Catalog\InMemoryCardCatalog;
use Tilttek\DeckBuilder\Catalog\InMemoryFormatCatalog;
use Tilttek\DeckBuilder\Catalog\OwnedCardList;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\DeckDiff;
use Tilttek\DeckBuilder\DeckValidator;
use Tilttek\DeckBuilder\Format;
use Tilttek\DeckBuilder\TabooList;
use Tilttek\DeckBuilder\Validation;

/**
 * What a deck costs on a page: the largest deck — 1 000 distinct cards — validated against a format of 10 000 banned
 * and 10 000 restricted cards and 100 taboo lists, with a collection; its differences with another version; and its
 * reading from JSON.
 */
final class DeckBuilderBench
{
    private readonly DeckValidator $validator;

    private readonly Deck $deck;

    private readonly Deck $other;

    private readonly OwnedCardList $owned;

    private readonly string $json;

    private readonly DateTimeImmutable $on;

    /** The last validation, kept so that the measured call has an effect. */
    public ?Validation $validation = null;

    public function __construct()
    {
        $ids = array_map(static fn (int $card): string => 'card-'.$card, range(1, 1000));
        $cards = array_map(static fn (string $id): Card => new Card($id, 'Card '.$id), $ids);
        $banned = array_map(static fn (int $card): string => 'banned-'.$card, range(1, 10_000));
        $restricted = array_fill_keys(array_map(static fn (int $card): string => 'restricted-'.$card, range(1, 10_000)), 1);
        $lists = array_map(static fn (int $day): TabooList => new TabooList('t'.$day, new DateTimeImmutable('2020-01-01')->modify('+'.$day.' days'), ['card-'.$day]), range(1, 100));

        $this->validator = new DeckValidator(new InMemoryCardCatalog(...$cards), new InMemoryFormatCatalog(new Format('bench', 'Bench', 40, 5000, 3, $banned, $restricted, $lists)));
        $this->deck = new Deck('d', 'Bench', 'bench', array_fill_keys($ids, 3));
        $this->other = $this->deck->withQuantity('card-1', 1)->withoutCard('card-2', 3)->withCard('card-new', 2);
        $this->owned = new OwnedCardList(array_fill_keys($ids, 2));
        $this->json = $this->deck->toJson();
        $this->on = new DateTimeImmutable('2026-11-13');
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchValidate1000CardsWithACollection(): void
    {
        $this->validation = $this->validator->validate($this->deck, $this->on, $this->owned);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchDiffTwoVersionsOf1000Cards(): void
    {
        DeckDiff::between($this->deck, $this->other);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchReadADeckOf1000Cards(): void
    {
        Deck::fromJson($this->json);
    }
}
