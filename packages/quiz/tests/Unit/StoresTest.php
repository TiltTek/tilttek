<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\SelfPaced\Play;
use Tilttek\Quiz\Store\InMemoryLiveSessionStore;
use Tilttek\Quiz\Store\InMemoryPlayStore;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;

#[CoversClass(InMemoryLiveSessionStore::class)]
#[CoversClass(InMemoryPlayStore::class)]
final class StoresTest extends TestCase
{
    public function testAPinIsHeldByOneSessionNotOverAtMost(): void
    {
        $store = new InMemoryLiveSessionStore();
        $first = LiveSession::open('s1', Quizzes::trivia(), '123456');

        self::assertTrue($store->add($first));
        self::assertFalse($store->add(LiveSession::open('s2', Quizzes::trivia(), '123456')));
        self::assertNull($store->find('s2'));
        self::assertTrue($store->add(LiveSession::open('s3', Quizzes::trivia(), '654321')));

        $first->finish();
        $store->save($first);

        self::assertTrue($store->add(LiveSession::open('s2', Quizzes::trivia(), '123456')));
        self::assertSame('s2', $store->findByPin('123456')?->id);
    }

    public function testASessionChangedAndNotSavedLeavesTheStoredOneAsItWas(): void
    {
        $store = new InMemoryLiveSessionStore();
        $session = LiveSession::open('s1', Quizzes::trivia(), '123456');
        $store->add($session);
        $session->join(Player::member('m1'));
        self::assertSame(1, $store->find('s1')?->version());

        $store->save($session);
        $session->join(Player::member('m2'));
        self::assertSame(2, $store->find('s1')?->version());

        $found = $store->find('s1');
        self::assertNotNull($found);
        $found->join(Player::member('m3'));
        $again = $store->find('s1');
        self::assertNotNull($again);
        self::assertSame(2, $again->version());
        self::assertSame(2, $store->findByPin('123456')?->version());
    }

    public function testASessionNeverAddedIsNotSaved(): void
    {
        $session = LiveSession::open('s1', Quizzes::trivia(), '123456');
        $session->join(Player::member('m1'));

        $this->expectException(QuizConflict::class);
        $this->expectExceptionMessageIsOrContains('The live session s1 is not stored.');
        new InMemoryLiveSessionStore()->save($session);
    }

    public function testAPlayChangedAndNotSavedLeavesTheStoredOneAsItWas(): void
    {
        $quiz = Quizzes::trivia();
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $store = new InMemoryPlayStore();
        $play = Play::start('p1', $quiz, Player::member('m1'), $now);
        $store->add($play);
        $play->answer($quiz, 'm1', ChoiceAnswer::of('b'), $now);
        self::assertSame(0, $store->find('p1')?->position());

        $store->save($play);
        $play->answer($quiz, 'm1', new NumberAnswer(1974), $now);
        self::assertSame(1, $store->find('p1')?->position());
    }

    public function testAPlayNeverAddedIsNotSaved(): void
    {
        $quiz = Quizzes::trivia();
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $play = Play::start('p1', $quiz, Player::member('m1'), $now);
        $play->answer($quiz, 'm1', ChoiceAnswer::of('b'), $now);

        $this->expectException(QuizConflict::class);
        $this->expectExceptionMessageIsOrContains('The play p1 is not stored.');
        new InMemoryPlayStore()->save($play);
    }
}
