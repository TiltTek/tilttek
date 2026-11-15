<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\ChallengeState;
use Tilttek\FriendChallenges\Exception\ChallengeRefused;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Proof;
use Tilttek\FriendChallenges\ProofState;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

#[CoversClass(Challenge::class)]
#[CoversClass(Proof::class)]
#[CoversClass(ChallengeState::class)]
#[CoversClass(ChallengeRefused::class)]
final class ChallengeTest extends TestCase
{
    private const string AT = '2026-11-14T01:00:00Z';

    public function testACreatedChallengeIsOpenAtVersionOneInUtc(): void
    {
        $challenge = $this->challenge();

        self::assertSame(ChallengeState::Open, $challenge->state);
        self::assertSame(1, $challenge->version);
        self::assertSame('2026-11-14T01:00:00.000000Z', $challenge->toArray()['created_at']);
        self::assertSame('2026-11-21T01:00:00.000000Z', $challenge->toArray()['ends_at']);
        self::assertSame('2026-11-24T01:00:00.000000Z', $challenge->toArray()['review_ends_at']);
        self::assertSame(['member:bea', 'member:cy'], $challenge->toArray()['invitees']);
        self::assertSame([], $challenge->toArray()['participants']);
        self::assertNull($challenge->winner);
        self::assertNull($challenge->settledAt);
        self::assertFalse($challenge->state->isSettled());
    }

    public function testTheLengthOfAChallengeIsWithinTheRules(): void
    {
        $rules = new ChallengeRules(shortest: 3600, longest: 7200);

        self::assertSame(1, $this->challenge(ends: '+3600 seconds', rules: $rules)->version);
        self::assertSame(1, $this->challenge(ends: '+7200 seconds', rules: $rules)->version);

        foreach (['+3599 seconds', '+7201 seconds', '-1 hour'] as $ends) {
            try {
                $this->challenge(ends: $ends, rules: $rules);
                self::fail(\sprintf('A challenge ending %s is outside the rules.', $ends));
            } catch (InvalidChallenge $invalidChallenge) {
                self::assertStringContainsString('lasts 3600 to 7200 seconds', $invalidChallenge->getMessage());
            }
        }
    }

    public function testThePrizeIsWithinTheRules(): void
    {
        self::assertSame(1, $this->challenge(prize: 10, rules: new ChallengeRules(largestPrize: 10))->version);

        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains('A prize is 10 points at most; 11 is too many.');

        $this->challenge(prize: 11, rules: new ChallengeRules(largestPrize: 10));
    }

    public function testTheCreatorDoesNotInviteThemselves(): void
    {
        $this->expectException(InvalidChallenge::class);

        $this->challenge(invitees: [World::bea(), World::ana()]);
    }

    public function testADraftMatchesOnlyTheSameChallenge(): void
    {
        $challenge = $this->challenge();
        $at = new DateTimeImmutable(self::AT);

        self::assertTrue($challenge->matches($this->draft(), World::ana()));
        self::assertFalse($challenge->matches($this->draft(), World::bea()));
        self::assertFalse($challenge->matches($this->draft(id: 'c2'), World::ana()));
        self::assertFalse($challenge->matches(new ChallengeDraft('c1', 'Other', new Prize(100), $at->modify('+7 days'), [World::bea(), World::cy()]), World::ana()));
        self::assertFalse($challenge->matches(new ChallengeDraft('c1', 'Paint', new Prize(100), $at->modify('+7 days'), [World::bea(), World::cy()], description: 'More'), World::ana()));
        self::assertFalse($challenge->matches($this->draft(prize: 99), World::ana()));
        self::assertFalse($challenge->matches($this->draft(ends: '+6 days'), World::ana()));
        self::assertFalse($challenge->matches($this->draft(invitees: [World::bea()]), World::ana()));
        self::assertFalse($challenge->matches($this->draft(validation: Validation::Moderator), World::ana()));
        self::assertFalse($challenge->matches($this->draft(validation: Validation::Vote), World::ana()));
        self::assertFalse($this->challenge(validation: Validation::Vote, votes: 2)->matches($this->draft(validation: Validation::Vote), World::ana()));
        self::assertFalse($this->challenge(invitees: [], visibility: Visibility::Open)->matches(new ChallengeDraft('c1', 'Paint', new Prize(100), $at->modify('+7 days'), [World::bea()]), World::ana()));
        self::assertTrue($challenge->matches(new ChallengeDraft('c1', 'Paint', new Prize(100), $at->modify('+7 days')->setTimezone(new DateTimeZone('Asia/Tokyo')), [World::bea(), World::cy()]), World::ana()));
    }

