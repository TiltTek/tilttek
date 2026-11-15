<?php

declare(strict_types=1);

/*
 * The fuzz target of the state machine of a challenge, run by `phil-dev fuzz`: each byte of the input is a move — a
 * member joins, declines, submits, approves, rejects, cancels, a moderator freezes, unfreezes or removes, time passes,
 * the due challenges expire, a member is erased —, by one of six members or by a reference of the kind `erased`, a
 * pseudonym the challenge holds or a new one. Whatever the moves, no point is made or lost, the prize is held exactly
 * while the challenge is open or frozen, a won challenge paid its winner, and the challenge reads back as it was
 * written. A move by an `erased` reference is refused with `InvalidChallenge` and changes nothing. A refused move
 * throws `FriendChallengesException`, never another exception.
 */

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\ChallengeState;
use Tilttek\FriendChallenges\Escrow\InMemoryPrizeEscrow;
use Tilttek\FriendChallenges\Exception\FriendChallengesException;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Proof;
use Tilttek\FriendChallenges\Pseudonym;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Store\InMemoryFriendship;
use Tilttek\FriendChallenges\Store\InMemoryModeration;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    if ('' === $input) {
        return;
    }

    $members = array_map(static fn (string $name): ResourceReference => ResourceReference::of('member', $name), ['ana', 'bea', 'cy', 'dee', 'eve', 'mo']);
    [$ana, $bea, $cy, $dee, $eve, $mo] = $members;
    $clock = new MockClock('2026-11-14 01:00:00 UTC');
    $escrow = new InMemoryPrizeEscrow();
    $escrow->fund($ana, 1000);

    $friends = new InMemoryFriendship();
    $friends->befriend($ana, $bea);
    $friends->befriend($ana, $cy);
    $friends->befriend($ana, $dee);

    $service = new FriendChallenges(new InMemoryChallengeStore(), $escrow, $friends, new InMemoryModeration($mo), clock: $clock);

    $setup = ord($input[0]);
    $validation = [Validation::Creator, Validation::Vote, Validation::Moderator][$setup % 3];
    $open = 0 !== ($setup & 4);
    $draft = new ChallengeDraft('c1', 'Paint', new Prize(100), $clock->now()->modify('+7 days'), $open ? [] : [$bea, $cy, $dee], $open ? Visibility::Open : Visibility::Friends, $validation, '', Validation::Vote === $validation ? 1 + ($setup >> 3) % 3 : 1);
    $service->create($draft, $ana);

    for ($i = 1, $length = min(strlen($input), 200); $i < $length; ++$i) {
        $byte = ord($input[$i]);
        $erased = $byte >= 240;
        $before = $service->get('c1');
        $actor = $erased ? challengeErasedReference($before) : $members[intdiv($byte, 16) % 6];
        $proof = 'p'.($byte % 4);
        $snapshot = challengeSnapshot($before, $escrow, $members);
        $byActor = !in_array($byte % 14, [11, 13], true);

        try {
            match ($byte % 14) {
                0 => $service->join('c1', $actor),
                1 => $service->decline('c1', $actor),
                2, 3 => $service->submitProof('c1', $proof, $actor, 'upload:'.$actor->id),
                4, 5 => $service->approve('c1', $proof, $actor),
                6 => $service->reject('c1', $proof, $actor),
                7 => $service->cancel('c1', $actor),
                8 => $service->freeze('c1', $actor),
                9 => $service->unfreeze('c1', $actor),
                10 => 0 === $byte % 2 ? $service->remove('c1', $actor) : $service->removeProof('c1', $proof, $actor),
                11 => $clock->sleep(86_400 * (1 + $byte % 5)),
                12 => $service->erase($actor),
                default => $service->expireDue(),
            };

            if ($erased && $byActor) {
                throw new LogicException(sprintf('A move by %s was taken.', $actor->toString()));
            }
        } catch (InvalidChallenge $invalidChallenge) {
            if ($erased && $byActor && challengeSnapshot($service->get('c1'), $escrow, $members) !== $snapshot) {
                throw new LogicException(sprintf('A move by %s was refused after a change: %s', $actor->toString(), $invalidChallenge->getMessage()), 0, $invalidChallenge);
            }
        } catch (FriendChallengesException $friendChallengesException) {
            if ($erased && $byActor) {
                throw new LogicException(sprintf('A move by %s was not refused as erased: %s', $actor->toString(), $friendChallengesException->getMessage()), 0, $friendChallengesException);
            }
        }

        $challenge = $service->get('c1');
        $total = $escrow->held('friend-challenge:c1');

        foreach ($members as $member) {
            $total += $escrow->balance($member);
        }

        if (1000 !== $total) {
            throw new LogicException(sprintf('The points add up to %d, not 1000.', $total));
        }

        if ($challenge->state->isSettled() === (100 === $escrow->held('friend-challenge:c1'))) {
            throw new LogicException(sprintf('A %s challenge holds %d points.', $challenge->state->value, $escrow->held('friend-challenge:c1')));
        }

        if (ChallengeState::Won === $challenge->state && (!$challenge->winner instanceof ResourceReference || (!Pseudonym::isErased($challenge->winner) && $escrow->balance($challenge->winner) < 100))) {
            throw new LogicException('The winner of the challenge was not paid.');
        }

        if ($challenge::fromArray($challenge->toArray())->toArray() !== $challenge->toArray()) {
            throw new LogicException('The challenge written does not read back the same.');
        }
    }
});
$config->setMaxLen(200);

/**
 * A reference of the kind `erased`: a pseudonym the challenge holds, when it holds one, or a new one.
 */
function challengeErasedReference(Challenge $challenge): ResourceReference
{
    foreach ([$challenge->creator, ...$challenge->invitees, ...$challenge->participants, ...array_map(static fn (Proof $proof): ResourceReference => $proof->participant, $challenge->proofs)] as $member) {
        if (Pseudonym::isErased($member)) {
            return $member;
        }
    }

    return Pseudonym::draw();
}

/**
 * What a refused move leaves as it was: the challenge, the prize held and every balance.
 *
 * @param list<ResourceReference> $members
 */
function challengeSnapshot(Challenge $challenge, InMemoryPrizeEscrow $escrow, array $members): string
{
    return serialize([$challenge->toArray(), $escrow->held('friend-challenge:c1'), array_map($escrow->balance(...), $members)]);
}
