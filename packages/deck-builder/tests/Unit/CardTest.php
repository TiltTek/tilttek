<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

#[CoversClass(Card::class)]
final class CardTest extends TestCase
{
    public function testACardKeepsWhatItIsGiven(): void
    {
        $card = new Card('ember-fox', 'Ember Fox', 1);

        self::assertSame('ember-fox', $card->id);
        self::assertSame('Ember Fox', $card->name);
        self::assertSame(1, $card->copyLimit);
        self::assertSame('"Ember Fox" (ember-fox)', $card->label());
        self::assertNull(new Card('a', 'A')->copyLimit);
        self::assertSame(999, new Card('a', 'A', 999)->copyLimit);
    }

    #[TestWith([0])]
    #[TestWith([1000])]
    #[TestWith([-1])]
    public function testACopyLimitOutOfBoundsIsRefused(int $limit): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The copy limit of the card "a" is from 1 to 999; %d is not.', $limit));

        new Card('a', 'A', $limit);
    }

    public function testTheIdentifierAndTheNameAreChecked(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The name of a card is text');

        new Card('a', '');
    }

    public function testTheIdentifierIsChecked(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a card is');

        new Card('', 'A');
    }
}