    public function testMembersTakePartUntilTheChallengeEnds(): void
    {
        $challenge = $this->challenge();
        $ends = $challenge->endsAt;

        $joined = $challenge->join(World::bea(), $ends->modify('-1 second'));
        self::assertSame(2, $joined->version);
        self::assertSame($joined, $joined->join(World::bea(), $ends->modify('+1 day')));

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('The time to take part in the challenge "c1" is over.');

        $joined->join(World::cy(), $ends);
    }

    public function testOnlyTheInvitedTakePartBetweenFriends(): void
    {
        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('member:eve is not invited to the challenge "c1"');

        $this->challenge()->join(World::eve(), self::at());
    }

    public function testTheCreatorDoesNotTakePart(): void
    {
        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('they do not take part');

        $this->challenge(invitees: [], visibility: Visibility::Open)->join(World::ana(), self::at());
    }

    public function testAChallengeTakesAHundredParticipants(): void
    {
        $challenge = $this->challenge(invitees: [], visibility: Visibility::Open);

        for ($n = 1; $n <= Challenge::MAX_PARTICIPANTS; ++$n) {
            $challenge = $challenge->join(ResourceReference::of('member', 'p'.$n), self::at());
        }

        self::assertCount(Challenge::MAX_PARTICIPANTS, $challenge->participants);

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('holds 100 participants');

        $challenge->join(World::eve(), self::at());
    }

    public function testAMemberWhoDeclinedMayStillTakePart(): void
    {
        $declined = $this->challenge()->decline(World::bea(), self::at());
        self::assertSame(['member:bea'], $declined->toArray()['declined']);
        self::assertSame($declined, $declined->decline(World::bea(), self::at()));

        $joined = $declined->join(World::bea(), self::at());

        self::assertSame([], $joined->toArray()['declined']);
        self::assertSame(['member:bea'], $joined->toArray()['participants']);
    }

