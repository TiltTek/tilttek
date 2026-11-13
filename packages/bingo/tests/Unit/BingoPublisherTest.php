<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tilttek\Bingo\Event\BingoWon;
use Tilttek\Bingo\Event\SquareCalled;
use Tilttek\Bingo\Event\SquareTicked;
use Tilttek\Bingo\Exception\InvalidBingo;
use Tilttek\Bingo\Mercure\BingoPublisher;

#[CoversClass(BingoPublisher::class)]
final class BingoPublisherTest extends TestCase
{
    public function testCallsWinsAndTicksArePushedPrivatelyWithoutAPlayer(): void
    {
        /** @var list<Update> $updates */
        $updates = [];
        $hub = new MockHub('https://mercure.test/.well-known/mercure', new StaticTokenProvider('token'), static function (Update $update) use (&$updates): string {
            $updates[] = $update;

            return 'id';
        });
        $publisher = new BingoPublisher($hub, 'https://bingo.test/games/');

        $publisher->squareCalled(new SquareCalled('con 2026', 's4'));
        $publisher->bingoWon(new BingoWon('con 2026', 7, 'member:44', 'row'));
        $publisher->squareTicked(new SquareTicked('con 2026', 7, 's9'));

        self::assertSame('https://bingo.test/games/con%202026', $publisher->topic('con 2026'));
        self::assertSame('https://bingo.test/games/con%202026/7', $publisher->cardTopic('con 2026', 7));
        self::assertSame([
            [['https://bingo.test/games/con%202026'], '{"type":"square-called","game":"con 2026","square":"s4"}'],
            [['https://bingo.test/games/con%202026'], '{"type":"bingo-won","game":"con 2026","serial":7,"pattern":"row"}'],
            [['https://bingo.test/games/con%202026/7'], '{"type":"square-ticked","game":"con 2026","serial":7,"square":"s9"}'],
        ], array_map(static fn (Update $update): array => [$update->getTopics(), $update->getData()], $updates));

        foreach ($updates as $update) {
            self::assertTrue($update->isPrivate());
            self::assertStringNotContainsString('member:44', $update->getData());
        }
    }

    public function testTheTopicPrefixIsGiven(): void
    {
        $this->expectException(InvalidBingo::class);
        $this->expectExceptionMessageIsOrContains('The topic prefix of the bingo games is not empty.');
        new BingoPublisher(new MockHub('https://mercure.test', new StaticTokenProvider('token'), static fn (): string => 'id'), '');
    }
}
