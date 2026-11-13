<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Contract\DeckStore;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\Decks;
use Tilttek\DeckBuilder\DeckVersion;
use Tilttek\DeckBuilder\Exception\DeckConflict;
use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Exception\UnknownDeck;
use Tilttek\DeckBuilder\Store\InMemoryDeckStore;

#[CoversClass(Decks::class)]
#[CoversClass(DeckVersion::class)]
#[CoversClass(DeckConflict::class)]
#[CoversClass(UnknownDeck::class)]
final class DecksTest extends TestCase
{
    private Decks $decks;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->decks = new Decks(new InMemoryDeckStore());
        $this->at = new DateTimeImmutable('2026-11-13 10:00', new DateTimeZone('America/Toronto'));
    }

    public function testEachChangeIsSavedAsTheNextVersion(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 1]);

        $first = $this->decks->save($deck, $this->at, 'first draft');
        $second = $this->decks->save($deck->withCard('b'), $this->at->modify('+1 day'));

        self::assertSame(1, $first->version);
        self::assertSame('first draft', $first->note);
        self::assertSame('2026-11-13T15:00:00+00:00', $first->savedAt->format('c'));
        self::assertSame(2, $second->version);
        self::assertNull($second->note);
        self::assertSame($second, $this->decks->latest('d'));
        self::assertSame($first, $this->decks->version('d', 1));
        self::assertSame([$first, $second], $this->decks->history('d'));
        self::assertSame(['+1 b'], $this->decks->diff('d', 1, 2)->lines());
        self::assertSame(['-1 b'], $this->decks->diff('d', 2, 1)->lines());
    }

    public function testAnUnchangedDeckKeepsItsLatestVersion(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 1]);
        $first = $this->decks->save($deck, $this->at);

        self::assertSame($first, $this->decks->save(new Deck('d', 'D', 'f', ['a' => 1]), $this->at->modify('+1 hour'), 'again'));
        self::assertCount(1, $this->decks->history('d'));
    }

    public function testAVersionSavedByAnotherRequestFirstIsAConflict(): void
    {
        $store = new class implements DeckStore {
            /** @var list<DeckVersion> */
            public array $refused = [];

            public function add(DeckVersion $version): bool
            {
                $this->refused[] = $version;

                return false;
            }

            public function version(string $deck, int $version): ?DeckVersion
            {
                return null;
            }

            public function latest(string $deck): ?DeckVersion
            {
                return null;
            }

            public function versions(string $deck): array
            {
                return [];
            }
        };

        $this->expectException(DeckConflict::class);
        $this->expectExceptionMessageIsOrContains('The deck "d" already has a version 1: read the deck again and save it once more.');

        new Decks($store)->save(new Deck('d', 'D', 'f'), $this->at);
    }

    public function testACopyIsTheFirstVersionOfANewDeckLinkedToItsOriginal(): void
    {
        $this->decks->save(new Deck('d', 'D', 'f', ['a' => 1]), $this->at);
        $this->decks->save(new Deck('d', 'D', 'f', ['a' => 2]), $this->at);

        $latest = $this->decks->copy('d', 'e', 'My copy', $this->at);
        $first = $this->decks->copy('d', 'g', 'Old copy', $this->at, 1);

        self::assertSame(1, $latest->version);
        self::assertSame('e', $latest->deck->id);
        self::assertSame('My copy', $latest->deck->name);
        self::assertSame(['a' => 2], $latest->deck->cards);
        self::assertSame(['deck' => 'd', 'version' => 2], $latest->deck->copiedFrom?->toArray());
        self::assertSame(['a' => 1], $first->deck->cards);
        self::assertSame(['deck' => 'd', 'version' => 1], $first->deck->copiedFrom?->toArray());
        self::assertSame($latest, $this->decks->latest('e'));
        self::assertSame(2, $this->decks->latest('d')->version);
    }

    public function testACopyNeedsANewIdentifier(): void
    {
        $this->decks->save(new Deck('d', 'D', 'f'), $this->at);
        $this->decks->save(new Deck('e', 'E', 'f'), $this->at);

        $this->expectException(DeckConflict::class);
        $this->expectExceptionMessageIsOrContains('The deck "e" already exists: a copy takes a new identifier.');

        $this->decks->copy('d', 'e', 'E', $this->at);
    }

    public function testACopyOfAnExistingIdentifierIsNeverWritten(): void
    {
        $store = new class implements DeckStore {
            public int $added = 0;

            public function add(DeckVersion $version): bool
            {
                ++$this->added;

                return true;
            }

            public function version(string $deck, int $version): ?DeckVersion
            {
                return null;
            }

            public function latest(string $deck): ?DeckVersion
            {
                return 'none' === $deck ? null : new DeckVersion(new Deck($deck, 'D', 'f'), 1, new DateTimeImmutable());
            }

            public function versions(string $deck): array
            {
                return [];
            }
        };

        try {
            new Decks($store)->copy('d', 'e', 'E', $this->at);
            self::fail('A copy over an existing deck was accepted.');
        } catch (DeckConflict) {
            self::assertSame(0, $store->added);
        }
    }

    public function testACopyWrittenFirstByAnotherRequestIsAConflict(): void
    {
        $store = new class implements DeckStore {
            /** @var list<DeckVersion> */
            public array $refused = [];

            public function add(DeckVersion $version): bool
            {
                $this->refused[] = $version;

                return false;
            }

            public function version(string $deck, int $version): ?DeckVersion
            {
                return null;
            }

            public function latest(string $deck): ?DeckVersion
            {
                return 'd' === $deck ? new DeckVersion(new Deck('d', 'D', 'f'), 1, new DateTimeImmutable()) : null;
            }

            public function versions(string $deck): array
            {
                return [];
            }
        };

        $this->expectException(DeckConflict::class);
        $this->expectExceptionMessageIsOrContains('The deck "e" already exists: a copy takes a new identifier.');

        new Decks($store)->copy('d', 'e', 'E', $this->at);
    }

    public function testAnUnknownDeckIsAnError(): void
    {
        $this->expectException(UnknownDeck::class);
        $this->expectExceptionMessageIsOrContains('The deck "nope" has no saved version.');

        $this->decks->history('nope');
    }

    public function testTheLatestOfAnUnknownDeckIsAnError(): void
    {
        $this->expectException(UnknownDeck::class);
        $this->expectExceptionMessageIsOrContains('The deck "nope" has no saved version.');

        $this->decks->latest('nope');
    }

    public function testAnUnknownVersionIsAnError(): void
    {
        $this->decks->save(new Deck('d', 'D', 'f'), $this->at);

        $this->expectException(UnknownDeck::class);
        $this->expectExceptionMessageIsOrContains('The deck "d" has no version 2.');

        $this->decks->diff('d', 1, 2);
    }

    public function testAVersionChecksItsNumberAndNote(): void
    {
        $deck = new Deck('d', 'D', 'f');

        self::assertSame(str_repeat('é', 500), new DeckVersion($deck, 1, $this->at, str_repeat('é', 500))->note);
        self::assertSame('', new DeckVersion($deck, 1, $this->at, '')->note);
        self::assertSame(['deck' => 'd', 'version' => 3], new DeckVersion($deck, 3, $this->at)->reference()->toArray());

        foreach ([[0, null, 'A version of a deck is 1 or more; 0 is not.'], [1, str_repeat('é', 501), 'The note of a version is text of at most 500 characters, without control characters.'], [1, "a\u{0007}", 'The note of a version is text']] as [$version, $note, $message]) {
            try {
                new DeckVersion($deck, $version, $this->at, $note);
                self::fail('The version was accepted.');
            } catch (InvalidDeck $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }
    }
}