    public function testDecliningIsForTheInvitedBeforeTheEnd(): void
    {
        $challenge = $this->challenge();

        foreach ([
            static fn (): Challenge => $challenge->decline(World::eve(), self::at()),
            static fn (): Challenge => $challenge->decline(World::bea(), $challenge->endsAt),
            static fn (): Challenge => $challenge->join(World::bea(), self::at())->decline(World::bea(), self::at()),
            static fn (): Challenge => $challenge->freeze()->decline(World::bea(), self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (ChallengeRefused) {
                self::assertSame(1, $challenge->version);
            }
        }
    }

    public function testAParticipantSubmitsFiveProofsAtMost(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->join(World::cy(), self::at());

        for ($n = 1; $n <= Challenge::MAX_PROOFS_PER_PARTICIPANT; ++$n) {
            $challenge = $challenge->submit('p'.$n, World::bea(), 'upload:'.$n, self::at());
        }

        self::assertSame($challenge, $challenge->submit('p1', World::bea(), 'upload:1', self::at()));
        self::assertSame(ProofState::Pending, $challenge->proof('p5')?->state);
        self::assertSame('2026-11-14T01:00:00.000000Z', $challenge->toArray()['proofs'][0]['submitted_at']);
        self::assertSame(9, $challenge->submit('q1', World::cy(), 'upload:q', self::at())->version);

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('holds 5 proofs of member:bea');

        $challenge->submit('p6', World::bea(), 'upload:6', self::at());
    }

    public function testAChallengeHoldsTwoHundredProofs(): void
    {
        $challenge = self::challenge(invitees: [], visibility: Visibility::Open);

        for ($n = 0; $n < Challenge::MAX_PROOFS; ++$n) {
            $member = ResourceReference::of('member', 'p'.intdiv($n, Challenge::MAX_PROOFS_PER_PARTICIPANT));
            $challenge = $challenge->join($member, self::at())->submit('p'.$n, $member, 'upload:'.$n, self::at());
        }

        self::assertCount(Challenge::MAX_PROOFS, $challenge->proofs);

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('holds 200 proofs');

        $challenge->join(World::eve(), self::at())->submit('extra', World::eve(), 'upload:x', self::at());
    }

    public function testAProofIdentifierNamesOneProof(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at());

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('The proof "p1" of the challenge "c1" exists already');

        $challenge->submit('p1', World::bea(), 'upload:2', self::at());
    }

    public function testProofsAreSubmittedByParticipantsBeforeTheEnd(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at());

        foreach ([
            static fn (): Challenge => $challenge->submit('p1', World::cy(), 'upload:1', self::at()),
            static fn (): Challenge => $challenge->submit('p1', World::bea(), 'upload:1', $challenge->endsAt),
            static fn (): Challenge => $challenge->freeze()->submit('p1', World::bea(), 'upload:1', self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (ChallengeRefused) {
                self::assertSame([], $challenge->proofs);
            }
        }

        self::assertCount(1, $challenge->submit('p1', World::bea(), 'upload:1', $challenge->endsAt->modify('-1 second'))->proofs);
    }

    public function testTheCreatorReviewsUntilTheReviewEnds(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at());

        $won = $challenge->approve('p1', World::ana(), false, $challenge->reviewEndsAt->modify('-1 second'));

        self::assertSame(ChallengeState::Won, $won->state);
        self::assertSame('p1', $won->winningProof);
        self::assertSame('2026-11-24T00:59:59.000000Z', $won->toArray()['settled_at']);
        self::assertSame($won, $won->approve('p1', World::ana(), false, $challenge->reviewEndsAt->modify('+1 year')));
        self::assertTrue($won->state->isSettled());

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('The time to review a proof in the challenge "c1" is over.');

        $challenge->approve('p1', World::ana(), false, $challenge->reviewEndsAt);
    }

    public function testADecidedProofIsNotDecidedAgain(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at())->reject('p1', World::ana(), false, self::at());

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('is decided already');

        $challenge->approve('p1', World::ana(), false, self::at());
    }

