<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Report;
use Tilttek\FriendChallenges\ReportReason;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Store\InMemoryFriendship;
use Tilttek\FriendChallenges\Store\InMemoryModeration;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;

#[CoversClass(InMemoryChallengeStore::class)]
#[CoversClass(InMemoryFriendship::class)]
#[CoversClass(InMemoryModeration::class)]
final class StoresTest extends TestCase
{
    public function testTheStoreKeepsEachVersionOnce(): void
    {
        $store = new InMemoryChallengeStore();
        $challenge = $this->challenge('c1', '+7 days');

        self::assertNull($store->get('c1'));
        $store->add($challenge);
        self::assertSame($challenge->toArray(), $store->get('c1')?->toArray());

        try {
            $store->add($challenge);
            self::fail('The challenge is kept already.');
        } catch (ChallengeConflict) {
            self::assertSame(1, $store->get('c1')->version);
        }

        $joined = $challenge->join(World::bea(), $this->at());
        $store->update($joined, 1);
        self::assertSame(2, $store->get('c1')?->version);

        $this->expectException(ChallengeConflict::class);

        $store->update($joined->join(World::cy(), $this->at()), 1);
    }

    public function testAnUnknownChallengeIsNotUpdated(): void
    {
        $this->expectException(ChallengeConflict::class);

        new InMemoryChallengeStore()->update($this->challenge('c1', '+7 days'), 1);
    }

    public function testTheDueChallengesComeEarliestFirst(): void
    {
        $store = new InMemoryChallengeStore();
        $store->add($this->challenge('late', '+9 days'));
        $store->add($this->challenge('early', '+7 days'));
        $store->add($this->challenge('later', '+20 days'));
        $store->add($this->challenge('cancelled', '+1 day')->cancel(World::ana(), $this->at()));
        $store->add($this->challenge('middle', '+8 days'));

        $at = $this->at()->modify('+12 days');

        self::assertSame(['early', 'middle', 'late'], $store->due($at, 10));
        self::assertSame(['early', 'middle'], $store->due($at, 2));
        self::assertSame([], $store->due($at, 0));
        self::assertSame([], $store->due($at, -1));
        self::assertSame(['early'], $store->due($this->at()->modify('+10 days'), 10));
        self::assertSame([], $store->due($this->at()->modify('+10 days -1 second'), 10));
    }

    public function testTheDueChallengesAreOrderedToTheMicrosecond(): void
    {
        $store = new InMemoryChallengeStore();
        $store->add($this->challenge('b', '+7 days +500000 usec'));
        $store->add($this->challenge('a', '+7 days +250000 usec'));

        self::assertSame(['a', 'b'], $store->due($this->at()->modify('+11 days'), 10));
    }

    public function testNumericIdentifiersComeBackAsTexts(): void
    {
        $store = new InMemoryChallengeStore();
        $store->add($this->challenge('42', '+7 days'));

        self::assertSame(['42'], $store->due($this->at()->modify('+11 days'), 10));
    }

    public function testFriendshipsGoBothWays(): void
    {
        $friends = new InMemoryFriendship();
        $friends->befriend(World::ana(), World::bea());

        self::assertTrue($friends->areFriends(World::ana(), World::bea()));
        self::assertTrue($friends->areFriends(World::bea(), World::ana()));
        self::assertFalse($friends->areFriends(World::ana(), World::cy()));
    }

    public function testTheModerationKeepsItsReportsAndKnowsItsModerators(): void
    {
        $moderation = new InMemoryModeration(World::mo(), World::dee());
        $report = new Report('c1', World::cy(), ReportReason::Spam, $this->at());

        $moderation->report($report);

        self::assertSame([$report], $moderation->reports);
        self::assertTrue($moderation->mayModerate(World::mo()));
        self::assertTrue($moderation->mayModerate(World::dee()));
        self::assertFalse($moderation->mayModerate(World::ana()));
    }

    private function challenge(string $id, string $ends): Challenge
    {
        return Challenge::create(new ChallengeDraft($id, 'Paint', new Prize(5), $this->at()->modify($ends), [World::bea(), World::cy()]), World::ana(), $this->at(), new ChallengeRules());
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-14T01:00:00Z');
    }
}
