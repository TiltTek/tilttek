<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Player;

#[CoversClass(Player::class)]
final class PlayerTest extends TestCase
{
    public function testAMemberHasNoPseudonym(): void
    {
        $member = Player::member('member:44');

        self::assertSame('member:44', $member->id);
        self::assertNull($member->pseudonym);
        self::assertFalse($member->isGuest());
    }

    public function testAGuestPlaysUnderATrimmedPseudonym(): void
    {
        $guest = Player::guest('guest-1', '  Pixel Wanderer ');

        self::assertSame('guest-1', $guest->id);
        self::assertSame('Pixel Wanderer', $guest->pseudonym);
        self::assertTrue($guest->isGuest());
        self::assertSame(str_repeat('é', 24), Player::guest('g', str_repeat('é', 24))->pseudonym);
        self::assertSame('x', Player::guest('g', 'x')->pseudonym);
    }

    #[TestWith(['', 'A pseudonym is 1 to 24 characters; this one is 0.'])]
    #[TestWith(['   ', 'A pseudonym is 1 to 24 characters; this one is 0.'])]
    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaa', 'A pseudonym is 1 to 24 characters; this one is 25.'])]
    #[TestWith(["bad\u{0007}bell", 'A pseudonym is text, without control or invisible characters.'])]
    #[TestWith(["zero\u{200B}width", 'A pseudonym is text, without control or invisible characters.'])]
    #[TestWith(["\xff", 'A pseudonym is text, without control or invisible characters.'])]
    public function testAPseudonymIsBounded(string $pseudonym, string $message): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains($message);
        Player::guest('g', $pseudonym);
    }

    public function testTheIdentifierIsChecked(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a player');
        Player::member('Jane Doe');
    }

    public function testAGuestIdentifierIsChecked(): void
    {
        $this->expectException(InvalidQuiz::class);
        Player::guest('a guest', 'Jane');
    }

    public function testAPseudonymIsReadAsUnicode(): void
    {
        self::assertSame('Gnōme', Player::guest('g', 'Gnōme')->pseudonym);
        self::assertSame('Āžō', Player::guest('g', 'Āžō')->pseudonym);
    }
}
