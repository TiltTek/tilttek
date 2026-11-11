<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\VerbPattern;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\LevelChange;
use Tilttek\Progression\ProgressionProjection;
use Tilttek\Progression\Streak\ContextTimezone;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Tests\Unit\Fixture\Kit;
use Tilttek\Progression\Tracks;

#[CoversClass(ProgressionProjection::class)]
#[CoversClass(LevelChange::class)]
final class ProgressionProjectionTest extends TestCase
{
    public function testTheFilterReadsTheVerbsOfTheTracksAndTheStreaksOnce(): void
    {
        $kit = new Kit(self::tracks(), StreakRules::fromArray(['daily' => ['verbs' => ['news.read', 'game.played', 'forum.posted']]]));

        self::assertSame(['session.played', 'game.played', 'game.*', 'news.read', 'forum.posted'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $kit->projection->filter()->verbs->patterns));
    }

    public function testTheProjection(): void
    {
        $kit = self::kit();
        $named = new ProgressionProjection($kit->tracks, $kit->keeper, $kit->experience, $kit->milestones, $kit->clock, name: 'levels');

        self::assertSame('progression', $kit->projection->name());
        self::assertSame('levels', $named->name());
        self::assertSame(['session.played', 'game.played', 'game.*'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $kit->projection->filter()->verbs->patterns));
    }

