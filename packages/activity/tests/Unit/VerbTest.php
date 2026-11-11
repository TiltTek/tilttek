<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Exception\UnknownVerb;
use Tilttek\Activity\Verb;
use Tilttek\Activity\VerbPattern;
use Tilttek\Activity\Verbs;

#[CoversClass(Verb::class)]
#[CoversClass(VerbPattern::class)]
#[CoversClass(Verbs::class)]
#[CoversClass(InvalidActivity::class)]
#[CoversClass(UnknownVerb::class)]
final class VerbTest extends TestCase
{
    public function testAVerbIsDottedSegments(): void
    {
        self::assertSame('game.played', Verb::fromString('game.played')->value);
        self::assertSame('a', Verb::fromString('a')->value);
        self::assertSame('news_feed.item_2.read', Verb::fromString('news_feed.item_2.read')->value);
        self::assertSame(str_repeat('a', 128), Verb::fromString(str_repeat('a', 128))->value);
        self::assertTrue(Verb::fromString('game.played')->equals(Verb::fromString('game.played')));
        self::assertFalse(Verb::fromString('game.played')->equals(Verb::fromString('game.won')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidVerbs(): iterable
    {
        yield 'empty' => ['', '"" is not a verb: lower-case segments of letters, digits and "_", each starting with a letter, joined by dots, such as "game.played".'];
        yield 'upper case' => ['Game.played', '"Game.played" is not a verb'];
        yield 'leading digit' => ['1game', '"1game" is not a verb'];
        yield 'segment with a digit first' => ['game.1st', '"game.1st" is not a verb'];
        yield 'empty segment' => ['game..played', '"game..played" is not a verb'];
        yield 'trailing dot' => ['game.', '"game." is not a verb'];
        yield 'leading dot' => ['.game', '".game" is not a verb'];
        yield 'dash' => ['game-played', '"game-played" is not a verb'];
        yield 'trailing newline' => ["game\n", "\"game\n\" is not a verb"];
        yield 'too long' => [str_repeat('a', 129), 'A verb is at most 128 bytes; "'.str_repeat('a', 129).'" is 129.'];
    }

    #[DataProvider('invalidVerbs')]
    public function testAnInvalidVerbIsRefused(string $verb, string $message): void
    {
        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains($message);

        Verb::fromString($verb);
    }

    public function testAPatternMatchesAVerbAPrefixOrEverything(): void
    {
        $exact = VerbPattern::fromString('game.played');
        self::assertSame('game.played', $exact->value);
        self::assertTrue($exact->matches(Verb::fromString('game.played')));
        self::assertFalse($exact->matches(Verb::fromString('game.played.twice')));
        self::assertFalse($exact->matches(Verb::fromString('game')));
        self::assertSame('game.played', $exact->exact()?->value);
        self::assertNull($exact->prefix());

        $prefix = VerbPattern::fromString('game.*');
        self::assertSame('game.*', $prefix->value);
        self::assertTrue($prefix->matches(Verb::fromString('game.played')));
        self::assertTrue($prefix->matches(Verb::fromString('game.won.flawless')));
        self::assertFalse($prefix->matches(Verb::fromString('game')));
        self::assertFalse($prefix->matches(Verb::fromString('gamebook.read')));
        self::assertNull($prefix->exact());
        self::assertSame('game.', $prefix->prefix());

        $all = VerbPattern::fromString('*');
        self::assertTrue($all->matches(Verb::fromString('anything.at.all')));
        self::assertNull($all->exact());
        self::assertSame('', $all->prefix());
    }

    public function testAnInvalidPatternIsRefused(): void
    {
        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains('"game.*.won" is not a verb');

        VerbPattern::fromString('game.*.won');
    }

    public function testAPrefixPatternNeedsAVerbBeforeTheStar(): void
    {
        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains('"." is not a verb');

        VerbPattern::fromString('..*');
    }

    public function testDeclaredVerbsLetThroughWhatTheirPatternsMatch(): void
    {
        $any = new Verbs();
        self::assertSame([], $any->patterns);
        self::assertTrue($any->allows(Verb::fromString('whatever')));

        $verbs = Verbs::of(['news.commented', 'game.*']);
        self::assertSame(['news.commented', 'game.*'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $verbs->patterns));
        self::assertTrue($verbs->allows(Verb::fromString('news.commented')));
        self::assertTrue($verbs->allows(Verb::fromString('game.won')));
        self::assertFalse($verbs->allows(Verb::fromString('news.read')));
        $verbs->check(Verb::fromString('game.played'));

        $this->expectException(UnknownVerb::class);
        $this->expectExceptionMessageIsOrContains('The verb "news.read" is not declared.');

        $verbs->check(Verb::fromString('news.read'));
    }

    public function testVerbsKeepTheirPatternsAsAList(): void
    {
        $verbs = new Verbs(...['a' => VerbPattern::fromString('a'), 'b' => VerbPattern::fromString('b')]);

        self::assertSame([0, 1], array_keys($verbs->patterns));
    }
}
