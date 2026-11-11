<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit;

use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Curve;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Exception\UnknownTrack;
use Tilttek\Progression\Experience\Period;
use Tilttek\Progression\Season\CalendarSeasons;
use Tilttek\Progression\Season\NamedSeasons;
use Tilttek\Progression\Season\SeasonLength;
use Tilttek\Progression\Subject;
use Tilttek\Progression\Track;
use Tilttek\Progression\Tracks;

#[CoversClass(Tracks::class)]
#[CoversClass(UnknownTrack::class)]
final class TracksTest extends TestCase
{
    public function testTracksFromAConfiguration(): void
    {
        $tracks = Tracks::fromArray([
            'player' => [
                'curve' => ['type' => 'quadratic', 'base' => 50, 'levels' => 60],
                'seasons' => ['length' => 'quarter'],
                'rules' => [
                    'games' => ['verbs' => ['game.played', 'game.won'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day'], 'context' => ['ranked' => true]],
                    'wins' => ['verbs' => ['game.won'], 'experience' => 5, 'per_quantity' => false],
                ],
                'milestones' => ['veteran' => ['level' => 10], 'thousand' => ['experience' => 1000, 'level' => null]],
            ],
            'character' => [
                'curve' => ['type' => 'table', 'thresholds' => [300, 900]],
                'subject' => 'object',
                'seasons' => ['named' => [['code' => 'summer', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
            ],
            'author' => ['curve' => ['type' => 'linear', 'step' => 100], 'seasons' => null, 'rules' => ['pages' => ['verbs' => ['page.written'], 'experience' => 1]]],
            'gm' => ['curve' => ['type' => 'exponential', 'base' => 100, 'factor' => 2, 'levels' => 20], 'seasons' => ['length' => null, 'named' => []]],
        ], 'America/Toronto');

        self::assertSame('America/Toronto', $tracks->timezone->getName());
        self::assertSame(['author', 'character', 'gm', 'player'], array_map(static fn (Track $track): string => $track->name, $tracks->all()));
        self::assertSame(['page.written', 'game.played', 'game.won'], $tracks->patterns());
        self::assertTrue($tracks->has('gm'));
        self::assertFalse($tracks->has('reader'));

        $player = $tracks->get('player');
        self::assertSame(60, $player->curve->levels());
        self::assertSame(50, $player->curve->experienceFor(2));
        self::assertSame(Subject::Member, $player->subject);
        self::assertEquals(new CalendarSeasons(SeasonLength::Quarter, $tracks->timezone), $player->seasons);
        self::assertSame(100, $player->rules['games']->cap?->amount);
        self::assertSame(Period::Day, $player->rules['games']->cap->period);
        self::assertSame(['ranked' => true], $player->rules['games']->conditions->values);
        self::assertTrue($player->rules['games']->perQuantity);
        self::assertFalse($player->rules['wins']->perQuantity);
        self::assertNull($player->rules['wins']->cap);
        self::assertSame(['thousand', 'veteran'], array_keys($player->milestones));
        self::assertTrue($player->milestones['veteran']->level);
        self::assertFalse($player->milestones['thousand']->level);

        $character = $tracks->get('character');
        self::assertSame([0, 300, 900], $character->curve->thresholds);
        self::assertSame(Subject::Object, $character->subject);
        self::assertInstanceOf(NamedSeasons::class, $character->seasons);
        self::assertSame('2026-06-21 00:00 -04:00', $character->seasons->named('summer')?->start->format('Y-m-d H:i P'));
        self::assertSame('2026-09-22 00:00 -04:00', $character->seasons->named('summer')->end->format('Y-m-d H:i P'));
        self::assertSame([], $character->rules);

        self::assertNull($tracks->get('author')->seasons);
        self::assertSame(Curve::DEFAULT_LEVELS, $tracks->get('author')->curve->levels());
        self::assertSame(1, $tracks->get('author')->rules['pages']->experience);
        self::assertNull($tracks->get('gm')->seasons);
        self::assertSame([0, 100, 300], \array_slice($tracks->get('gm')->curve->thresholds, 0, 3));
        self::assertSame('UTC', Tracks::fromArray([])->timezone->getName());
    }

    public function testAnUnknownTrack(): void
    {
        $this->expectExceptionObject(UnknownTrack::named('reader'));
        Tracks::fromArray([])->get('reader');
    }

    public function testTwoTracksHaveTwoNames(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('Two tracks are named "player".'));
        new Tracks(new DateTimeZone('UTC'), new Track('player', Curve::linear(1), []), new Track('player', Curve::linear(2), []));
    }

    /**
     * @return iterable<string, array{callable(): Tracks, string}>
     */
    public static function refusedConfigurations(): iterable
    {
        $linear = ['type' => 'linear', 'step' => 10];

        yield 'a timezone' => [static fn (): Tracks => Tracks::fromArray([], 'Mars/Olympus'), '"Mars/Olympus" is not a timezone.'];
        yield 'a period' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => $linear, 'rules' => ['r' => ['verbs' => ['a'], 'experience' => 1, 'cap' => ['amount' => 1, 'period' => 'hour']]]]], 'UTC'), '"hour" is not a period: day, week, month, year or ever.'];
        yield 'a subject' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => $linear, 'subject' => 'team']], 'UTC'), 'The subject of the track "p" is "member" or "object".'];
        yield 'a curve type' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => ['type' => 'cubic']]], 'UTC'), 'The curve of the track "p" is linear, quadratic, exponential or table; "cubic" is none of them.'];
        yield 'a step' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => ['type' => 'linear']]], 'UTC'), 'The linear curve of the track "p" needs its "step".'];
        yield 'a quadratic base' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => ['type' => 'quadratic']]], 'UTC'), 'The quadratic curve of the track "p" needs its "base".'];
        yield 'an exponential base' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => ['type' => 'exponential', 'factor' => 2]]], 'UTC'), 'The exponential curve of the track "p" needs its "base".'];
        yield 'a factor' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => ['type' => 'exponential', 'base' => 2]]], 'UTC'), 'The exponential curve of the track "p" needs its "factor".'];
        yield 'thresholds' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => ['type' => 'table']]], 'UTC'), 'The table curve of the track "p" needs its "thresholds".'];
        yield 'a milestone' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => $linear, 'milestones' => ['m' => []]]], 'UTC'), 'The milestone "m" is at a level or an amount of experience.'];
        yield 'a season length' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => $linear, 'seasons' => ['length' => 'week']]], 'UTC'), '"week" is not a season length: month, quarter or year.'];
        yield 'a season date' => [static fn (): Tracks => Tracks::fromArray(['p' => ['curve' => $linear, 'seasons' => ['named' => [['code' => 's', 'start' => 'someday', 'end' => '2026-01-01']]]]], 'UTC'), '"someday" is not a date.'];
    }

    /**
     * @param callable(): Tracks $tracks
     */
    #[DataProvider('refusedConfigurations')]
    public function testARefusedConfiguration(callable $tracks, string $message): void
    {
        $this->expectExceptionObject(InvalidProgression::because($message));
        $tracks();
    }
}