    public function testExperienceLevelsUpAndReachesMilestones(): void
    {
        $kit = self::kit();

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00');
        $kit->record('k3', 'member:1', 'game.played', '2026-11-04 10:00');
        $kit->record('k4', 'member:1', 'game.played', '2026-11-05 10:00', quantity: 3);

        self::assertSame(300, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertSame([
            'milestone player first member:1 ',
            'up player member:1  1→2 k2',
            'up player member:1  2→4 k4',
            'milestone player veteran member:1 ',
            'up season member:1 2026-11 1→2 k4',
        ], $kit->recorder->told);
        self::assertSame('2026-11-05T10:00:00+00:00', $kit->milestones->of($this->ref('member:1'))[1]->reachedAt->format(\DATE_ATOM));
        self::assertSame('k4', $kit->milestones->of($this->ref('member:1'))[1]->activity);
    }

    public function testACapLimitsARuleInItsPeriod(): void
    {
        $kit = self::kit();

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 01:00', quantity: 2);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-02 02:00', quantity: 2);
        $kit->record('k3', 'member:1', 'game.played', '2026-11-02 03:00');
        $kit->record('k4', 'member:1', 'game.played', '2026-11-02 05:00');

        // A hundred and fifty a day in Toronto, where 2026-11-02 05:00 UTC is already the next day.
        self::assertSame(200, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertSame(50, $kit->experience->earned('player', 'games', $this->ref('member:1'), new DateTimeImmutable('2026-11-02 05:00 UTC'), null));
        self::assertSame(['k1', 'k1', 'k2', 'k2', 'k3', 'k4', 'k4'], array_map(static fn (Gain $gain): string => $gain->activity, $kit->experience->of($this->ref('member:1'))));
    }

    public function testATrackOfObjectsGivesTheExperienceToTheObject(): void
    {
        $kit = self::kit();

        $kit->record('k1', 'member:1', 'session.played', '2026-11-02 10:00', 'character:9', 400);
        $kit->record('k2', 'member:1', 'session.played', '2026-11-03 10:00');

        self::assertSame(400, $kit->experience->lifetime('character', $this->ref('character:9')));
        self::assertSame(0, $kit->experience->lifetime('character', $this->ref('member:1')));
        self::assertSame('member:1', $kit->experience->of($this->ref('character:9'))[0]->member->toString());
        self::assertSame(['up character character:9  1→2 k1'], $kit->recorder->told);
    }

    public function testASeasonStartsATrackAgain(): void
    {
        $kit = self::kit();

        $kit->record('k1', 'member:1', 'game.won', '2026-10-31 10:00', quantity: 6);
        $kit->record('k2', 'member:1', 'game.won', '2026-11-01 10:00', quantity: 2);
        $kit->record('k3', 'member:1', 'game.won', '2026-11-02 10:00', quantity: 4);

        self::assertSame(60, $kit->experience->total('season', $this->ref('member:1'), '2026-10'));
        self::assertSame(60, $kit->experience->total('season', $this->ref('member:1'), '2026-11'));
        self::assertSame(['up season member:1 2026-10 1→2 k1', 'up season member:1 2026-11 1→2 k3'], array_values(array_filter($kit->recorder->told, static fn (string $told): bool => str_contains($told, 'season'))));
    }

    public function testAnEntryHandedTwiceCountsOnce(): void
    {
        $kit = self::kit();
        $entry = new Entry(1, EntryKind::Recorded, Activity::of('k1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 10:00 UTC'), quantity: 3), new DateTimeImmutable('2026-11-02 10:00 UTC'));

        $kit->projection->apply($entry);
        $kit->projection->apply($entry);

        self::assertSame(150, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertSame(['up player member:1  1→2 k1', 'milestone player first member:1 '], $kit->recorder->told);
    }

    public function testACancellationTakesBack(): void
    {
        $kit = self::kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 2);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00', quantity: 2);
        $kit->record('k3', 'member:1', 'game.won', '2026-11-03 11:00');
        $kit->recorder->clear();

        $kit->clock->set('2026-11-04 10:00:00 UTC');
        $kit->journal->cancel('k2');
        $kit->journal->cancel('k3');

        self::assertSame(100, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertSame(20, $kit->experience->lifetime('season', $this->ref('member:1')));
        self::assertSame(['down player member:1  3→2 k2', 'down season member:1 2026-11 2→1 k2'], $kit->recorder->told);
        self::assertCount(2, $kit->milestones->of($this->ref('member:1')));
        self::assertSame(1, $kit->progression->streak($this->ref('member:1'), 'daily')->best);
    }

    public function testACancellationThatKeepsTheLevelSaysNothing(): void
    {
        $kit = self::kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 2);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00');
        $kit->recorder->clear();

        $kit->journal->cancel('k2');

        self::assertSame(100, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertSame([], $kit->recorder->told);
    }

    public function testTheGainsOfATrackNoLongerConfiguredAreTakenBackSilently(): void
    {
        $kit = self::kit();
        $kit->experience->add(new Gain('retired', 'old', $this->ref('member:1'), $this->ref('member:1'), 'k1', 500, new DateTimeImmutable('2026-11-01 10:00 UTC')));

        $kit->projection->apply(new Entry(2, EntryKind::Cancelled, Activity::of('k1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-01 10:00 UTC')), new DateTimeImmutable('2026-11-02 10:00 UTC')));

        self::assertSame(0, $kit->experience->lifetime('retired', $this->ref('member:1')));
        self::assertSame([], $kit->recorder->told);
    }

    public function testARebuildTellsOnlyTheLevelsAboveThoseHeld(): void
    {
        $kit = self::kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 3);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-04 10:00', quantity: 2);
        $kit->record('k3', 'member:1', 'game.played', '2026-11-05 10:00', quantity: 2);
        $kit->clock->set('2026-11-05 11:00:00 UTC');
        $kit->journal->cancel('k3');
        $kit->record('k4', 'member:2', 'game.played', '2026-11-05 12:00', quantity: 2);
        $kit->experience->add(new Gain('retired', 'old', $this->ref('member:1'), $this->ref('member:1'), 'k9', 500, new DateTimeImmutable('2026-11-05 10:00 UTC')));
        $kit->recorder->clear();

        $doubled = new Kit(self::tracks(80), self::streaks());
        $projection = new ProgressionProjection($doubled->tracks, $kit->keeper, $kit->experience, $kit->milestones, $kit->clock, null, $kit->recorder);
        $kit->clock->set('2026-11-06 10:00:00 UTC');
        $kit->projector->rebuild($projection, new DateTimeImmutable('2026-11-03 00:00 UTC'));

