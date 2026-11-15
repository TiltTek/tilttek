<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use Closure;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\Check;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Pseudonym;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

/**
 * A reference of the kind `erased` stands only for a member `erase()` took out: one a caller gives is refused before
 * anything is written or any point moves.
 */
#[CoversClass(Bounties::class)]
#[CoversClass(BountyDraft::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidBounty::class)]
final class ErasedReferencesTest extends TestCase
{
    public function testADraftOnAnErasedSubjectIsRefused(): void
    {
        $w = new World();
        $subject = ResourceReference::of('erased', '00112233aabbccdd');

        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains('The subject of a bounty is erased:00112233aabbccdd, a pseudonym the library draws for an erased member; it is never taken from a caller.');

        new BountyDraft('b1', $subject, BountyReason::Detail, new Prize(100), $w->in('+7 days'));
    }

    public function testAnErasedSponsorOffersNothing(): void
    {
        $w = new World();
        $sponsor = Pseudonym::draw();

        $this->refused($w, 'The sponsor of a bounty', $sponsor, static fn (): Bounty => $w->bounties->offer($w->draft('b2'), $sponsor));

        self::assertNull($w->store->get('b2'));
        self::assertSame(0, $w->points->held('bounty:b2'));
    }

    public function testThePseudonymOfAnErasedSponsorDoesNotGiveTheirBountyBack(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->erase(World::ana());
        $w->events->take();

        $pseudonym = $w->bounties->get('b1')->sponsor;

        $this->refused($w, 'The sponsor of a bounty', $pseudonym, static fn (): Bounty => $w->bounties->offer($w->draft(), $pseudonym));
    }

    public function testAnErasedClaimantClaimsNothing(): void
    {
        $w = $this->offered();
        $claimant = Pseudonym::draw();

        $this->refused($w, 'A claimant', $claimant, static fn (): Bounty => $w->bounties->claim('b1', 'answer-1', $claimant, 'answer:901'));
    }

    public function testThePseudonymOfAnErasedClaimantDoesNotSendTheirClaimAgain(): void
    {
        $w = $this->offered();
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->award('b1', 'award-1', 'answer-1', 100, World::ana());
        $w->bounties->erase(World::bea());
        $w->events->take();

        $pseudonym = $w->bounties->get('b1')->claims[0]->claimant;
        self::assertTrue(Pseudonym::isErased($pseudonym));

        $this->refused($w, 'A claimant', $pseudonym, static fn (): Bounty => $w->bounties->claim('b1', 'answer-3', $pseudonym, 'answer:903'));
    }

    public function testAnErasedSponsorAwardsNothing(): void
    {
        $w = $this->offered();
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->events->take();

        $sponsor = Pseudonym::draw();

        $this->refused($w, 'The sponsor of a bounty', $sponsor, static fn (): Bounty => $w->bounties->award('b1', 'award-1', 'answer-1', 100, $sponsor));
    }

    public function testAnErasedSponsorClosesNothing(): void
    {
        $w = $this->offered();
        $sponsor = Pseudonym::draw();

        $this->refused($w, 'The sponsor of a bounty', $sponsor, static fn (): Bounty => $w->bounties->close('b1', $sponsor));
    }

    public function testAnErasedSponsorCancelsNothing(): void
    {
        $w = $this->offered();
        $sponsor = Pseudonym::draw();

        $this->refused($w, 'The sponsor of a bounty', $sponsor, static fn (): Bounty => $w->bounties->cancel('b1', $sponsor));
    }

    public function testAPseudonymIsNotErasedAgain(): void
    {
        $w = $this->offered();
        $pseudonym = Pseudonym::draw();

        $this->refused($w, 'The member to erase', $pseudonym, static fn (): int => $w->bounties->erase($pseudonym));
    }

    public function testThePseudonymOfAnErasedSponsorIsNotErasedAgain(): void
    {
        $w = $this->offered();
        $w->bounties->erase(World::ana());
        $w->events->take();

        $pseudonym = $w->bounties->get('b1')->sponsor;

        $this->refused($w, 'The member to erase', $pseudonym, static fn (): int => $w->bounties->erase($pseudonym));
    }

    private function offered(): World
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->events->take();

        return $w;
    }

    /**
     * The move is refused as one by a reference of the kind `erased`, and nothing changed: the bounty, the points held,
     * the balances and the events.
     *
     * @param Closure(): mixed $move
     */
    private function refused(World $w, string $what, ResourceReference $reference, Closure $move): void
    {
        $before = $this->state($w);

        try {
            $move();
            self::fail(\sprintf('A move by %s was taken.', $reference->toString()));
        } catch (InvalidBounty $invalidBounty) {
            self::assertSame(\sprintf('%s is %s, a pseudonym the library draws for an erased member; it is never taken from a caller.', $what, $reference->toString()), $invalidBounty->getMessage());
        }

        self::assertSame($before, $this->state($w));
        self::assertSame([], $w->events->events);
    }

    /**
     * @return array{?array<string, mixed>, int, list<int>}
     */
    private function state(World $w): array
    {
        return [
            $w->store->get('b1')?->toArray(),
            $w->points->held('bounty:b1'),
            array_map($w->points->balance(...), [World::ana(), World::bea(), World::cy(), World::dee()]),
        ];
    }
}
