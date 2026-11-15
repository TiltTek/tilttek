<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\GrantPolicy;
use Tilttek\LuckTokens\HolderScope;
use Tilttek\LuckTokens\Refresh;
use Tilttek\LuckTokens\TokenKind;

#[CoversClass(TokenKind::class)]
#[CoversClass(InvalidTokenRule::class)]
final class TokenKindTest extends TestCase
{
    public function testAKindTakesItsDefaults(): void
    {
        $kind = new TokenKind('inspiration', 'Inspiration', 1);

        self::assertSame([
            'key' => 'inspiration',
            'name' => 'Inspiration',
            'cap' => 1,
            'scope' => 'character',
            'refresh' => 'never',
            'refresh_to' => 0,
            'carry_over' => null,
            'grants' => 'game-masters',
            'transferable' => false,
        ], $kind->toArray());
    }

    public function testTheStartOfASessionKeepsAndRefreshes(): void
    {
        $inspiration = new TokenKind('inspiration', 'Inspiration', 1);
        $bennies = new TokenKind('bennies', 'Bennies', 5, HolderScope::Player, Refresh::EachSession, 3, 0, GrantPolicy::Anyone, true);
        $fate = new TokenKind('fate', 'Fate points', 10, refresh: Refresh::EachSession, refreshTo: 3);
        $luck = new TokenKind('luck', 'Luck', 10, carryOver: 2);

        self::assertSame([0, 1], [$inspiration->atSessionStart(0), $inspiration->atSessionStart(1)]);
        self::assertSame([3, 3, 3], [$bennies->atSessionStart(0), $bennies->atSessionStart(3), $bennies->atSessionStart(5)]);
        self::assertSame([3, 3, 7], [$fate->atSessionStart(0), $fate->atSessionStart(2), $fate->atSessionStart(7)]);
        self::assertSame([0, 2, 2], [$luck->atSessionStart(0), $luck->atSessionStart(2), $luck->atSessionStart(9)]);
        self::assertSame(['player', 'each-session', 3, 0, 'anyone', true], array_values(\array_slice($bennies->toArray(), 3)));
    }

    public function testTheLimitsAreTaken(): void
    {
        $key = 'a'.str_repeat('-', 62).'b';

        self::assertSame($key, new TokenKind($key, str_repeat('é', 80), 1000, refresh: Refresh::EachSession, refreshTo: 1000, carryOver: 1000)->key);
        self::assertSame(0, new TokenKind('k', 'K', 1, carryOver: 0)->carryOver);
        self::assertSame(1, new TokenKind('k', 'K', 1, refresh: Refresh::EachSession, refreshTo: 1)->refreshTo);
    }

    /**
     * @return iterable<string, array{Closure(): TokenKind, string}>
     */
    public static function refused(): iterable
    {
        yield 'an empty key' => [static fn (): TokenKind => new TokenKind('', 'K', 1), 'The key of a token is 1 to 64 characters among a-z, 0-9 and inner "-"; "" is not.'];
        yield 'a key of upper case' => [static fn (): TokenKind => new TokenKind('Luck', 'K', 1), '"Luck" is not.'];
        yield 'a key of leading dash' => [static fn (): TokenKind => new TokenKind('-luck', 'K', 1), '"-luck" is not.'];
        yield 'a key of trailing dash' => [static fn (): TokenKind => new TokenKind('luck-', 'K', 1), '"luck-" is not.'];
        yield 'a key too long' => [static fn (): TokenKind => new TokenKind(str_repeat('k', 65), 'K', 1), '"'.str_repeat('k', 32).'" is not.'];
        yield 'a key with a new line' => [static fn (): TokenKind => new TokenKind("luck\n", 'K', 1), 'The key of a token'];
        yield 'a blank name' => [static fn (): TokenKind => new TokenKind('luck', ' ', 1), 'The name of the token "luck" is 1 to 80 characters, without control characters.'];
        yield 'a name too long' => [static fn (): TokenKind => new TokenKind('luck', str_repeat('é', 81), 1), 'The name of the token "luck"'];
        yield 'a name with a tab' => [static fn (): TokenKind => new TokenKind('luck', "Lu\tck", 1), 'The name of the token "luck"'];
        yield 'no cap' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 0), 'A holder keeps 1 to 1000 "luck" at most; not 0.'];
        yield 'a cap too high' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 1001), 'A holder keeps 1 to 1000 "luck" at most; not 1001.'];
        yield 'a refresh amount never used' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 3, refreshTo: 1), 'The token "luck" is never refreshed: it has no refresh amount.'];
        yield 'a refresh to nothing' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 3, refresh: Refresh::EachSession), 'The token "luck" is refreshed each session to 1 to 3, its cap; not 0.'];
        yield 'a refresh over the cap' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 3, refresh: Refresh::EachSession, refreshTo: 4), 'The token "luck" is refreshed each session to 1 to 3, its cap; not 4.'];
        yield 'a negative carry-over' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 3, carryOver: -1), 'A holder carries 0 to 3 "luck" to the next session, or all of them; not -1.'];
        yield 'a carry-over over the cap' => [static fn (): TokenKind => new TokenKind('luck', 'Luck', 3, carryOver: 4), 'A holder carries 0 to 3 "luck" to the next session, or all of them; not 4.'];
    }

    /**
     * @param Closure(): TokenKind $kind
     */
    #[DataProvider('refused')]
    public function testWhatIsNotAKindIsRefused(Closure $kind, string $message): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $kind();
    }
}
