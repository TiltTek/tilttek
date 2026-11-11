<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Duel;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Chains;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Duel\DuelStanding;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Erasure;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Exception\QuestRefused;
use Tilttek\Quests\Exception\UnknownDuel;
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\Names;
use Tilttek\Quests\Progress\Finish;
use Tilttek\Quests\Progress\ProgressCalculator;
use Tilttek\Quests\Progress\QuestProgress;
use Tilttek\Quests\Progress\StepProgress;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\QuestProjection;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Quests;
use Tilttek\Quests\QuestStatus;
use Tilttek\Quests\Reward;
use Tilttek\Quests\Schedule\Dated;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Permanent;
use Tilttek\Quests\Scope;
use Tilttek\Quests\Step;
use Tilttek\Quests\StepMeasure;
use Tilttek\Quests\Store\InMemoryCompletionStore;
use Tilttek\Quests\Store\InMemoryContributionStore;
use Tilttek\Quests\Store\InMemoryDuelStore;
use Tilttek\Quests\Store\InMemoryEnrolmentStore;
use Tilttek\Quests\Tests\Unit\Fixture\Kit;
use Tilttek\Quests\Window;

#[CoversClass(Quest::class)]
#[CoversClass(Step::class)]
#[CoversClass(Scope::class)]
#[CoversClass(StepMeasure::class)]
#[CoversClass(Window::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(Dated::class)]
#[CoversClass(Permanent::class)]
#[CoversClass(Enrolment::class)]
#[CoversClass(Completion::class)]
#[CoversClass(Contribution::class)]
#[CoversClass(Duel::class)]
#[CoversClass(Duels::class)]
#[CoversClass(DuelStanding::class)]
#[CoversClass(ProgressCalculator::class)]
#[CoversClass(QuestProgress::class)]
#[CoversClass(StepProgress::class)]
#[CoversClass(Finish::class)]
#[CoversClass(InMemoryContributionStore::class)]
#[CoversClass(InMemoryEnrolmentStore::class)]
#[CoversClass(InMemoryCompletionStore::class)]
#[CoversClass(InMemoryDuelStore::class)]
#[CoversClass(QuestReader::class)]
#[CoversClass(InMemoryCatalog::class)]
#[CoversClass(Chains::class)]
#[CoversClass(Names::class)]
#[CoversClass(Reward::class)]
#[CoversClass(QuestCompleted::class)]
#[CoversClass(QuestStatus::class)]
#[CoversClass(Evaluator::class)]
#[CoversClass(Quests::class)]
#[CoversClass(QuestProjection::class)]
#[CoversClass(Erasure::class)]
final class DuelsTest extends TestCase
{
    private const array QUESTS = [
        'race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played'], 'count' => 2]], 'duel' => ['length' => 'P2D']],
        'open' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played']]]],
        'season' => ['kind' => 'duel', 'closes' => '2026-11-02', 'steps' => [['verbs' => ['game.played']]], 'duel' => ['length' => 'P7D']],
        'short' => ['kind' => 'duel', 'closes' => '2026-12-01', 'steps' => [['verbs' => ['game.played']]], 'duel' => ['length' => 'P1D']],
        'later' => ['kind' => 'duel', 'opens' => '2027-01-01', 'steps' => [['verbs' => ['game.played']]]],
        'solo' => ['steps' => [['verbs' => ['game.played']]]],
    ];

    public function testAMemberChallengesAnotherWhoAccepts(): void
    {
        $kit = Kit::of(self::QUESTS);
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');
        $kit->at('2026-11-01 09:00');

        $offered = $kit->duelService->challenge('d1', 'race', $one, $two);
        self::assertEquals(new Duel('d1', 'race', $one, $two, new DateTimeImmutable('2026-11-01 09:00 UTC')), $offered);
        self::assertEquals($offered, $kit->duelService->challenge('d1', 'race', $one, $two));
        self::assertEquals($offered, $kit->duelService->get('d1'));

        $kit->at('2026-11-01 10:00');
        $accepted = $kit->duelService->accept('d1', $two);
        self::assertSame(DuelStatus::Accepted, $accepted->status);
        self::assertEquals(new DateTimeImmutable('2026-11-01 10:00 UTC'), $accepted->acceptedAt);
        self::assertEquals(new DateTimeImmutable('2026-11-03 10:00 UTC'), $accepted->endsAt);
        self::assertEquals($accepted, $kit->duelService->accept('d1', $two));
        self::assertEquals([$accepted], $kit->duelService->of($one));
        $kit->at('2026-11-01 10:30');
        self::assertEquals($accepted, $kit->duelService->challenge('d1', 'race', $one, $two));

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 11:00');
        $standing = $kit->duelService->standing('d1');
        self::assertEquals($accepted, $standing->duel);
        self::assertSame(1, $standing->challenger->steps[0]->value);
        self::assertSame(0, $standing->opponent->steps[0]->value);
    }

    public function testADuelEndsWhenItsQuestClosesOrNever(): void
    {
        $kit = Kit::of(self::QUESTS);
        $kit->at('2026-11-01 09:00');

        foreach (['open' => null, 'season' => '2026-11-02 00:00', 'short' => '2026-11-02 09:00'] as $quest => $end) {
            $kit->duelService->challenge($quest, $quest, Kit::ref('member:1'), Kit::ref('member:2'));
            $endsAt = $kit->duelService->accept($quest, Kit::ref('member:2'))->endsAt;
            self::assertEquals(null === $end ? null : new DateTimeImmutable($end.' UTC'), $endsAt, $quest);
        }
    }

    public function testADuelIsDeclinedOrWithdrawnOnlyWhileItWaits(): void
    {
        $kit = Kit::of(self::QUESTS);
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', $one, $two);
        $kit->duelService->challenge('d2', 'race', $one, $two);

        $kit->at('2026-11-01 10:00');

        $declined = $kit->duelService->decline('d1', $two);
        self::assertSame(DuelStatus::Declined, $declined->status);
        self::assertEquals(new DateTimeImmutable('2026-11-01 10:00 UTC'), $declined->decidedAt);
        self::assertEquals($declined, $kit->duelService->decline('d1', $two));
        self::assertSame(DuelStatus::Withdrawn, $kit->duelService->withdraw('d2', $one)->status);
        self::assertSame(DuelStatus::Declined, $kit->duels->find('d1')?->status);

        $this->expectException(QuestRefused::class);
        $this->expectExceptionMessageIsOrContains('The duel "d1" is declined; it no longer waits for an answer.');
        $kit->duelService->withdraw('d1', $one);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusals(): iterable
    {
        yield 'another duel under the key' => ['The key "d1" names another duel.'];
        yield 'not a duel' => ['"solo" is not a duel.'];
        yield 'closed' => ['"later" is closed.'];
        yield 'accepted by the challenger' => ['Only member:2 answers the duel "d1" so.'];
        yield 'withdrawn by the opponent' => ['Only member:1 answers the duel "d1" so.'];
        yield 'declined by the challenger' => ['Only member:2 answers the duel "d1" so. '];
        yield 'accepted once declined' => ['The duel "d3" is declined; it no longer waits for an answer.'];
        yield 'accepted once closed' => ['"season" is closed.'];
    }

    #[DataProvider('refusals')]
    public function testWhatCannotBeIsRefused(string $message): void
    {
        $kit = Kit::of(self::QUESTS);
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', $one, $two);
        $kit->duelService->challenge('d3', 'race', $one, $two);
        $kit->duelService->decline('d3', $two);
        $kit->duelService->challenge('d4', 'season', $one, $two);

        $this->expectException(QuestRefused::class);
        $this->expectExceptionMessageIsOrContains(trim($message));

        match ($message) {
            'The key "d1" names another duel.' => $kit->duelService->challenge('d1', 'open', $one, $two),
            '"solo" is not a duel.' => $kit->duelService->challenge('d2', 'solo', $one, $two),
            '"later" is closed.' => $kit->duelService->challenge('d2', 'later', $one, $two),
            'Only member:2 answers the duel "d1" so.' => $kit->duelService->accept('d1', $one),
            'Only member:1 answers the duel "d1" so.' => $kit->duelService->withdraw('d1', $two),
            'Only member:2 answers the duel "d1" so. ' => $kit->duelService->decline('d1', $one),
            'The duel "d3" is declined; it no longer waits for an answer.' => $kit->duelService->accept('d3', $two),
            default => (static function () use ($kit, $two): void {
                $kit->at('2026-11-02 00:00');
                $kit->duelService->accept('d4', $two);
            })(),
        };
    }

    public function testTheSameKeyForAnotherPairIsRefused(): void
    {
        $kit = Kit::of(self::QUESTS);
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));

        foreach ([['member:3', 'member:2'], ['member:1', 'member:3']] as [$challenger, $opponent]) {
            try {
                $kit->duelService->challenge('d1', 'race', Kit::ref($challenger), Kit::ref($opponent));
                self::fail('Expected a refusal.');
            } catch (QuestRefused $questRefused) {
                self::assertSame('The key "d1" names another duel.', $questRefused->getMessage());
            }
        }
    }

    public function testAnOfferLeftUnansweredExpires(): void
    {
        $kit = Kit::of(self::QUESTS);
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));

        $kit->at('2026-11-08 09:00');

        try {
            $kit->duelService->accept('d1', Kit::ref('member:2'));
            self::fail('Expected a refusal.');
        } catch (QuestRefused $questRefused) {
            self::assertSame('The duel "d1" was not answered in time.', $questRefused->getMessage());
        }

        self::assertSame(DuelStatus::Expired, $kit->duels->find('d1')?->status);
        self::assertSame(['duel d1 expired'], $kit->recorder->events());
    }

    public function testTheSweepExpiresTheOffersAndTheDuelsWhoseTimeIsUp(): void
    {
        $kit = Kit::of(self::QUESTS);
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));
        $kit->duelService->challenge('d2', 'race', Kit::ref('member:1'), Kit::ref('member:3'));
        $kit->duelService->accept('d2', Kit::ref('member:3'));
        $kit->at('2026-11-01 10:00');
        $kit->duelService->challenge('d3', 'race', Kit::ref('member:1'), Kit::ref('member:4'));

        $kit->at('2026-11-08 09:00');

        $expired = $kit->duelService->expire();

        self::assertSame(['d1', 'd2'], array_map(static fn (Duel $duel): string => $duel->key, $expired));
        self::assertEquals(new DateTimeImmutable('2026-11-08 09:00 UTC'), $expired[0]->decidedAt);
        self::assertSame(['duel d1 expired', 'duel d2 expired'], $kit->recorder->events());
        self::assertSame(DuelStatus::Offered, $kit->duels->find('d3')?->status);

        $kit->at('2026-11-08 10:00');
        self::assertCount(1, new Duels($kit->evaluator, $kit->clock)->expire());
    }

    public function testAnUnknownDuelIsReported(): void
    {
        $kit = Kit::of(self::QUESTS);

        $this->expectException(UnknownDuel::class);
        $this->expectExceptionMessageIsOrContains('No duel has the key "d9".');
        $kit->duelService->standing('d9');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lifetimes(): iterable
    {
        yield 'not a duration' => ['soon', '"soon" is not an ISO 8601 duration like "P7D".'];
        yield 'no time' => ['PT0S', 'An offer waits for some time; "PT0S" is no time.'];
    }

    #[DataProvider('lifetimes')]
    public function testTheLifetimeOfAnOfferIsSomeTime(string $lifetime, string $message): void
    {
        $kit = Kit::of(self::QUESTS);

        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains($message);
        new Duels($kit->evaluator, $kit->clock, null, $lifetime);
    }
}