    public function testAnotherProofOfAWonChallengeIsNotReviewed(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->join(World::cy(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at())
            ->submit('p2', World::cy(), 'upload:2', self::at())
            ->approve('p1', World::ana(), false, self::at());

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('is won: its proofs are not reviewed');

        $challenge->approve('p2', World::ana(), false, self::at());
    }

    public function testAnUnknownProofIsRefused(): void
    {
        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('The challenge "c1" has no proof "p9".');

        $this->challenge()->approve('p9', World::ana(), false, self::at());
    }

    public function testAModeratorJudgesOnlyWhenTheProofsAreLeftToModerators(): void
    {
        $challenge = $this->challenge(validation: Validation::Moderator)->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at());

        $rejected = $challenge->reject('p1', World::mo(), true, self::at());
        self::assertSame(ProofState::Rejected, $rejected->proof('p1')?->state);
        self::assertSame(ChallengeState::Open, $rejected->state);

        try {
            $challenge->approve('p1', World::ana(), false, self::at());
            self::fail('The creator does not judge.');
        } catch (ChallengeRefused $challengeRefused) {
            self::assertStringContainsString('member:ana does not judge', $challengeRefused->getMessage());
        }

        $this->expectException(ChallengeRefused::class);

        $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at())->approve('p1', World::mo(), true, self::at());
    }

    public function testVotesAcceptAProofWhenTheVotesNeededApproveIt(): void
    {
        $challenge = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())->join(World::cy(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at());

        $one = $challenge->approve('p1', World::cy(), false, self::at());
        self::assertSame(['member:cy'], $one->proof('p1')?->approvals);
        self::assertSame(ProofState::Pending, $one->proof('p1')->state);
        self::assertSame($one, $one->approve('p1', World::cy(), false, self::at()));

        $two = $one->approve('p1', World::ana(), false, self::at());
        self::assertSame(ProofState::Accepted, $two->proof('p1')?->state);
        self::assertSame(ChallengeState::Won, $two->state);
        self::assertTrue($two->winner?->equals(World::bea()));
    }

    public function testVotesRejectAProofWhenTheVotesNeededRejectIt(): void
    {
        $challenge = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())->join(World::cy(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at());

        $one = $challenge->reject('p1', World::cy(), false, self::at());
        self::assertSame(['member:cy'], $one->proof('p1')?->rejections);
        self::assertSame($one, $one->reject('p1', World::cy(), false, self::at()));

        try {
            $one->approve('p1', World::cy(), false, self::at());
            self::fail('A vote is not changed.');
        } catch (ChallengeRefused $challengeRefused) {
            self::assertStringContainsString('the other way', $challengeRefused->getMessage());
        }

        $two = $one->reject('p1', World::ana(), false, self::at());
        self::assertSame(ProofState::Rejected, $two->proof('p1')?->state);
        self::assertSame(ChallengeState::Open, $two->state);
    }

    public function testTheParticipantOfAProofAndOutsidersDoNotVote(): void
    {
        $challenge = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at());

        foreach ([World::bea(), World::eve(), World::mo()] as $member) {
            try {
                $challenge->approve('p1', $member, true, self::at());
                self::fail(\sprintf('%s does not vote.', $member->toString()));
            } catch (ChallengeRefused $challengeRefused) {
                self::assertStringContainsString('may not vote', $challengeRefused->getMessage());
            }
        }

        self::assertTrue($challenge->mayVote(World::ana(), $challenge->proofs[0]));
    }

    public function testAVoteAfterTheReviewIsRefused(): void
    {
        $challenge = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at());

        $this->expectException(ChallengeRefused::class);

        $challenge->reject('p1', World::ana(), false, $challenge->reviewEndsAt);
    }

    public function testAVoteOnARemovedProofIsRefused(): void
    {
        $challenge = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at())->removeProof('p1');

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('is decided already');

        $challenge->approve('p1', World::ana(), false, self::at());
    }

    public function testTheCreatorCancelsBeforeAnyProof(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at());

        $cancelled = $challenge->cancel(World::ana(), self::at());
        self::assertSame(ChallengeState::Cancelled, $cancelled->state);
        self::assertSame('2026-11-14T01:00:00.000000Z', $cancelled->toArray()['settled_at']);
        self::assertSame($cancelled, $cancelled->cancel(World::ana(), self::at()));

        foreach ([
            static fn (): Challenge => $challenge->cancel(World::bea(), self::at()),
            static fn (): Challenge => $challenge->submit('p1', World::bea(), 'upload:1', self::at())->cancel(World::ana(), self::at()),
            static fn (): Challenge => $challenge->freeze()->cancel(World::ana(), self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (ChallengeRefused) {
                self::assertSame(ChallengeState::Open, $challenge->state);
            }
        }
    }

    public function testAChallengeExpiresFromTheEndOfItsReview(): void
    {
        $challenge = $this->challenge();

        self::assertFalse($challenge->isDue($challenge->reviewEndsAt->modify('-1 second')));
        self::assertTrue($challenge->isDue($challenge->reviewEndsAt));
        self::assertFalse($challenge->freeze()->isDue($challenge->reviewEndsAt));

        $expired = $challenge->expire($challenge->reviewEndsAt);
        self::assertSame(ChallengeState::Expired, $expired->state);
        self::assertSame($expired, $expired->expire($challenge->reviewEndsAt));
        self::assertFalse($expired->isDue($challenge->reviewEndsAt));

        try {
            $challenge->expire($challenge->reviewEndsAt->modify('-1 second'));
            self::fail('The review is not over.');
        } catch (ChallengeRefused $challengeRefused) {
            self::assertStringContainsString('still open', $challengeRefused->getMessage());
        }

        $this->expectException(ChallengeRefused::class);

        $challenge->freeze()->expire($challenge->reviewEndsAt);
    }

    public function testFreezingAndUnfreezing(): void
    {
        $challenge = $this->challenge();

        $frozen = $challenge->freeze();
        self::assertSame(ChallengeState::Frozen, $frozen->state);
        self::assertSame(2, $frozen->version);
        self::assertSame($frozen, $frozen->freeze());

        $open = $frozen->unfreeze();
        self::assertSame(ChallengeState::Open, $open->state);
        self::assertSame(3, $open->version);
        self::assertSame($open, $open->unfreeze());

        foreach ([
            static fn (): Challenge => $challenge->cancel(World::ana(), self::at())->freeze(),
            static fn (): Challenge => $challenge->cancel(World::ana(), self::at())->unfreeze(),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (ChallengeRefused $challengeRefused) {
                self::assertStringContainsString('is cancelled', $challengeRefused->getMessage());
            }
        }
    }

    public function testRemovingAnOpenOrFrozenChallenge(): void
    {
        $challenge = $this->challenge();

        $removed = $challenge->remove(self::at());
        self::assertSame(ChallengeState::Removed, $removed->state);
        self::assertSame($removed, $removed->remove(self::at()));
        self::assertSame(ChallengeState::Removed, $challenge->freeze()->remove(self::at())->state);
        self::assertSame('2026-11-14T01:00:00.000000Z', $removed->toArray()['settled_at']);

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('it cannot be removed');

        $challenge->expire($challenge->reviewEndsAt)->remove(self::at());
    }

    public function testRemovingAProofWaitingForItsReview(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at());

        $removed = $challenge->freeze()->removeProof('p1');
        self::assertSame(ProofState::Removed, $removed->proof('p1')?->state);
        self::assertSame($removed, $removed->removeProof('p1'));

        foreach ([
            static fn (): Challenge => $challenge->removeProof('p9'),
            static fn (): Challenge => $challenge->reject('p1', World::ana(), false, self::at())->removeProof('p1'),
            static fn (): Challenge => $challenge->approve('p1', World::ana(), false, self::at())->removeProof('p1'),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (ChallengeRefused) {
                self::assertSame(ProofState::Pending, $challenge->proof('p1')?->state);
            }
        }
    }

    public function testEachMoveOfTheProofsRaisesTheVersionByOne(): void
    {
        $challenge = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())->join(World::cy(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at())->submit('p2', World::cy(), 'upload:2', self::at());
        $version = $challenge->version;

        $approved = $challenge->approve('p1', World::cy(), false, self::at());
        $rejected = $challenge->reject('p1', World::cy(), false, self::at());

        self::assertSame($version + 1, $approved->version);
        self::assertSame($version + 1, $rejected->version);
        self::assertSame($version + 2, $approved->approve('p1', World::ana(), false, self::at())->version);
        self::assertSame($version + 2, $rejected->reject('p1', World::ana(), false, self::at())->version);
        self::assertSame($version + 1, $challenge->removeProof('p1')->version);
    }

    public function testEachDeclineKeepsThePreviousOnesAndRaisesTheVersionByOne(): void
    {
        $challenge = $this->challenge();

        $one = $challenge->decline(World::bea(), self::at());
        $two = $one->decline(World::cy(), self::at());

        self::assertSame($challenge->version + 1, $one->version);
        self::assertSame($challenge->version + 2, $two->version);
        self::assertSame(['member:bea', 'member:cy'], array_map(static fn (ResourceReference $member): string => $member->toString(), $two->declined));
    }

    public function testTakingPartAfterDecliningLeavesTheOtherDeclinedAsAList(): void
    {
        $joined = $this->challenge()->decline(World::bea(), self::at())->decline(World::cy(), self::at())->join(World::bea(), self::at());

        self::assertCount(1, $joined->declined);
        self::assertTrue(array_is_list($joined->declined));
        self::assertTrue($joined->declined[0]->equals(World::cy()));
    }

    public function testASettledOrFrozenChallengeTakesNoOneMore(): void
    {
        $challenge = $this->challenge();

        foreach ([$challenge->freeze(), $challenge->cancel(World::ana(), self::at()), $challenge->remove(self::at())] as $n => $closed) {
            try {
                $closed->join(World::bea(), self::at());
                self::fail(\sprintf('Challenge %d takes no one more.', $n));
            } catch (ChallengeRefused) {
                self::assertFalse($closed->isParticipant(World::bea()));
            }
        }
    }

    public function testTheProofsOfASettledChallengeStayAsTheyAre(): void
    {
        $removed = $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at())->remove(self::at());

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('its proofs stay as they are');

        $removed->removeProof('p1');
    }

    public function testAPseudonymTakesTheVotesOfTheMemberOnTheProofsOfOthers(): void
    {
        $voted = $this->challenge(validation: Validation::Vote, votes: 2)->join(World::bea(), self::at())->join(World::cy(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at())
            ->approve('p1', World::cy(), false, self::at())->reject('p1', World::ana(), false, self::at());
        $pseudonym = ResourceReference::of('erased', 'x1');

        $cy = $voted->pseudonymized(World::cy(), $pseudonym)->proof('p1');
        self::assertSame(['erased:x1'], $cy?->approvals);
        self::assertSame(['member:ana'], $cy->rejections);

        $ana = $voted->pseudonymized(World::ana(), $pseudonym)->proof('p1');
        self::assertSame(['member:cy'], $ana?->approvals);
        self::assertSame(['erased:x1'], $ana->rejections);

        $bea = $voted->pseudonymized(World::bea(), $pseudonym)->proof('p1');
        self::assertSame(['member:cy'], $bea?->approvals);
        self::assertSame(['member:ana'], $bea->rejections);
        self::assertSame('erased:x1', $bea->evidence);
        self::assertTrue($bea->participant->equals($pseudonym));
    }

    public function testAProofHoldsAnIdentifierAndEvidenceTheLibraryTakes(): void
    {
        self::assertSame('upload:1', new Proof('p1', World::bea(), 'upload:1', self::at())->evidence);

        foreach ([['p 1', 'upload:1'], ['', 'upload:1'], ['p1', ''], ['p1', 'upload 1']] as [$id, $evidence]) {
            try {
                new Proof($id, World::bea(), $evidence, self::at());
                self::fail(\sprintf('The proof "%s" of "%s" is refused.', $id, $evidence));
            } catch (InvalidChallenge $invalidChallenge) {
                self::assertNotSame('', $invalidChallenge->getMessage());
            }
        }
    }

    public function testAProofIsFoundByItsIdentifier(): void
    {
        $challenge = $this->challenge()->join(World::bea(), self::at())->submit('p1', World::bea(), 'upload:1', self::at())->submit('p2', World::bea(), 'upload:2', self::at());

        self::assertSame('upload:2', $challenge->proof('p2')?->evidence);
        self::assertNull($challenge->proof('p3'));
        self::assertSame(['p1', 'p2'], array_map(static fn (Proof $proof): string => $proof->id, $challenge->reject('p1', World::ana(), false, self::at())->proofs));
    }

    /**
     * @param list<ResourceReference>|null $invitees
     */
    private function challenge(string $ends = '+7 days', int $prize = 100, ?array $invitees = null, Validation $validation = Validation::Creator, int $votes = 1, Visibility $visibility = Visibility::Friends, ChallengeRules $rules = new ChallengeRules()): Challenge
    {
        return Challenge::create($this->draft('c1', $ends, $prize, $invitees, $validation, $votes, $visibility), World::ana(), self::at(), $rules);
    }

    /**
     * @param list<ResourceReference>|null $invitees
     */
    private function draft(string $id = 'c1', string $ends = '+7 days', int $prize = 100, ?array $invitees = null, Validation $validation = Validation::Creator, int $votes = 1, Visibility $visibility = Visibility::Friends): ChallengeDraft
    {
        return new ChallengeDraft($id, 'Paint', new Prize($prize), self::at()->modify($ends), $invitees ?? [World::bea(), World::cy()], $visibility, $validation, '', $votes);
    }

    private static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 20:00:00 America/Toronto');
    }
}
