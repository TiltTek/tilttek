<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\ChangeKind;
use Tilttek\GameCatalog\Correction;
use Tilttek\GameCatalog\Corrections;
use Tilttek\GameCatalog\CorrectionStatus;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\CorrectionConflict;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\Experience;
use Tilttek\GameCatalog\Field;
use Tilttek\GameCatalog\Polls;
use Tilttek\GameCatalog\Range;
use Tilttek\GameCatalog\Reviews;
use Tilttek\GameCatalog\SourcedEntry;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameCatalog\Tests\Unit\Fixture\CountedPlays;
use Tilttek\GameCatalog\Tests\Unit\Fixture\KeptReviews;
use Tilttek\GameCatalog\Tests\Unit\Fixture\Observed;

#[CoversClass(Corrections::class)]
#[CoversClass(CorrectionConflict::class)]
#[CoversClass(Polls::class)]
#[CoversClass(Reviews::class)]
final class CorrectionsTest extends TestCase
{
    private InMemoryCatalogStore $store;

    private MockClock $clock;

    private Observed $observed;

    private Catalog $catalog;

    private Corrections $corrections;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryCatalogStore();
        $this->clock = new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC'));
        $this->observed = new Observed();
        $this->catalog = new Catalog($this->store, $this->clock);
        $this->corrections = new Corrections($this->store, $this->clock, $this->observed);
        $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide', year: 2026, players: new Range(2, 4))));
    }

    public function testAMemberProposesACorrection(): void
    {
        $correction = $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025, players: new Range(2, 4)));

        self::assertSame([Field::Year], $correction->fields);
        self::assertSame(1, $correction->version);
        self::assertEquals($this->clock->now(), $correction->proposedAt);
        self::assertSame(2026, $correction->base->year);
        self::assertSame(['proposed e-1 c-1'], $this->observed->calls);
        self::assertSame([$correction], $this->corrections->waiting('e-1'));
        self::assertSame([$correction], $this->corrections->waiting());
        self::assertSame([], $this->corrections->waiting('e-2'));
        self::assertSame(2026, $this->catalog->get('e-1')->details()->year);
    }

    public function testACorrectionOfAnUnknownEntryIsRefused(): void
    {
        $this->expectException(EntryNotFound::class);
        $this->corrections->propose('c-1', 'e-9', 'm-1', new Details('Tide'));
    }

    public function testAMemberHasAtMostTwentyCorrectionsWaiting(): void
    {
        for ($i = 1; $i <= Corrections::MAX_PENDING; ++$i) {
            $this->corrections->propose('c-'.$i, 'e-1', 'm-1', new Details('Tide '.$i));
        }

        $this->corrections->propose('other', 'e-1', 'm-2', new Details('Tide again'));
        $this->corrections->refuse('c-1', 'admin');
        $this->corrections->propose('c-21', 'e-1', 'm-1', new Details('Tide 21'));

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The member m-1 has 20 corrections waiting for a decision.');
        $this->corrections->propose('c-22', 'e-1', 'm-1', new Details('Tide 22'));
    }

    public function testAnAcceptedCorrectionOutlivesTheSource(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025, players: new Range(2, 5)));
        $this->clock->modify('+1 day');

        $entry = $this->corrections->accept('c-1', 'admin');

        self::assertSame(2025, $entry->details()->year);
        self::assertSame(5, $entry->details()->players?->max);
        self::assertSame(2026, $entry->source->year);
        self::assertSame([Field::Year, Field::Players], $entry->corrected);
        $correction = $this->store->correction('c-1');
        self::assertSame(CorrectionStatus::Accepted, $correction->status);
        self::assertSame('admin', $correction->decidedBy);
        self::assertEquals($this->clock->now(), $correction->decidedAt);
        self::assertSame(['proposed e-1 c-1', 'decided c-1 m-1 accepted'], $this->observed->calls);
        $change = $this->catalog->history('e-1')[0];
        self::assertSame(ChangeKind::Corrected, $change->kind);
        self::assertSame([Field::Year, Field::Players], $change->fields);
        self::assertSame('admin', $change->by);
        self::assertSame('c-1', $change->about);
        self::assertEquals($this->clock->now(), $change->at);
        self::assertSame([], $this->corrections->waiting());

        $resent = $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide (new printing)', year: 2026, players: new Range(2, 4))));
        self::assertSame('Tide (new printing)', $resent->details()->name);
        self::assertSame(2025, $resent->details()->year);
        self::assertSame(5, $resent->details()->players?->max);
    }

    public function testACorrectionIsDecidedOnce(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025));
        $this->corrections->accept('c-1', 'admin');

        try {
            $this->corrections->accept('c-1', 'admin');
            self::fail('A correction was accepted twice.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('The correction c-1 is already accepted.', $catalogRefused->getMessage());
        }

        $this->expectExceptionMessageIs('The correction c-1 is already accepted.');
        $this->corrections->refuse('c-1', 'admin');
    }

    public function testARefusedCorrectionChangesNothing(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025));
        $this->clock->modify('+1 hour');

        $refused = $this->corrections->refuse('c-1', 'admin', 'The box says 2026.');

        self::assertSame(CorrectionStatus::Refused, $refused->status);
        self::assertSame('The box says 2026.', $refused->note);
        self::assertSame('admin', $refused->decidedBy);
        self::assertEquals($this->clock->now(), $refused->decidedAt);
        self::assertSame(2, $refused->version);
        self::assertSame(2026, $this->catalog->get('e-1')->details()->year);
        self::assertSame(['proposed e-1 c-1', 'decided c-1 m-1 refused'], $this->observed->calls);
    }

    public function testAFieldChangedSinceIsAConflict(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025, players: new Range(2, 4)));
        $this->corrections->propose('c-2', 'e-1', 'm-2', new Details('Tide', year: 2024, players: new Range(1, 4)));
        $this->corrections->accept('c-1', 'admin');

        try {
            $this->corrections->accept('c-2', 'admin');
            self::fail('A correction overwrote a field changed since.');
        } catch (CorrectionConflict $correctionConflict) {
            self::assertSame([Field::Year], $correctionConflict->fields);
            self::assertSame('The correction c-2 changes year, changed on the entry since it was proposed.', $correctionConflict->getMessage());
        }

        self::assertTrue($this->store->correction('c-2')->isPending());
        $entry = $this->corrections->accept('c-2', 'admin', force: true);
        self::assertSame(2024, $entry->details()->year);
        self::assertSame(1, $entry->details()->players?->min);
    }

    public function testTheConflictListsTheFieldsChangedSince(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025, players: new Range(2, 4)));
        $this->corrections->propose('c-2', 'e-1', 'm-2', new Details('Tide 2', year: 2024, players: new Range(2, 4)));
        $this->corrections->accept('c-1', 'admin');

        try {
            $this->corrections->accept('c-2', 'admin');
            self::fail('A correction overwrote a field changed since.');
        } catch (CorrectionConflict $correctionConflict) {
            self::assertSame([Field::Year], $correctionConflict->fields);
        }
    }

    public function testAFieldChangedSinceToTheSameValueIsNoConflict(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025, players: new Range(2, 4)));
        $this->corrections->propose('c-2', 'e-1', 'm-2', new Details('Tide', year: 2025, players: new Range(2, 4), minimumAge: 10));
        $this->corrections->accept('c-1', 'admin');

        $entry = $this->corrections->accept('c-2', 'admin');

        self::assertSame(2025, $entry->details()->year);
        self::assertSame(10, $entry->details()->minimumAge);
    }

    public function testTheConflictNamesEveryFieldChangedSince(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide 2', year: 2025));
        $this->corrections->propose('c-2', 'e-1', 'm-2', new Details('Tide 3', year: 2024));
        $this->corrections->accept('c-1', 'admin');

        $this->expectException(CorrectionConflict::class);
        $this->expectExceptionMessageIs('The correction c-2 changes name, year, changed on the entry since it was proposed.');
        $this->corrections->accept('c-2', 'admin');
    }

    public function testAFieldGoesBackToTheSource(): void
    {
        $this->corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025, players: new Range(2, 5)));
        $this->corrections->accept('c-1', 'admin');

        $entry = $this->corrections->revert('e-1', Field::Year, 'admin');

        self::assertSame(2026, $entry->details()->year);
        self::assertSame([Field::Players], $entry->corrected);
        $change = $this->catalog->history('e-1')[0];
        self::assertSame(ChangeKind::Reverted, $change->kind);
        self::assertSame([Field::Year], $change->fields);
        self::assertSame('admin', $change->by);

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The field year of the entry e-1 is not corrected.');
        $this->corrections->revert('e-1', Field::Year, 'admin');
    }

    public function testPollsKeepOneBallotPerMember(): void
    {
        $polls = new Polls($this->store);

        $polls->vote(new Ballot('e-1', 'm-1', weight: 2));
        $polls->vote(new Ballot('e-1', 'm-1', weight: 4));
        $polls->vote(new Ballot('e-1', 'm-2', weight: 3));

        self::assertSame(4, $polls->ballot('e-1', 'm-1')?->weight);
        self::assertSame(350, $polls->results('e-1')->weight());
        $polls->withdraw('e-1', 'm-1');
        self::assertNull($polls->ballot('e-1', 'm-1'));
        self::assertSame(300, $polls->results('e-1')->weight());

        $this->expectException(EntryNotFound::class);
        $polls->vote(new Ballot('e-9', 'm-1', weight: 2));
    }

    public function testTheExperienceOfAReviewerIsFrozen(): void
    {
        $plays = new CountedPlays();
        $plays->played['m-1 e-1'] = 12;
        $plays->ran['m-1 e-1'] = 3;
        $kept = new KeptReviews();
        $reviews = new Reviews($this->store, $plays, $kept, $this->clock);

        $experience = $reviews->experience('r-1', 'e-1', 'm-1');

        self::assertSame(12, $experience->played);
        self::assertSame(3, $experience->ran);
        self::assertEquals($this->clock->now(), $experience->at);
        self::assertSame(['e-1', 'm-1', $experience], $kept->kept['r-1']);

        $plays->played['m-1 e-1'] = 20;
        self::assertSame($experience, $reviews->experience('r-1', 'e-1', 'm-1'));
        self::assertSame(20, $reviews->experience('r-2', 'e-1', 'm-1')->played);
        self::assertInstanceOf(Experience::class, $kept->experience('r-2'));

        $this->expectException(EntryNotFound::class);
        $reviews->experience('r-3', 'e-9', 'm-1');
    }

    public function testCorrectionsWithoutObserverWork(): void
    {
        $corrections = new Corrections($this->store, $this->clock);
        $corrections->propose('c-1', 'e-1', 'm-1', new Details('Tide', year: 2025));
        $corrections->propose('c-2', 'e-1', 'm-1', new Details('Tide', year: 2024));

        self::assertSame(2025, $corrections->accept('c-1', 'admin')->details()->year);
        self::assertInstanceOf(Correction::class, $corrections->refuse('c-2', 'admin'));
    }
}
