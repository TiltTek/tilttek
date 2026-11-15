<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit;

use Closure;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\Check;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\Pseudonym;
use Tilttek\FriendChallenges\ReportReason;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\FriendChallenges\Validation;

/**
 * A reference of the kind `erased` stands only for a member `erase()` took out: one a caller gives is refused before
 * anything is written, reported or moved in the escrow.
 */
#[CoversClass(FriendChallenges::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidChallenge::class)]
final class ErasedReferencesTest extends TestCase
{
    public function testTheMessageNamesTheRoleAndTheReference(): void
    {
        $w = new World();
        $creator = ResourceReference::of('erased', '00112233aabbccdd');

        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains('The creator of a challenge is erased:00112233aabbccdd, a pseudonym the library draws for an erased member; it is never taken from a caller.');

        $w->challenges->create($w->draft(), $creator);
    }

    public function testAnErasedCreatorCreatesNothing(): void
    {
        $w = new World();
        $creator = Pseudonym::draw();

        $this->refused($w, 'The creator of a challenge', $creator, static fn (): Challenge => $w->challenges->create($w->draft(), $creator));

        self::assertNull($w->store->get('c1'));
    }

    public function testThePseudonymOfAnErasedCreatorDoesNotGiveTheirChallengeBack(): void
    {
        $w = $this->created();
        $w->challenges->erase(World::ana());
        $w->events->take();

        $pseudonym = $w->challenges->get('c1')->creator;

        $this->refused($w, 'The creator of a challenge', $pseudonym, static fn (): Challenge => $w->challenges->create($w->draft(), $pseudonym));
    }

    public function testAnErasedInviteeIsNotInvited(): void
    {
        $w = new World();
        $invitee = Pseudonym::draw();
        $draft = $w->draft(invitees: [World::bea(), $invitee]);

        $this->refused($w, 'An invitee', $invitee, static fn (): Challenge => $w->challenges->create($draft, World::ana()));

        self::assertNull($w->store->get('c1'));
    }

    public function testAnErasedMemberJoinsNothing(): void
    {
        $w = $this->created();
        $member = Pseudonym::draw();

        $this->refused($w, 'A participant', $member, static fn (): Challenge => $w->challenges->join('c1', $member));
    }

    public function testAnErasedMemberDeclinesNothing(): void
    {
        $w = $this->created();
        $member = Pseudonym::draw();

        $this->refused($w, 'An invitee', $member, static fn (): Challenge => $w->challenges->decline('c1', $member));
    }

    public function testAnErasedMemberSubmitsNoProof(): void
    {
        $w = $this->created();
        $member = Pseudonym::draw();

        $this->refused($w, 'A participant', $member, static fn (): Challenge => $w->challenges->submitProof('c1', 'p1', $member, 'upload:1'));
    }

    public function testAnErasedJudgeApprovesNothing(): void
    {
        $w = $this->proved();
        $judge = Pseudonym::draw();

        $this->refused($w, 'A judge', $judge, static fn (): Challenge => $w->challenges->approve('c1', 'p1', $judge));
    }

    public function testAnErasedJudgeRejectsNothing(): void
    {
        $w = $this->proved();
        $judge = Pseudonym::draw();

        $this->refused($w, 'A judge', $judge, static fn (): Challenge => $w->challenges->reject('c1', 'p1', $judge));
    }

    public function testAnErasedCreatorCancelsNothing(): void
    {
        $w = $this->created();
        $creator = Pseudonym::draw();

        $this->refused($w, 'The creator of a challenge', $creator, static fn (): Challenge => $w->challenges->cancel('c1', $creator));
    }

    public function testAnErasedMemberReportsNothing(): void
    {
        $w = $this->created();
        $reporter = Pseudonym::draw();

        $this->refused($w, 'The author of a report', $reporter, static fn () => $w->challenges->report('c1', $reporter, ReportReason::Unsafe));

        self::assertSame([], $w->moderation->reports);
    }

    public function testAnErasedModeratorFreezesNothing(): void
    {
        $w = $this->created();
        $moderator = Pseudonym::draw();

        $this->refused($w, 'A moderator', $moderator, static fn (): Challenge => $w->challenges->freeze('c1', $moderator));
    }

    public function testAnErasedModeratorUnfreezesNothing(): void
    {
        $w = $this->created();
        $w->challenges->freeze('c1', World::mo());
        $w->events->take();

        $moderator = Pseudonym::draw();

        $this->refused($w, 'A moderator', $moderator, static fn (): Challenge => $w->challenges->unfreeze('c1', $moderator));
    }

    public function testAnErasedModeratorRemovesNothing(): void
    {
        $w = $this->created();
        $moderator = Pseudonym::draw();

        $this->refused($w, 'A moderator', $moderator, static fn (): Challenge => $w->challenges->remove('c1', $moderator));
    }

    public function testAnErasedModeratorRemovesNoProof(): void
    {
        $w = $this->proved();
        $moderator = Pseudonym::draw();

        $this->refused($w, 'A moderator', $moderator, static fn (): Challenge => $w->challenges->removeProof('c1', 'p1', $moderator));
    }

    public function testAPseudonymIsNotErasedAgain(): void
    {
        $w = $this->created();
        $pseudonym = Pseudonym::draw();

        $this->refused($w, 'The member to erase', $pseudonym, static fn (): int => $w->challenges->erase($pseudonym));
    }

    public function testThePseudonymOfAnErasedCreatorIsNotErasedAgain(): void
    {
        $w = $this->created();
        $w->challenges->erase(World::ana());
        $w->events->take();

        $pseudonym = $w->challenges->get('c1')->creator;

        $this->refused($w, 'The member to erase', $pseudonym, static fn (): int => $w->challenges->erase($pseudonym));
    }

    private function created(): World
    {
        $w = new World();
        $w->challenges->create($w->draft(validation: Validation::Creator), World::ana());
        $w->events->take();

        return $w;
    }

    private function proved(): World
    {
        $w = $this->created();
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:1');
        $w->events->take();

        return $w;
    }

    /**
     * The move is refused as one by a reference of the kind `erased`, and nothing changed: the challenge, the prize
     * held, the balances, the reports and the events.
     *
     * @param Closure(): mixed $move
     */
    private function refused(World $w, string $what, ResourceReference $reference, Closure $move): void
    {
        $before = $this->state($w);

        try {
            $move();
            self::fail(\sprintf('A move by %s was taken.', $reference->toString()));
        } catch (InvalidChallenge $invalidChallenge) {
            self::assertSame(\sprintf('%s is %s, a pseudonym the library draws for an erased member; it is never taken from a caller.', $what, $reference->toString()), $invalidChallenge->getMessage());
        }

        self::assertSame($before, $this->state($w));
        self::assertSame([], $w->events->events);
    }

    /**
     * @return array{?array<string, mixed>, int, list<int>, int}
     */
    private function state(World $w): array
    {
        return [
            $w->store->get('c1')?->toArray(),
            $w->points->held('friend-challenge:c1'),
            array_map($w->points->balance(...), [World::ana(), World::bea(), World::cy(), World::dee(), World::mo()]),
            \count($w->moderation->reports),
        ];
    }
}
