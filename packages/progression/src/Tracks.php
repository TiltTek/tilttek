<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Exception\UnknownTrack;
use Tilttek\Progression\Experience\Cap;
use Tilttek\Progression\Experience\ExperienceRule;
use Tilttek\Progression\Experience\Period;
use Tilttek\Progression\Season\CalendarSeasons;
use Tilttek\Progression\Season\NamedSeasons;
use Tilttek\Progression\Season\Season;
use Tilttek\Progression\Season\SeasonLength;
use Tilttek\Progression\Season\SeasonSchedule;

/**
 * The tracks of an application, by name, sorted by name, and the timezone their caps, calendar seasons and dates are
 * read in.
 */
final readonly class Tracks
{
    /**
     * @var array<string, Track>
     */
    private array $tracks;

    public function __construct(
        public DateTimeZone $timezone,
        Track ...$tracks,
    ) {
        $byName = [];

        foreach ($tracks as $track) {
            if (isset($byName[$track->name])) {
                throw InvalidProgression::because(\sprintf('Two tracks are named "%s".', $track->name));
            }

            $byName[$track->name] = $track;
        }

        ksort($byName, \SORT_STRING);
        $this->tracks = $byName;
    }

    /**
     * The tracks from plain values, as a configuration gives them:
     *
     * ```php
     * Tracks::fromArray([
     *     'player' => [
     *         'curve' => ['type' => 'quadratic', 'base' => 50, 'levels' => 60],
     *         'seasons' => ['length' => 'quarter'],
     *         'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day']]],
     *         'milestones' => ['veteran' => ['level' => 10]],
     *     ],
     * ], 'America/Toronto');
     * ```
     *
     * A curve is `linear` (`step`), `quadratic` (`base`), `exponential` (`base`, `factor`), each with its number of
     * `levels`, or a `table` of `thresholds`. Seasons follow the calendar by `length` — month, quarter or year — or
     * are `named`, each with its `code`, `start` and `end`.
     *
     * @param array<string, array{curve: array{type: string, step?: int, base?: int, factor?: float|int, levels?: int, thresholds?: list<int>}, subject?: string, seasons?: ?array{length?: ?string, named?: list<array{code: string, start: string, end: string}>}, rules?: array<string, array{verbs: list<string>, experience: int, per_quantity?: bool, cap?: ?array{amount: int, period: string}, context?: array<string, bool|int|string>}>, milestones?: array<string, array{experience?: ?int, level?: ?int}>}> $tracks
     */
    public static function fromArray(array $tracks, string $timezone = 'UTC'): self
    {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidProgression::because(\sprintf('"%s" is not a timezone.', $timezone));
        }

        $list = [];

        foreach ($tracks as $name => $track) {
            $rules = [];

            foreach ($track['rules'] ?? [] as $rule => $values) {
                $cap = $values['cap'] ?? null;
                $rules[] = new ExperienceRule(
                    $rule,
                    $values['verbs'],
                    $values['experience'],
                    null === $cap ? null : new Cap($cap['amount'], Period::tryFrom($cap['period']) ?? throw InvalidProgression::because(\sprintf('"%s" is not a period: day, week, month, year or ever.', $cap['period']))),
                    $values['context'] ?? [],
                    $values['per_quantity'] ?? true,
                );
            }

            $milestones = [];

            foreach ($track['milestones'] ?? [] as $code => $milestone) {
                $level = $milestone['level'] ?? null;
                $milestones[] = null === $level
                    ? new Milestone($code, $milestone['experience'] ?? throw InvalidProgression::because(\sprintf('The milestone "%s" is at a level or an amount of experience.', $code)))
                    : new Milestone($code, $level, true);
            }

            $list[] = new Track(
                $name,
                self::curve($name, $track['curve']),
                $rules,
                Subject::tryFrom($track['subject'] ?? 'member') ?? throw InvalidProgression::because(\sprintf('The subject of the track "%s" is "member" or "object".', $name)),
                self::seasons($track['seasons'] ?? null, $zone),
                $milestones,
            );
        }

        return new self($zone, ...$list);
    }

    /**
     * @return list<Track>
     */
    public function all(): array
    {
        return array_values($this->tracks);
    }

    public function has(string $name): bool
    {
        return isset($this->tracks[$name]);
    }

    /**
     * @throws UnknownTrack
     */
    public function get(string $name): Track
    {
        return $this->tracks[$name] ?? throw UnknownTrack::named($name);
    }

    /**
     * The verb patterns the rules of every track read, each once.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        $patterns = [];

        foreach ($this->tracks as $track) {
            foreach ($track->rules as $rule) {
                foreach ($rule->verbs->patterns as $pattern) {
                    $patterns[$pattern->value] = $pattern->value;
                }
            }
        }

        return array_values($patterns);
    }

    /**
     * @param array{type: string, step?: int, base?: int, factor?: float|int, levels?: int, thresholds?: list<int>} $curve
     */
    private static function curve(string $track, array $curve): Curve
    {
        $levels = $curve['levels'] ?? Curve::DEFAULT_LEVELS;
        $missing = static fn (string $key): InvalidProgression => InvalidProgression::because(\sprintf('The %s curve of the track "%s" needs its "%s".', $curve['type'], $track, $key));

        return match ($curve['type']) {
            'linear' => Curve::linear($curve['step'] ?? throw $missing('step'), $levels),
            'quadratic' => Curve::quadratic($curve['base'] ?? throw $missing('base'), $levels),
            'exponential' => Curve::exponential($curve['base'] ?? throw $missing('base'), $curve['factor'] ?? throw $missing('factor'), $levels),
            'table' => Curve::table($curve['thresholds'] ?? throw $missing('thresholds')),
            default => throw InvalidProgression::because(\sprintf('The curve of the track "%s" is linear, quadratic, exponential or table; "%s" is none of them.', $track, $curve['type'])),
        };
    }

    /**
     * @param ?array{length?: ?string, named?: list<array{code: string, start: string, end: string}>} $seasons
     */
    private static function seasons(?array $seasons, DateTimeZone $timezone): ?SeasonSchedule
    {
        $length = $seasons['length'] ?? null;

        if (null !== $length) {
            return new CalendarSeasons(SeasonLength::tryFrom($length) ?? throw InvalidProgression::because(\sprintf('"%s" is not a season length: month, quarter or year.', $length)), $timezone);
        }

        $named = $seasons['named'] ?? [];

        if ([] === $named) {
            return null;
        }

        $list = [];

        foreach ($named as $season) {
            $list[] = new Season($season['code'], self::date($season['start'], $timezone), self::date($season['end'], $timezone));
        }

        return new NamedSeasons(...$list);
    }

    private static function date(string $date, DateTimeZone $timezone): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($date, $timezone);
        } catch (Exception) {
            throw InvalidProgression::because(\sprintf('"%s" is not a date.', $date));
        }
    }
}