        // member:1 held level 3 (250): 150 kept, then 310 (level 4, told), 470 (level 5, told), and back to 310 silently;
        // member:2 held level 2 and reaches it again, untold; the season held level 2 and reaches it again, untold.
        self::assertSame(310, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertSame(160, $kit->experience->lifetime('player', $this->ref('member:2')));
        self::assertSame(50, $kit->experience->lifetime('season', $this->ref('member:1')));
        self::assertSame(['up player member:1  2→4 k2', 'up player member:1  4→5 k3'], $kit->recorder->told);

        $kit->recorder->clear();
        $projection->apply(new Entry(99, EntryKind::Recorded, Activity::of('k5', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 11:00 UTC'), quantity: 2), new DateTimeImmutable('2026-11-06 10:00 UTC')));
        $projection->apply(new Entry(100, EntryKind::Cancelled, Activity::of('k5', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 11:00 UTC'), quantity: 2), new DateTimeImmutable('2026-11-06 12:00 UTC')));
        // An entry written at the moment of the reset is not a replay; the streak broke on the fifth, once the third activity was taken back: a gesture after the rebuild tells it.
        self::assertSame(['up player member:1  4→5 k5', 'broken daily member:1 1 20761 2026-11-06 10:00', 'down player member:1  5→4 k5'], $kit->recorder->told);
    }

    public function testARebuildTellsNoBrokenStreak(): void
    {
        $kit = self::kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00');
        $kit->record('k3', 'member:1', 'game.played', '2026-11-06 10:00');
        $kit->recorder->clear();
        $kit->clock->set('2026-11-07 10:00:00 UTC');

        $kit->projector->rebuild($kit->projection);

        self::assertSame([], array_values(array_filter($kit->recorder->told, static fn (string $told): bool => str_starts_with($told, 'broken'))));
        self::assertSame(2, $kit->progression->streak($this->ref('member:1'), 'daily')->best);

        $kit->projection->reset(new DateTimeImmutable('2026-11-03 00:00 UTC'));
        self::assertSame([20759], $kit->streakStore->units('daily', $this->ref('member:1')));
    }

    public function testAStreakIsReadInTheMembersTimezone(): void
    {
        $kit = new Kit(self::tracks(), self::streaks(), new ContextTimezone('timezone', new DateTimeZone('UTC')));

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 23:30', context: ['timezone' => 'Asia/Tokyo']);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 01:00', context: ['timezone' => 'Asia/Tokyo']);
        $kit->record('k3', 'member:2', 'game.played', '2026-11-02 23:30');
        $kit->record('k4', 'member:2', 'game.played', '2026-11-03 01:00');

        self::assertSame(1, $kit->progression->streak($this->ref('member:1'), 'daily')->current);
        self::assertSame(2, $kit->progression->streak($this->ref('member:2'), 'daily')->current);
        self::assertSame('Asia/Tokyo', $kit->streakStore->state('daily', $this->ref('member:1'))?->timezone);
    }

    public function testTheDefaultTimezoneIsThatOfTheStreaks(): void
    {
        $tracks = self::tracks();
        $streaks = StreakRules::fromArray(['daily' => ['verbs' => ['game.played']]], 'Asia/Tokyo');
        $kit = new Kit($tracks, $streaks);
        $projection = new ProgressionProjection($tracks, new StreakKeeper($streaks, $kit->streakStore), $kit->experience, $kit->milestones, $kit->clock);

        $projection->apply(new Entry(1, EntryKind::Recorded, Activity::of('k1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 23:30 UTC')), new DateTimeImmutable('2026-11-02 23:30 UTC')));

        self::assertSame('Asia/Tokyo', $kit->streakStore->state('daily', $this->ref('member:1'))?->timezone);
    }

    public function testACapLeavingOneExperienceGivesIt(): void
    {
        $kit = new Kit(Tracks::fromArray(['reader' => ['curve' => ['type' => 'linear', 'step' => 10], 'rules' => ['pages' => ['verbs' => ['page.read'], 'experience' => 1, 'cap' => ['amount' => 3, 'period' => 'ever']]]]]), self::streaks());

        $kit->record('k1', 'member:1', 'page.read', '2026-11-02 10:00', quantity: 2);
        $kit->record('k2', 'member:1', 'page.read', '2026-11-02 11:00', quantity: 5);
        $kit->record('k3', 'member:1', 'page.read', '2026-11-02 12:00', quantity: 5);

        self::assertSame(3, $kit->experience->lifetime('reader', $this->ref('member:1')));
    }

    public function testACancellationAddsUpTheGainsOfItsActivity(): void
    {
        $kit = new Kit(Tracks::fromArray(['duo' => ['curve' => ['type' => 'linear', 'step' => 100], 'rules' => [
            'a' => ['verbs' => ['game.won'], 'experience' => 30],
            'b' => ['verbs' => ['game.won'], 'experience' => 40],
            'c' => ['verbs' => ['game.lost'], 'experience' => 1],
        ]]]), self::streaks());
        $kit->experience->add(new Gain('retired', 'old', $this->ref('member:1'), $this->ref('member:1'), 'k2', 1, new DateTimeImmutable('2026-11-02 10:00 UTC')));
        $kit->record('k1', 'member:1', 'game.lost', '2026-11-02 09:00', quantity: 59);
        $kit->record('k2', 'member:1', 'game.won', '2026-11-02 10:00');
        $kit->record('k3', 'member:2', 'game.lost', '2026-11-02 09:00', quantity: 29);
        $kit->record('k4', 'member:2', 'game.won', '2026-11-02 10:00');
        $kit->recorder->clear();

        $kit->journal->cancel('k2');
        $kit->journal->cancel('k4');

        // 129 back to 59 crosses a level; 99 back to 29 does not.
        self::assertSame(['down duo member:1  2→1 k2'], $kit->recorder->told);
    }

    public function testAProjectionWithoutAnObserver(): void
    {
        $kit = self::kit();
        $projection = new ProgressionProjection($kit->tracks, new StreakKeeper($kit->streaks, $kit->streakStore), $kit->experience, $kit->milestones, $kit->clock);
        $at = new DateTimeImmutable('2026-11-02 10:00 UTC');

        $projection->apply(new Entry(1, EntryKind::Recorded, Activity::of('k1', 'member:1', 'game.played', $at, quantity: 3), $at));
        $projection->apply(new Entry(2, EntryKind::Cancelled, Activity::of('k1', 'member:1', 'game.played', $at, quantity: 3), $at));

        self::assertSame(0, $kit->experience->lifetime('player', $this->ref('member:1')));
        self::assertCount(1, $kit->milestones->of($this->ref('member:1')));
        self::assertSame([], $kit->recorder->told);
    }

    public function testClearingTheReplayLeavesTheProjectionAsNew(): void
    {
        $kit = self::kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->projector->rebuild($kit->projection);

        $kit->projection->clearReplay();

        self::assertEquals(new ProgressionProjection($kit->tracks, $kit->keeper, $kit->experience, $kit->milestones, $kit->clock, null, $kit->recorder), $kit->projection);
    }

    public static function kit(): Kit
    {
        return new Kit(self::tracks(), self::streaks());
    }

    public static function tracks(int $perGame = 50): Tracks
    {
        return Tracks::fromArray([
            'player' => [
                'curve' => ['type' => 'linear', 'step' => 100, 'levels' => 10],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => $perGame, 'cap' => ['amount' => 3 * $perGame, 'period' => 'day']]],
                'milestones' => ['first' => ['experience' => 50], 'veteran' => ['level' => 3]],
            ],
            'character' => [
                'curve' => ['type' => 'table', 'thresholds' => [300, 900]],
                'subject' => 'object',
                'rules' => ['sessions' => ['verbs' => ['session.played'], 'experience' => 1]],
            ],
            'season' => [
                'curve' => ['type' => 'linear', 'step' => 50],
                'seasons' => ['length' => 'month'],
                'rules' => ['wins' => ['verbs' => ['game.*'], 'experience' => 10, 'context' => []]],
            ],
        ], 'America/Toronto');
    }

    public static function streaks(): StreakRules
    {
        return StreakRules::fromArray(['daily' => ['verbs' => ['game.played']]]);
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
