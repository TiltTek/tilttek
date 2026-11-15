<?php

declare(strict_types=1);

/*
 * The fuzz target of the state machine of a bounty, run by `phil-dev fuzz`: each byte of the input is a move — a member
 * claims, the sponsor or another awards, closes or cancels, time passes, the due bounties are settled, a member is
 * erased —, by one of four members or by a reference of the kind `erased`, a pseudonym the bounty holds or a new one.
 * Whatever the moves, no point is made or lost, the points are held exactly while the bounty is open, the winners are
 * paid what was awarded to them, the awards never pass the amount, and the bounty reads back as it was written. A move
 * by an `erased` reference is refused with `InvalidBounty` and changes nothing. A refused move throws
 * `BountiesException`, never another exception.
 */

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\BountyState;
use Tilttek\Bounties\Claim;
use Tilttek\Bounties\Escrow\InMemoryBountyEscrow;
use Tilttek\Bounties\Exception\BountiesException;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Pseudonym;
use Tilttek\Bounties\Store\InMemoryBountyStore;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $members = array_map(static fn (string $name): ResourceReference => ResourceReference::of('member', $name), ['ana', 'bea', 'cy', 'dee']);
    $clock = new MockClock('2026-11-14 01:00:00 UTC');
    $escrow = new InMemoryBountyEscrow();
    $escrow->fund($members[0], 1000);

    $bounties = new Bounties(new InMemoryBountyStore(), $escrow, clock: $clock);
    $bounties->offer(new BountyDraft('b1', ResourceReference::of('question', '42'), BountyReason::Detail, new Prize(100), $clock->now()->modify('+7 days')), $members[0]);

    for ($i = 0, $length = min(strlen($input), 200); $i < $length; ++$i) {
        $byte = ord($input[$i]);
        $erased = $byte >= 224;
        $before = $bounties->get('b1');
        $actor = $erased ? bountyErasedReference($before) : $members[intdiv($byte, 8) % 4];
        $claim = 'c'.($byte % 3);
        $snapshot = bountySnapshot($before, $escrow, $members);
        $byActor = !in_array($byte % 9, [6, 8], true);

        try {
            match ($byte % 9) {
                0, 1 => $bounties->claim('b1', $claim, $actor, 'answer:'.$actor->id),
                2, 3 => $bounties->award('b1', 'a'.$i, $claim, 1 + intdiv($byte, 4) % 60, $actor),
                4 => $bounties->close('b1', $actor),
                5 => $bounties->cancel('b1', $actor),
                6 => $clock->sleep(86_400 * (1 + $byte % 4)),
                7 => $bounties->erase($actor),
                default => $bounties->expireDue(),
            };

            if ($erased && $byActor) {
                throw new LogicException(sprintf('A move by %s was taken.', $actor->toString()));
            }
        } catch (InvalidBounty $invalidBounty) {
            if ($erased && $byActor && bountySnapshot($bounties->get('b1'), $escrow, $members) !== $snapshot) {
                throw new LogicException(sprintf('A move by %s was refused after a change: %s', $actor->toString(), $invalidBounty->getMessage()), 0, $invalidBounty);
            }
        } catch (BountiesException $bountiesException) {
            if ($erased && $byActor) {
                throw new LogicException(sprintf('A move by %s was not refused as erased: %s', $actor->toString(), $bountiesException->getMessage()), 0, $bountiesException);
            }
        }

        $bounty = $bounties->get('b1');
        $held = $escrow->held('bounty:b1');
        $total = $held;

        foreach ($members as $member) {
            $total += $escrow->balance($member);
        }

        if (1000 !== $total) {
            throw new LogicException(sprintf('The points add up to %d, not 1000.', $total));
        }

        if ($bounty->state->isSettled() === (100 === $held) || $bounty->awarded() > 100) {
            throw new LogicException(sprintf('A %s bounty holds %d points, %d awarded.', $bounty->state->value, $held, $bounty->awarded()));
        }

        if (BountyState::Awarded === $bounty->state) {
            foreach ($bounty->payouts() as $payout) {
                if (!Pseudonym::isErased($payout->member) && $escrow->balance($payout->member) < $payout->amount) {
                    throw new LogicException(sprintf('%s was not paid %d points.', $payout->member->toString(), $payout->amount));
                }
            }
        }

        if ($bounty::fromArray($bounty->toArray())->toArray() !== $bounty->toArray()) {
            throw new LogicException('The bounty written does not read back the same.');
        }
    }
});
$config->setMaxLen(200);

/**
 * A reference of the kind `erased`: a pseudonym the bounty holds, when it holds one, or a new one.
 */
function bountyErasedReference(Bounty $bounty): ResourceReference
{
    foreach ([$bounty->sponsor, ...array_map(static fn (Claim $claim): ResourceReference => $claim->claimant, $bounty->claims)] as $member) {
        if (Pseudonym::isErased($member)) {
            return $member;
        }
    }

    return Pseudonym::draw();
}

/**
 * What a refused move leaves as it was: the bounty, the points held and every balance.
 *
 * @param list<ResourceReference> $members
 */
function bountySnapshot(Bounty $bounty, InMemoryBountyEscrow $escrow, array $members): string
{
    return serialize([$bounty->toArray(), $escrow->held('bounty:b1'), array_map($escrow->balance(...), $members)]);
}
