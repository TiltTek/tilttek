<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tilttek\Quiz\Event\LiveQuizFinished;
use Tilttek\Quiz\Event\PlayerJoined;
use Tilttek\Quiz\Event\QuestionClosed;
use Tilttek\Quiz\Event\QuestionShown;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Mercure\LiveSessionPublisher;

#[CoversClass(LiveSessionPublisher::class)]
final class LiveSessionPublisherTest extends TestCase
{
    public function testEachMoveIsPushedPrivatelyWithIdentifiersOnly(): void
    {
        /** @var list<Update> $updates */
        $updates = [];
        $hub = new MockHub('https://mercure.test/.well-known/mercure', new StaticTokenProvider('token'), static function (Update $update) use (&$updates): string {
            $updates[] = $update;

            return 'id';
        });
        $publisher = new LiveSessionPublisher($hub, 'https://quiz.test/live/');

        $publisher->playerJoined(new PlayerJoined('night 1', 'member:44'));
        $publisher->questionShown(new QuestionShown('trivia', 'night 1', 2));
        $publisher->questionClosed(new QuestionClosed('trivia', 'night 1', 2));
        $publisher->liveQuizFinished(new LiveQuizFinished('trivia', 'night 1'));

        self::assertSame('https://quiz.test/live/night%201', $publisher->topic('night 1'));
        self::assertSame([
            '{"type":"player-joined","session":"night 1"}',
            '{"type":"question-shown","session":"night 1","position":2}',
            '{"type":"question-closed","session":"night 1","position":2}',
            '{"type":"finished","session":"night 1"}',
        ], array_map(static fn (Update $update): string => $update->getData(), $updates));

        foreach ($updates as $update) {
            self::assertSame(['https://quiz.test/live/night%201'], $update->getTopics());
            self::assertTrue($update->isPrivate());
            self::assertStringNotContainsString('member:44', $update->getData());
        }
    }

    public function testTheTopicPrefixIsGiven(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The topic prefix of the live sessions is not empty.');
        new LiveSessionPublisher(new MockHub('https://mercure.test', new StaticTokenProvider('token'), static fn (): string => 'id'), '');
    }
}
