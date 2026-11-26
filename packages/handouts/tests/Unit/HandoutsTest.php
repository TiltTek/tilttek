<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Tests\Unit;

use Closure;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Handouts\Attachment;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Disclosure;
use Tilttek\Handouts\Exception\HandoutRefused;
use Tilttek\Handouts\Exception\InvalidHandout;
use Tilttek\Handouts\Exception\UnknownHandout;
use Tilttek\Handouts\Freshness;
use Tilttek\Handouts\Handout;
use Tilttek\Handouts\HandoutChanged;
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Limits;
use Tilttek\Handouts\MediaTypes;
use Tilttek\Handouts\Refusal;
use Tilttek\Handouts\Sighting;
use Tilttek\Handouts\StagedReveal;
use Tilttek\Handouts\Store\InMemoryHandoutStore;
use Tilttek\Handouts\Tests\Unit\Fixture\Recorder;
use Tilttek\Handouts\Trigger;
use Tilttek\Handouts\Visible;

#[CoversClass(Handouts::class)]
#[CoversClass(InMemoryHandoutStore::class)]
#[CoversClass(HandoutChanged::class)]
#[CoversClass(Visible::class)]
final class HandoutsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryHandoutStore $store;

    private Recorder $recorder;

    private Handouts $handouts;

    private ResourceReference $letter;

    private ResourceReference $table;

    private ResourceReference $gm;

    private ResourceReference $ada;

    private ResourceReference $bo;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-25 19:00:00', 'UTC');
        $this->store = new InMemoryHandoutStore();
        $this->recorder = new Recorder();
        $this->handouts = new Handouts($this->store, $this->clock, new Limits(), new MediaTypes(), [$this->recorder]);
        $this->letter = ResourceReference::of('handout', '4');
        $this->table = ResourceReference::of('campaign', '3');
        $this->gm = ResourceReference::of('player', '1');
        $this->ada = ResourceReference::of('player', '12');
        $this->bo = ResourceReference::of('player', '13');
    }

    public function testAHandoutIsCreatedConcealed(): void
    {
        $map = new Attachment(ResourceReference::of('file', '9'), 'image/png', 'A map of the mill');

        $handout = $this->handouts->create($this->letter, $this->table, "The miller's letter", 'Meet me at the mill.', [$map], $this->gm);

        self::assertSame(["The miller's letter", 'Meet me at the mill.', [$map], 1, '2026-11-25 19:00:00'], [$handout->title, $handout->text, $handout->attachments, $handout->revision, $handout->createdAt->format('Y-m-d H:i:s')]);
        self::assertSame($handout, $this->handouts->handout($this->letter));
        self::assertSame([$handout], $this->handouts->handouts($this->table));
        self::assertSame([], $this->handouts->audience($this->letter));
        self::assertFalse($this->handouts->canSee($this->letter, $this->ada));
        self::assertSame(['handout:4 campaign:3 created - player:1'], $this->recorder->summary());
        self::assertSame('2026-11-25 19:00:00', $this->recorder->changes[0]->at->format('Y-m-d H:i:s'));
    }

    public function testAReferenceIsCreatedOnce(): void
    {
        $this->create();

        $this->assertRefused(Refusal::AlreadyCreated, fn (): Handout => $this->handouts->create($this->letter, $this->table, 'Other', 'Text'));
        self::assertSame('Letter', $this->handouts->handout($this->letter)->title);
        self::assertCount(1, $this->recorder->changes);
    }

    public function testAnInvalidHandoutIsRefusedBeforeTheStoreIsRead(): void
    {
        $this->create();

        $this->expectException(InvalidHandout::class);

        $this->handouts->create($this->letter, $this->table, '', 'Text');
    }

    public function testATableHoldsAsManyHandoutsAsAllowed(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(handoutsPerTable: 2));
        $handouts->create(ResourceReference::of('handout', '1'), $this->table, 'One', 'x');
        $handouts->create(ResourceReference::of('handout', '2'), $this->table, 'Two', 'x');
        $handouts->create(ResourceReference::of('handout', '3'), ResourceReference::of('campaign', '4'), 'Elsewhere', 'x');

        $this->assertRefused(Refusal::TooManyHandouts, static fn (): Handout => $handouts->create(ResourceReference::of('handout', '5'), ResourceReference::of('campaign', '3'), 'Three', 'x'));
        self::assertNull($this->store->handout('handout:5'));
    }

    public function testAttachmentsAreBoundedByTheLimit(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(attachmentsPerHandout: 2));
        $files = [$this->attachment('1'), $this->attachment('2')];
        $handouts->create($this->letter, $this->table, 'Two maps', null, $files);

        $this->assertRefused(Refusal::TooManyAttachments, fn (): Handout => $handouts->create(ResourceReference::of('handout', '5'), $this->table, 'Three maps', null, [...$files, $this->attachment('3')]));
        $this->assertRefused(Refusal::TooManyAttachments, fn (): Handout => $handouts->edit($this->letter, 'Three maps', null, [...$files, $this->attachment('3')]));
        self::assertNull($this->store->handout('handout:5'));
        self::assertSame(1, $this->store->handout('handout:4')?->revision);
    }

    public function testAttachmentsHaveAnAllowedMediaType(): void
    {
        $svg = new Attachment(ResourceReference::of('file', '9'), 'image/svg+xml', 'A seal');

        $this->assertRefused(Refusal::MediaTypeNotAllowed, fn (): Handout => $this->handouts->create($this->letter, $this->table, 'Seal', null, [$this->attachment('1'), $svg]));
        self::assertNull($this->store->handout('handout:4'));

        $this->create();
        $this->assertRefused(Refusal::MediaTypeNotAllowed, fn (): Handout => $this->handouts->edit($this->letter, 'Seal', null, [$svg]));

        $sanitised = new Handouts($this->store, $this->clock, new Limits(), new MediaTypes(['image/svg+xml']));
        self::assertSame([$svg], $sanitised->create(ResourceReference::of('handout', '5'), $this->table, 'Seal', null, [$svg])->attachments);
    }

    public function testAnUnknownHandoutIsReported(): void
    {
        $this->expectException(UnknownHandout::class);
        $this->expectExceptionMessageIsOrContains('No handout is known as "handout:4".');

        $this->handouts->handout($this->letter);
    }

    /**
     * @return iterable<string, array{Closure(Handouts, ResourceReference): mixed}>
     */
    public static function actsOnAHandout(): iterable
    {
        $everyone = Audience::everyone();
        $player = ResourceReference::of('player', '12');

        yield 'edit' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->edit($r, 'Title', 'Text')];
        yield 'remove' => [static function (Handouts $h, ResourceReference $r): void {
            $h->remove($r);
        }];
        yield 'reveal' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->reveal($r, $everyone)];
        yield 'conceal' => [static function (Handouts $h, ResourceReference $r) use ($everyone): void {
            $h->conceal($r, $everyone);
        }];
        yield 'can see' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->canSee($r, $player)];
        yield 'audience' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->audience($r)];
        yield 'stage' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->stage($r, $everyone, Trigger::cue('x'))];
        yield 'unstage' => [static function (Handouts $h, ResourceReference $r) use ($everyone): void {
            $h->unstage($r, $everyone);
        }];
        yield 'staged' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->staged($r)];
        yield 'mark seen' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->markSeen($r, $player)];
        yield 'sightings' => [static fn (Handouts $h, ResourceReference $r): mixed => $h->sightings($r)];
    }

    /**
     * @param Closure(Handouts, ResourceReference): mixed $act
     */
    #[DataProvider('actsOnAHandout')]
    public function testEveryActNeedsAKnownHandout(Closure $act): void
    {
        $this->expectException(UnknownHandout::class);

        $act($this->handouts, $this->letter);
    }

    public function testAnEditRaisesTheRevision(): void
    {
        $this->create();
        $this->clock->modify('+1 hour');

        $edited = $this->handouts->edit($this->letter, 'Torn letter', 'Meet me', [$this->attachment('1')], $this->gm);

        self::assertSame(['Torn letter', 'Meet me', 2, '2026-11-25 20:00:00', '2026-11-25 19:00:00'], [$edited->title, $edited->text, $edited->revision, $edited->editedAt?->format('Y-m-d H:i:s'), $edited->createdAt->format('Y-m-d H:i:s')]);
        self::assertSame($edited, $this->handouts->handout($this->letter));
        self::assertSame('handout:4 campaign:3 edited - player:1', $this->recorder->summary()[1]);
        self::assertSame('2026-11-25 20:00:00', $this->recorder->changes[1]->at->format('Y-m-d H:i:s'));
    }

    public function testAHandoutIsRemovedWithEverythingItHolds(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->handouts->stage($this->letter, Audience::everyone(), Trigger::cue('crypt'));
        $this->handouts->markSeen($this->letter, $this->ada);

        $this->handouts->remove($this->letter, $this->gm);

        self::assertNull($this->store->handout('handout:4'));
        self::assertSame([], $this->store->disclosures('handout:4'));
        self::assertSame([], $this->store->stagedOf('handout:4'));
        self::assertSame([], $this->store->sightings('handout:4'));
        self::assertSame('handout:4 campaign:3 removed - player:1', $this->recorder->summary()[4]);
    }

    public function testHandoutsAreReadEarliestCreatedFirst(): void
    {
        $this->handouts->create(ResourceReference::of('handout', 'b'), $this->table, 'B', 'x');
        $this->handouts->create(ResourceReference::of('handout', 'a'), $this->table, 'A', 'x');

        $this->clock->modify('-1 minute');
        $this->handouts->create(ResourceReference::of('handout', 'c'), $this->table, 'C', 'x');
        $this->handouts->create(ResourceReference::of('handout', 'd'), ResourceReference::of('campaign', '9'), 'D', 'x');

        self::assertSame(['C', 'A', 'B'], array_map(static fn (Handout $h): string => $h->title, $this->handouts->handouts($this->table)));
        self::assertSame(3, $this->store->countHandouts('campaign:3'));
        self::assertSame(0, $this->store->countHandouts('campaign:404'));
    }

    public function testAHandoutIsRevealedToEveryone(): void
    {
        $this->create();
        $this->clock->modify('+5 minutes');

        $disclosures = $this->handouts->reveal($this->letter, Audience::everyone(), $this->gm);

        self::assertCount(1, $disclosures);
        self::assertSame(['*', 1, '2026-11-25 19:05:00', 'player:1', 'campaign:3'], [$disclosures[0]->key(), $disclosures[0]->revision, $disclosures[0]->revealedAt->format('Y-m-d H:i:s'), $disclosures[0]->by?->toString(), $disclosures[0]->table->toString()]);
        self::assertTrue($this->handouts->canSee($this->letter, $this->ada));
        self::assertTrue($this->handouts->canSee($this->letter, $this->bo));
        self::assertSame($disclosures, $this->handouts->audience($this->letter));
        self::assertSame('handout:4 campaign:3 revealed - player:1', $this->recorder->summary()[1]);
    }

    public function testAHandoutIsRevealedToSomePlayers(): void
    {
        $this->create();

        $disclosures = $this->handouts->reveal($this->letter, Audience::players($this->bo, $this->ada));

        self::assertSame(['player:13', 'player:12'], array_map(static fn (Disclosure $d): string => $d->key(), $disclosures));
        self::assertTrue($this->handouts->canSee($this->letter, $this->ada));
        self::assertTrue($this->handouts->canSee($this->letter, $this->bo));
        self::assertFalse($this->handouts->canSee($this->letter, ResourceReference::of('player', '14')));
        self::assertSame(['player:12', 'player:13'], array_map(static fn (Disclosure $d): string => $d->key(), $this->handouts->audience($this->letter)));
        self::assertSame(['handout:4 campaign:3 revealed player:13 -', 'handout:4 campaign:3 revealed player:12 -'], \array_slice($this->recorder->summary(), 1));
    }

    public function testRevealingAgainShowsTheNewRevision(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->clock->modify('+1 hour');
        $this->handouts->edit($this->letter, 'Torn letter', 'Text');

        $again = $this->handouts->reveal($this->letter, Audience::players($this->ada));

        self::assertSame([2, '2026-11-25 20:00:00'], [$again[0]->revision, $again[0]->revealedAt->format('Y-m-d H:i:s')]);
        self::assertCount(1, $this->handouts->audience($this->letter));
    }

    public function testTheAudienceGivenIsBounded(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(audiencePerHandout: 2));
        $handouts->create($this->letter, $this->table, 'Letter', 'x');

        $three = Audience::players($this->ada, $this->bo, ResourceReference::of('player', '14'));

        $this->assertRefused(Refusal::AudienceTooLarge, static fn (): array => $handouts->reveal(ResourceReference::of('handout', '4'), $three));
        $this->assertRefused(Refusal::AudienceTooLarge, static fn (): array => $handouts->stage(ResourceReference::of('handout', '4'), $three, Trigger::cue('x')));
        self::assertSame([], $this->store->disclosures('handout:4'));
        self::assertSame([], $this->store->stagedOf('handout:4'));
    }

    public function testAHandoutIsRevealedToAsManyAudiencesAsAllowed(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(audiencePerHandout: 2));
        $handouts->create($this->letter, $this->table, 'Letter', 'x');
        $handouts->reveal($this->letter, Audience::players($this->ada));
        $handouts->reveal($this->letter, Audience::players($this->ada, $this->bo));
        $handouts->reveal($this->letter, Audience::players($this->bo));

        $this->assertRefused(Refusal::TooManyRevealed, fn (): array => $handouts->reveal($this->letter, Audience::everyone()));
        self::assertCount(2, $this->store->disclosures('handout:4'));
    }

    public function testAHandoutHoldsAsManyStagedRevealsAsAllowed(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(audiencePerHandout: 1));
        $handouts->create($this->letter, $this->table, 'Letter', 'x');
        $handouts->stage($this->letter, Audience::players($this->ada), Trigger::cue('a'));
        $handouts->stage($this->letter, Audience::players($this->ada), Trigger::cue('b'));

        $this->assertRefused(Refusal::TooManyStaged, fn (): array => $handouts->stage($this->letter, Audience::players($this->bo), Trigger::cue('a')));
        self::assertSame(['b'], array_map(static fn (StagedReveal $s): ?string => $s->trigger->cue, $handouts->staged($this->letter)));
    }

    public function testAHandoutIsConcealedFromEveryone(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->handouts->reveal($this->letter, Audience::everyone());
        $this->handouts->stage($this->letter, Audience::players($this->bo), Trigger::cue('crypt'));

        $this->recorder->clear();

        $this->handouts->conceal($this->letter, Audience::everyone(), $this->gm);

        self::assertFalse($this->handouts->canSee($this->letter, $this->ada));
        self::assertFalse($this->handouts->canSee($this->letter, $this->bo));
        self::assertSame([], $this->handouts->audience($this->letter));
        self::assertCount(1, $this->handouts->staged($this->letter));
        self::assertSame(['handout:4 campaign:3 concealed - player:1', 'handout:4 campaign:3 concealed player:12 player:1'], $this->recorder->summary());
    }

    public function testAHandoutIsConcealedFromSomePlayers(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada, $this->bo));
        $this->recorder->clear();

        $this->handouts->conceal($this->letter, Audience::players($this->ada, ResourceReference::of('player', '14')));

        self::assertFalse($this->handouts->canSee($this->letter, $this->ada));
        self::assertTrue($this->handouts->canSee($this->letter, $this->bo));
        self::assertSame(['handout:4 campaign:3 concealed player:12 -'], $this->recorder->summary());
    }

    public function testAPlayerStillSeesWhatIsRevealedToEveryone(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::everyone());
        $this->handouts->reveal($this->letter, Audience::players($this->ada));

        $this->handouts->conceal($this->letter, Audience::players($this->ada));

        self::assertTrue($this->handouts->canSee($this->letter, $this->ada));
        self::assertSame(['*'], array_map(static fn (Disclosure $d): string => $d->key(), $this->handouts->audience($this->letter)));
    }

    public function testARevealIsStagedOnACue(): void
    {
        $this->create();
        $map = ResourceReference::of('handout', '5');
        $this->handouts->create($map, $this->table, 'Map', 'x');
        $elsewhere = ResourceReference::of('handout', '6');
        $this->handouts->create($elsewhere, ResourceReference::of('campaign', '9'), 'Elsewhere', 'x');
        $this->recorder->clear();

        $staged = $this->handouts->stage($this->letter, Audience::players($this->ada, $this->bo), Trigger::cue('crypt.opened'), $this->gm);
        $this->clock->modify('+1 minute');
        $this->handouts->stage($map, Audience::everyone(), Trigger::cue('crypt.opened'));
        $this->handouts->stage($elsewhere, Audience::everyone(), Trigger::cue('crypt.opened'));

        self::assertSame(['player:12', 'player:13'], array_map(static fn (StagedReveal $s): string => $s->key(), $staged));
        self::assertSame(['crypt.opened', 'campaign:3', 'player:1', '2026-11-25 19:00:00'], [$staged[0]->trigger->cue, $staged[0]->table->toString(), $staged[0]->by?->toString(), $staged[0]->stagedAt->format('Y-m-d H:i:s')]);
        self::assertFalse($this->handouts->canSee($this->letter, $this->ada));
        self::assertSame(['handout:4 campaign:3 staged player:12 player:1', 'handout:4 campaign:3 staged player:13 player:1'], \array_slice($this->recorder->summary(), 0, 2));
        $this->recorder->clear();

        $this->clock->modify('+1 hour');
        $revealed = $this->handouts->cue($this->table, 'crypt.opened');

        self::assertSame(['handout:4 player:12', 'handout:4 player:13', 'handout:5 *'], array_map(static fn (Disclosure $d): string => $d->handout->toString().' '.$d->key(), $revealed));
        self::assertSame('player:1', $revealed[0]->by?->toString());
        self::assertNull($revealed[2]->by);
        self::assertSame('2026-11-25 20:01:00', $revealed[0]->revealedAt->format('Y-m-d H:i:s'));
        self::assertTrue($this->handouts->canSee($this->letter, $this->ada));
        self::assertSame([], $this->handouts->staged($this->letter));
        self::assertCount(1, $this->handouts->staged($elsewhere));
        self::assertSame([], $this->handouts->cue($this->table, 'crypt.opened'));
        self::assertSame(['handout:4 campaign:3 revealed player:12 player:1', 'handout:4 campaign:3 revealed player:13 player:1', 'handout:5 campaign:3 revealed - -'], $this->recorder->summary());
    }

    public function testTheCallerOfACueActsForIt(): void
    {
        $this->create();
        $this->handouts->stage($this->letter, Audience::everyone(), Trigger::cue('crypt'), $this->gm);

        $revealed = $this->handouts->cue($this->table, 'crypt', $this->ada);

        self::assertSame('player:12', $revealed[0]->by?->toString());
    }

    public function testACueIsAKey(): void
    {
        $this->expectException(InvalidHandout::class);

        $this->handouts->cue($this->table, 'Crypt Opened');
    }

    public function testACueRevealsAtMostTheReleaseBatch(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(releaseBatch: 2));
        $handouts->create($this->letter, $this->table, 'Letter', 'x');
        $handouts->stage($this->letter, Audience::players($this->ada, $this->bo, ResourceReference::of('player', '14')), Trigger::cue('crypt'));

        self::assertCount(2, $handouts->cue($this->table, 'crypt'));
        self::assertSame(['player:14'], array_map(static fn (StagedReveal $s): string => $s->key(), $handouts->staged($this->letter)));
        self::assertCount(1, $handouts->cue($this->table, 'crypt'));
    }

    public function testARevealIsStagedAtAMoment(): void
    {
        $this->create();
        $map = ResourceReference::of('handout', '5');
        $this->handouts->create($map, ResourceReference::of('campaign', '9'), 'Map', 'x');
        $this->handouts->stage($this->letter, Audience::players($this->ada), Trigger::at($this->clock->now()->modify('+2 hours')), $this->gm);
        $this->handouts->stage($map, Audience::everyone(), Trigger::at($this->clock->now()->modify('+1 hour')));
        $this->handouts->stage($this->letter, Audience::players($this->bo), Trigger::cue('crypt'));

        self::assertSame([], $this->handouts->releaseDue());

        $this->clock->modify('+1 hour');
        $first = $this->handouts->releaseDue();
        self::assertSame(['handout:5 *'], array_map(static fn (Disclosure $d): string => $d->handout->toString().' '.$d->key(), $first));

        $this->clock->modify('+2 hours');
        $second = $this->handouts->releaseDue($this->gm);
        self::assertSame(['handout:4 player:12 player:1'], array_map(static fn (Disclosure $d): string => $d->handout->toString().' '.$d->key().' '.$d->by?->toString(), $second));
        self::assertSame([], $this->handouts->releaseDue());
        self::assertSame(['player:13'], array_map(static fn (StagedReveal $s): string => $s->key(), $this->handouts->staged($this->letter)));
    }

    public function testDueRevealsAreReleasedEarliestFirstInBatches(): void
    {
        $handouts = new Handouts($this->store, $this->clock, new Limits(releaseBatch: 2));
        $handouts->create($this->letter, $this->table, 'Letter', 'x');

        $at = $this->clock->now();
        $handouts->stage($this->letter, Audience::players($this->bo), Trigger::at($at->modify('+2 minutes')));
        $handouts->stage($this->letter, Audience::players(ResourceReference::of('player', '14')), Trigger::at($at));
        $handouts->stage($this->letter, Audience::players($this->ada), Trigger::at($at));

        self::assertSame(['player:12', 'player:14'], array_map(static fn (Disclosure $d): string => $d->key(), $handouts->releaseDue()));
        self::assertSame([], $handouts->releaseDue());

        $this->clock->modify('+2 minutes');
        self::assertSame(['player:13'], array_map(static fn (Disclosure $d): string => $d->key(), $handouts->releaseDue()));
    }

    public function testRevealingNowFinishesAStagedReveal(): void
    {
        $this->create();
        $this->handouts->stage($this->letter, Audience::players($this->ada, $this->bo), Trigger::cue('crypt'));

        $this->handouts->reveal($this->letter, Audience::players($this->ada));

        self::assertSame(['player:13'], array_map(static fn (StagedReveal $s): string => $s->key(), $this->handouts->staged($this->letter)));
    }

    public function testStagingAgainReplacesTheTrigger(): void
    {
        $this->create();
        $this->handouts->stage($this->letter, Audience::everyone(), Trigger::cue('crypt'));
        $this->handouts->stage($this->letter, Audience::everyone(), Trigger::cue('mill'));

        self::assertSame(['mill'], array_map(static fn (StagedReveal $s): ?string => $s->trigger->cue, $this->handouts->staged($this->letter)));
        self::assertSame([], $this->handouts->cue($this->table, 'crypt'));
    }

    public function testStagedRevealsAreWithdrawn(): void
    {
        $this->create();
        $this->handouts->stage($this->letter, Audience::players($this->ada, $this->bo), Trigger::cue('crypt'));
        $this->recorder->clear();

        $this->handouts->unstage($this->letter, Audience::players($this->ada, ResourceReference::of('player', '14')), $this->gm);
        self::assertSame(['player:13'], array_map(static fn (StagedReveal $s): string => $s->key(), $this->handouts->staged($this->letter)));

        $this->handouts->stage($this->letter, Audience::everyone(), Trigger::cue('crypt'));
        $this->handouts->unstage($this->letter, Audience::everyone());
        self::assertSame([], $this->handouts->staged($this->letter));
        self::assertSame([
            'handout:4 campaign:3 unstaged player:12 player:1',
            'handout:4 campaign:3 staged - -',
            'handout:4 campaign:3 unstaged - -',
            'handout:4 campaign:3 unstaged player:13 -',
        ], $this->recorder->summary());
    }

    public function testStagedRevealsAreReadEarliestStagedFirst(): void
    {
        $this->create();
        $this->handouts->stage($this->letter, Audience::players($this->bo), Trigger::cue('a'));
        $this->handouts->stage($this->letter, Audience::players($this->ada), Trigger::cue('a'));

        $this->clock->modify('-1 minute');
        $this->handouts->stage($this->letter, Audience::everyone(), Trigger::cue('a'));

        self::assertSame(['*', 'player:12', 'player:13'], array_map(static fn (StagedReveal $s): string => $s->key(), $this->handouts->staged($this->letter)));
    }

    public function testAPlayerMarksWhatTheyOpened(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->recorder->clear();
        $this->clock->modify('+1 minute');

        $sighting = $this->handouts->markSeen($this->letter, $this->ada, $this->ada);

        self::assertSame([1, '19:01:00', '19:01:00'], [$sighting->revision, $sighting->firstSeenAt->format('H:i:s'), $sighting->lastSeenAt->format('H:i:s')]);
        self::assertSame(['handout:4 campaign:3 seen player:12 player:12'], $this->recorder->summary());

        $this->clock->modify('+1 minute');
        $again = $this->handouts->markSeen($this->letter, $this->ada);
        self::assertSame([1, '19:01:00', '19:02:00'], [$again->revision, $again->firstSeenAt->format('H:i:s'), $again->lastSeenAt->format('H:i:s')]);
        self::assertCount(1, $this->recorder->changes);

        $this->handouts->edit($this->letter, 'Torn letter', 'x');
        $this->clock->modify('+1 minute');
        $updated = $this->handouts->markSeen($this->letter, $this->ada);
        self::assertSame([2, '19:01:00', '19:03:00'], [$updated->revision, $updated->firstSeenAt->format('H:i:s'), $updated->lastSeenAt->format('H:i:s')]);
        self::assertSame('handout:4 campaign:3 seen player:12 -', $this->recorder->summary()[2]);
        self::assertSame([$updated], $this->handouts->sightings($this->letter));
    }

    public function testAPlayerOpensOnlyWhatIsRevealedToThem(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada));

        $this->assertRefused(Refusal::NotRevealed, fn (): Sighting => $this->handouts->markSeen($this->letter, $this->bo));
        self::assertSame([], $this->handouts->sightings($this->letter));

        $this->handouts->reveal($this->letter, Audience::everyone());
        self::assertSame(1, $this->handouts->markSeen($this->letter, $this->bo)->revision);
    }

    public function testSightingsAreReadEarliestFirst(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::everyone());
        $this->handouts->markSeen($this->letter, $this->bo);
        $this->handouts->markSeen($this->letter, $this->ada);

        $this->clock->modify('-1 minute');
        $this->handouts->markSeen($this->letter, $this->gm);

        self::assertSame(['player:1', 'player:12', 'player:13'], array_map(static fn (Sighting $s): string => $s->player->toString(), $this->handouts->sightings($this->letter)));
    }

    public function testAPlayerFindsWhatIsRevealedToThem(): void
    {
        $this->create();
        $map = ResourceReference::of('handout', '5');
        $this->handouts->create($map, $this->table, 'Map', 'x');
        $secret = ResourceReference::of('handout', '6');
        $this->handouts->create($secret, $this->table, 'Secret', 'x');
        $elsewhere = ResourceReference::of('handout', '7');
        $this->handouts->create($elsewhere, ResourceReference::of('campaign', '9'), 'Elsewhere', 'x');

        self::assertSame([], $this->handouts->visibleTo($this->table, $this->ada));

        $this->clock->modify('+1 minute');
        $this->handouts->reveal($map, Audience::everyone());
        $this->handouts->reveal($elsewhere, Audience::everyone());

        $this->clock->modify('+1 minute');
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->handouts->reveal($secret, Audience::players($this->bo));

        $this->clock->modify('+1 minute');
        $this->handouts->reveal($this->letter, Audience::everyone());
        $this->handouts->markSeen($this->letter, $this->ada);
        $this->handouts->markSeen($map, $this->ada);
        $this->handouts->edit($map, 'Map, annotated', 'x');

        $visible = $this->handouts->visibleTo($this->table, $this->ada);

        self::assertSame(
            ['handout:5 19:01:00 outdated 1', 'handout:4 19:02:00 seen 1'],
            array_map(static fn (Visible $v): string => \sprintf('%s %s %s %d', $v->handout->reference->toString(), $v->revealedAt->format('H:i:s'), $v->freshness->value, $v->sighting->revision ?? 0), $visible),
        );
        self::assertSame('Map, annotated', $visible[0]->handout->title);
        self::assertSame(['handout:5'], array_map(static fn (Visible $v): string => $v->handout->reference->toString(), $this->handouts->unseen($this->table, $this->ada)));

        $bo = $this->handouts->visibleTo($this->table, $this->bo);
        self::assertSame(['handout:5 unseen', 'handout:6 unseen', 'handout:4 unseen'], array_map(static fn (Visible $v): string => $v->handout->reference->toString().' '.$v->freshness->value, $bo));
        self::assertNull($bo[0]->sighting);
        self::assertCount(3, $this->handouts->unseen($this->table, $this->bo));
        self::assertSame(Freshness::Unseen, $bo[2]->freshness);
    }

    public function testVisibleHandoutsThatTieAreReadByReference(): void
    {
        $b = ResourceReference::of('handout', 'b');
        $a = ResourceReference::of('handout', 'a');
        $this->handouts->create($b, $this->table, 'B', 'x');
        $this->handouts->create($a, $this->table, 'A', 'x');
        $this->handouts->reveal($b, Audience::everyone());
        $this->handouts->reveal($a, Audience::everyone());

        self::assertSame(['A', 'B'], array_map(static fn (Visible $v): string => $v->handout->title, $this->handouts->visibleTo($this->table, $this->ada)));
    }

    public function testAPlayerIsForgottenEverywhere(): void
    {
        $this->create();
        $map = ResourceReference::of('handout', '5');
        $this->handouts->create($map, ResourceReference::of('campaign', '9'), 'Map', 'x');
        $untouched = ResourceReference::of('handout', '6');
        $this->handouts->create($untouched, $this->table, 'Untouched', 'x');
        $this->handouts->reveal($this->letter, Audience::players($this->ada, $this->bo));
        $this->handouts->markSeen($this->letter, $this->ada);
        $this->handouts->stage($map, Audience::players($this->ada), Trigger::cue('crypt'));
        $this->handouts->reveal($untouched, Audience::everyone());
        $this->handouts->markSeen($untouched, $this->bo);

        $this->recorder->clear();

        $this->handouts->forget($this->ada, $this->gm);

        self::assertFalse($this->handouts->canSee($this->letter, $this->ada));
        self::assertTrue($this->handouts->canSee($this->letter, $this->bo));
        self::assertTrue($this->handouts->canSee($untouched, $this->ada));
        self::assertSame([], $this->handouts->sightings($this->letter));
        self::assertSame([], $this->handouts->staged($map));
        self::assertCount(1, $this->handouts->sightings($untouched));
        self::assertSame(['handout:4 campaign:3 forgotten player:12 player:1', 'handout:5 campaign:9 forgotten player:12 player:1'], $this->recorder->summary());
    }

    public function testTheStoreForgetsInByteOrder(): void
    {
        $this->handouts->create(ResourceReference::of('handout', 'b'), $this->table, 'B', 'x');
        $this->handouts->create(ResourceReference::of('handout', 'a'), $this->table, 'A', 'x');
        $this->handouts->create(ResourceReference::of('handout', '10'), $this->table, 'Ten', 'x');
        $this->handouts->create(ResourceReference::of('handout', '9'), $this->table, 'Nine', 'x');
        $this->handouts->reveal(ResourceReference::of('handout', 'b'), Audience::players($this->ada));
        $this->handouts->markSeen(ResourceReference::of('handout', 'b'), $this->ada);
        $this->handouts->stage(ResourceReference::of('handout', 'a'), Audience::players($this->ada), Trigger::cue('x'));
        $this->handouts->stage(ResourceReference::of('handout', '9'), Audience::players($this->ada), Trigger::cue('x'));
        $this->handouts->reveal(ResourceReference::of('handout', '10'), Audience::players($this->ada));

        self::assertSame(['handout:10', 'handout:9', 'handout:a', 'handout:b'], $this->store->forgetPlayer('player:12'));
        self::assertSame([], $this->store->forgetPlayer('player:12'));
    }

    public function testTheStoreReadsATablesDisclosuresToAPlayer(): void
    {
        $this->create();
        $map = ResourceReference::of('handout', '5');
        $this->handouts->create($map, ResourceReference::of('campaign', '9'), 'Map', 'x');
        $this->handouts->reveal($this->letter, Audience::players($this->bo));
        $this->handouts->reveal($map, Audience::players($this->ada));
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->handouts->reveal($this->letter, Audience::everyone());

        self::assertSame(['*', 'player:12'], array_map(static fn (Disclosure $d): string => $d->key(), $this->store->disclosuresFor('campaign:3', 'player:12')));
        self::assertSame(['player:12'], array_map(static fn (Disclosure $d): string => $d->key(), $this->store->disclosuresFor('campaign:9', 'player:12')));
        self::assertNotNull($this->store->disclosure('handout:4', '*'));
        self::assertNull($this->store->disclosure('handout:5', '*'));
        self::assertNull($this->store->staged('handout:4', '*'));
    }

    public function testVisibleHandoutsAreReadByRevealTimeBeforeReference(): void
    {
        $b = ResourceReference::of('handout', 'b');
        $a = ResourceReference::of('handout', 'a');
        $this->handouts->create($b, $this->table, 'B', 'x');
        $this->handouts->create($a, $this->table, 'A', 'x');
        $this->handouts->reveal($b, Audience::everyone());

        $this->clock->modify('+1 minute');
        $this->handouts->reveal($a, Audience::everyone());

        self::assertSame(['B', 'A'], array_map(static fn (Visible $v): string => $v->handout->title, $this->handouts->visibleTo($this->table, $this->ada)));
    }

    public function testVisibleHandoutsRevealedInReverseReferenceOrderKeepTheirRevealOrder(): void
    {
        foreach (['d', 'c', 'b', 'a'] as $id) {
            $reference = ResourceReference::of('handout', $id);
            $this->handouts->create($reference, $this->table, strtoupper($id), 'x');
            $this->handouts->reveal($reference, Audience::everyone());
            $this->clock->modify('+1 minute');
        }

        self::assertSame(['D', 'C', 'B', 'A'], array_map(static fn (Visible $v): string => $v->handout->title, $this->handouts->visibleTo($this->table, $this->ada)));
    }

    public function testUnseenHandoutsAreListedFromZero(): void
    {
        $this->create();
        $map = ResourceReference::of('handout', '5');
        $this->handouts->create($map, $this->table, 'Map', 'x');
        $this->handouts->reveal($this->letter, Audience::everyone());

        $this->clock->modify('+1 minute');
        $this->handouts->reveal($map, Audience::everyone());
        $this->handouts->markSeen($this->letter, $this->ada);

        $unseen = $this->handouts->unseen($this->table, $this->ada);

        self::assertSame([0], array_keys($unseen));
        self::assertSame('Map', $unseen[0]->handout->title);
    }

    public function testTheStoreListsTheDueRevealsByTimeThenHandoutThenKey(): void
    {
        $at = $this->clock->now()->modify('+1 hour');
        $earlier = $this->clock->now()->modify('+30 minutes');
        foreach (['a', 'b', 'c'] as $id) {
            $this->handouts->create(ResourceReference::of('handout', $id), $this->table, strtoupper($id), 'x');
        }

        $this->handouts->stage(ResourceReference::of('handout', 'b'), Audience::everyone(), Trigger::at($at));
        $this->handouts->stage(ResourceReference::of('handout', 'a'), Audience::players($this->bo, $this->ada), Trigger::at($at));
        $this->handouts->stage(ResourceReference::of('handout', 'c'), Audience::everyone(), Trigger::at($earlier));
        $this->handouts->stage(ResourceReference::of('handout', 'c'), Audience::players($this->ada), Trigger::at($at->modify('+1 day')));

        $summary = static fn (array $reveals): array => array_map(static fn (StagedReveal $r): string => $r->handout->toString().' '.$r->key(), $reveals);
        $until = $at->modify('+1 year');

        self::assertSame(['handout:c *', 'handout:a player:12', 'handout:a player:13', 'handout:b *', 'handout:c player:12'], $summary($this->store->due($until, 10)));
        self::assertSame(['handout:c *', 'handout:a player:12'], $summary($this->store->due($until, 2)));
        self::assertSame(['handout:c *'], $summary($this->store->due($at->modify('-1 minute'), 10)));
    }

    public function testTheStoreTiesDueRevealsOfOneHandoutByKey(): void
    {
        $at = $this->clock->now()->modify('+1 hour');
        $this->create();
        $this->handouts->stage($this->letter, Audience::players($this->bo), Trigger::at($at));
        $this->handouts->stage($this->letter, Audience::players($this->ada), Trigger::at($at));

        self::assertSame(['player:12', 'player:13'], array_map(static fn (StagedReveal $r): string => $r->key(), $this->store->due($at, 10)));
    }

    public function testTheStoreStopsOnCueRevealsAtTheLimit(): void
    {
        $this->create();
        $this->handouts->stage($this->letter, Audience::players($this->ada, $this->bo), Trigger::cue('crypt'));

        self::assertCount(1, $this->store->onCue('campaign:3', 'crypt', 1));
        self::assertCount(2, $this->store->onCue('campaign:3', 'crypt', 5));
    }

    public function testAPlayerIsForgottenOnceFromEachStore(): void
    {
        $this->create();
        $this->handouts->reveal($this->letter, Audience::players($this->ada));
        $this->handouts->markSeen($this->letter, $this->ada);

        self::assertSame(['handout:4'], $this->store->forgetPlayer('player:12'));
    }

    public function testObserversAreTakenFromAnyIterable(): void
    {
        $handouts = new Handouts($this->store, $this->clock, observers: (function () {
            yield $this->recorder;
            yield $this->recorder;
        })());

        $handouts->create($this->letter, $this->table, 'Letter', 'x');

        self::assertCount(2, $this->recorder->changes);
    }

    private function create(): Handout
    {
        return $this->handouts->create($this->letter, $this->table, 'Letter', 'Meet me at the mill.');
    }

    private function attachment(string $id): Attachment
    {
        return new Attachment(ResourceReference::of('file', $id), 'image/png', 'A map');
    }

    /**
     * @param Closure(): mixed $act
     */
    private function assertRefused(Refusal $refusal, Closure $act): void
    {
        try {
            $act();
            self::fail(\sprintf('The act was not refused with %s.', $refusal->name));
        } catch (HandoutRefused $handoutRefused) {
            self::assertSame($refusal, $handoutRefused->refusal);
        }
    }
}
