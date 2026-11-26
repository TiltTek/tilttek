<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\Control;
use Tilttek\MiniGames\Exception\InvalidMiniGame;

#[CoversClass(Control::class)]
#[CoversClass(InvalidMiniGame::class)]
final class ControlTest extends TestCase
{
    public function testAnActionAndItsKeys(): void
    {
        $control = new Control('higher', ['ArrowUp', 'h']);

        self::assertSame('higher', $control->action);
        self::assertSame(['ArrowUp', 'h'], $control->keys);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $control = new Control(str_repeat('a', 64), array_fill(0, 12, str_repeat('k', 32)));

        self::assertCount(12, $control->keys);
        self::assertSame('erase-all-2', new Control('erase-all-2', ['!'])->action);
        self::assertSame(['~'], new Control('tilde', ['~'])->keys);
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function refused(): iterable
    {
        yield 'an action in capitals' => ['Higher', ['h'], 'The action "Higher" is 1 to 64 lowercase letters and digits, words joined by "-".'];
        yield 'an empty action' => ['', ['h'], 'The action "" is 1 to 64'];
        yield 'an action starting with a digit' => ['2nd', ['h'], 'The action "2nd"'];
        yield 'a trailing hyphen' => ['higher-', ['h'], 'The action "higher-"'];
        yield 'an action too long' => [str_repeat('a', 65), ['h'], 'is 1 to 64'];
        yield 'an action on two lines' => ["higher\n", ['h'], 'is 1 to 64'];
        yield 'no key' => ['higher', [], 'The action "higher" has 1 to 12 keys.'];
        yield 'thirteen keys' => ['higher', array_fill(0, 13, 'h'), 'has 1 to 12 keys.'];
        yield 'an empty key' => ['higher', [''], 'A key of the action "higher" is 1 to 32 printable ASCII characters.'];
        yield 'a space' => ['higher', [' '], 'is 1 to 32 printable'];
        yield 'a key too long' => ['higher', [str_repeat('k', 33)], 'is 1 to 32 printable'];
        yield 'a key with a line break' => ['higher', ["h\n"], 'is 1 to 32 printable'];
        yield 'a key outside ASCII' => ['higher', ['é'], 'is 1 to 32 printable'];
        yield 'a second key refused' => ['higher', ['h', "\x7F"], 'is 1 to 32 printable'];
    }

    /**
     * @param list<string> $keys
     */
    #[DataProvider('refused')]
    public function testARefusedControl(string $action, array $keys, string $message): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Control($action, $keys);
    }
}
