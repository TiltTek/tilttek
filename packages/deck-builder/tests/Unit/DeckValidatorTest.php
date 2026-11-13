<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Catalog\InMemoryCardCatalog;
use Tilttek\DeckBuilder\Catalog\InMemoryFormatCatalog;
use Tilttek\DeckBuilder\Catalog\OwnedCardList;
use Tilttek\DeckBuilder\Contract\OwnedCards;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\DeckValidator;
use Tilttek\DeckBuilder\Exception\UnknownFormat;
use Tilttek\DeckBuilder\Format;
use Tilttek\DeckBuilder\TabooList;
use Tilttek\DeckBuilder\Validation;
use Tilttek\DeckBuilder\Violation;
use Tilttek\DeckBuilder\ViolationType;

/**
 * The cards and formats are invented for the tests.
 */
#[CoversClass(DeckValidator::class)]
#[CoversClass(Validation::class)]
#[CoversClass(Violation::class)]
#[CoversClass(ViolationType::class)]
#[CoversClass(UnknownFormat::class)]
final class DeckValidatorTest extends TestCase
{
    private DeckValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DeckValidator(
            new InMemoryCardCatalog(
                new Card('fox', 'Ember Fox'),
                new Card('owl', 'Night Owl'),
                new Card('den', 'Fox Den', 1),
                new Card('path', 'Forest Path', 20),
                new Card('storm', 'Storm Call'),
                new Card('relic', 'Old Relic'),
            ),
            new InMemoryFormatCatalog(
                new Format('league', 'League', 10, 12, 3, ['relic'], ['storm' => 1], [
                    new TabooList('autumn', new DateTimeImmutable('2026-10-01'), ['owl'], ['fox' => 2]),
                ]),
                new Format('free', 'Free play', 0),
            ),
        );
    }

    public function testADeckWithinTheRulesIsValid(): void
    {
        $deck = new Deck('d', 'D', 'league', ['fox' => 3, 'owl' => 3, 'den' => 1, 'path' => 3]);
        $validation = $this->validator->validate($deck, new DateTimeImmutable('2026-09-30'));

        self::assertTrue($validation->isValid());
        self::assertSame([], $validation->messages());
        self::assertSame($deck, $validation->deck);
        self::assertNull($validation->ruling->tabooList);
        self::assertSame('2026-09-30', $validation->on->format('Y-m-d'));
    }

    public function testEveryViolationIsReturnedInTheDecksOrder(): void
    {
        $deck = new Deck('d', 'D', 'league', ['fox' => 3, 'owl' => 1, 'ghost' => 2, 'den' => 2, 'relic' => 1, 'storm' => 2, 'path' => 21]);
        $validation = $this->validator->validate($deck, new DateTimeImmutable('2026-10-01'));

        self::assertFalse($validation->isValid());
        self::assertSame('autumn', $validation->ruling->tabooList?->code);
        self::assertSame([
            'The deck has 32 cards; the format "league" allows at most 12.',
            'The deck has 3 copies of "Ember Fox" (fox); the taboo list "autumn" from 2026-10-01 restricts it to 2.',
            '"Night Owl" (owl) is banned by the taboo list "autumn" from 2026-10-01.',
            'The card "ghost" is not in the card catalog.',
            'The deck has 2 copies of "Fox Den" (den); at most 1 are allowed.',
            '"Old Relic" (relic) is banned by the format "league".',
            'The deck has 2 copies of "Storm Call" (storm); the format "league" restricts it to 1.',
            'The deck has 21 copies of "Forest Path" (path); at most 20 are allowed.',
        ], $validation->messages());
        self::assertSame([
            ViolationType::TooManyCards,
            ViolationType::Restricted,
            ViolationType::Banned,
            ViolationType::UnknownCard,
            ViolationType::TooManyCopies,
            ViolationType::Banned,
            ViolationType::Restricted,
            ViolationType::TooManyCopies,
        ], array_map(static fn (Violation $violation): ViolationType => $violation->type, $validation->violations));
        self::assertSame(['owl', 'relic'], array_map(static fn (Violation $violation): ?string => $violation->card, $validation->ofType(ViolationType::Banned)));
        self::assertSame([], $validation->ofType(ViolationType::NotOwned));
    }

    public function testTheViolationsCarryTheirValues(): void
    {
        $deck = new Deck('d', 'D', 'league', ['fox' => 3, 'owl' => 1, 'ghost' => 2, 'den' => 2]);
        $violations = $this->validator->validate($deck, new DateTimeImmutable('2026-10-01'))->violations;

        self::assertSame(['type' => 'too-few-cards', 'message' => 'The deck has 8 cards; the format "league" needs at least 10.', 'card' => null, 'values' => ['size' => 8, 'limit' => 10]], $violations[0]->toArray());
        self::assertSame(['card' => 'fox', 'quantity' => 3, 'limit' => 2, 'by' => 'the taboo list "autumn" from 2026-10-01'], $violations[1]->values);
        self::assertSame(['card' => 'owl', 'quantity' => 1, 'by' => 'the taboo list "autumn" from 2026-10-01'], $violations[2]->values);
        self::assertSame(['card' => 'ghost', 'quantity' => 2], $violations[3]->values);
        self::assertSame('ghost', $violations[3]->card);
        self::assertSame(['card' => 'den', 'quantity' => 2, 'limit' => 1], $violations[4]->values);
        self::assertSame('den', $violations[4]->card);
    }

    public function testADeckOfTooManyCardsCarriesItsSizeAndItsLimit(): void
    {
        $violations = $this->validator->validate(new Deck('d', 'D', 'league', ['path' => 13]), new DateTimeImmutable('2026-01-01'))->violations;

        self::assertSame(['type' => 'too-many-cards', 'message' => 'The deck has 13 cards; the format "league" allows at most 12.', 'card' => null, 'values' => ['size' => 13, 'limit' => 12]], $violations[0]->toArray());
    }

    public function testACardOfDigitsIsLookedUpAsAString(): void
    {
        $validator = new DeckValidator(new InMemoryCardCatalog(new Card('7', 'Seven')), new InMemoryFormatCatalog(new Format('free', 'Free play', 0)));

        self::assertTrue($validator->validate(new Deck('d', 'D', 'free', ['7' => 2]), new DateTimeImmutable('2026-01-01'))->isValid());
    }

    public function testTheSizesAreBounds(): void
    {
        $at = new DateTimeImmutable('2026-01-01');

        self::assertSame(['The deck has 9 cards; the format "league" needs at least 10.'], $this->validator->validate(new Deck('d', 'D', 'league', ['path' => 9]), $at)->messages());
        self::assertTrue($this->validator->validate(new Deck('d', 'D', 'league', ['path' => 10]), $at)->isValid());
        self::assertTrue($this->validator->validate(new Deck('d', 'D', 'league', ['path' => 12]), $at)->isValid());
        self::assertSame(['The deck has 13 cards; the format "league" allows at most 12.'], $this->validator->validate(new Deck('d', 'D', 'league', ['path' => 13]), $at)->messages());
        self::assertSame(['The deck has 1 card; the format "league" needs at least 10.'], $this->validator->validate(new Deck('d', 'D', 'league', ['path' => 1]), $at)->messages());
        self::assertSame(['The deck has 0 cards; the format "league" needs at least 10.'], $this->validator->validate(new Deck('d', 'D', 'league'), $at)->messages());
        self::assertTrue($this->validator->validate(new Deck('d', 'D', 'free'), $at)->isValid());
        self::assertTrue($this->validator->validate(new Deck('d', 'D', 'free', ['fox' => 999]), $at)->isValid());
    }

    public function testADeckIsCheckedAgainstACollection(): void
    {
        $deck = new Deck('d', 'D', 'league', ['fox' => 3, 'owl' => 1, 'den' => 1, 'path' => 5, 'ghost' => 1]);
        $validation = $this->validator->validate($deck, new DateTimeImmutable('2026-01-01'), new OwnedCardList(['fox' => 2, 'den' => 1, 'path' => 9]));

        self::assertSame([
            'The deck has 3 copies of "Ember Fox" (fox); the collection owns 2.',
            'The deck has 1 copy of "Night Owl" (owl); the collection owns 0.',
            'The card "ghost" is not in the card catalog.',
        ], $validation->messages());
        self::assertSame(['card' => 'fox', 'quantity' => 3, 'owned' => 2], $validation->ofType(ViolationType::NotOwned)[0]->values);
        self::assertSame('fox', $validation->ofType(ViolationType::NotOwned)[0]->card);
    }

    public function testANegativeCountFromACollectionCountsAsNone(): void
    {
        $owned = new class implements OwnedCards {
            public function copiesOf(string $card): int
            {
                return -3;
            }
        };

        $validation = $this->validator->validate(new Deck('d', 'D', 'free', ['fox' => 1]), new DateTimeImmutable(), $owned);

        self::assertSame(['The deck has 1 copy of "Ember Fox" (fox); the collection owns 0.'], $validation->messages());
    }

    public function testAnUnknownFormatIsAnError(): void
    {
        $this->expectException(UnknownFormat::class);
        $this->expectExceptionMessageIsOrContains('The format "nope" is not in the format catalog.');

        $this->validator->validate(new Deck('d', 'D', 'nope'), new DateTimeImmutable());
    }
}
