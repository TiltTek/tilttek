<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\UnknownBoard;
use Tilttek\Leaderboards\League\Formation;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;

/**
 * The boards of an application, by name, sorted by name.
 */
final readonly class Boards
{
    /**
     * @var array<string, Board>
     */
    private array $boards;

    public function __construct(Board ...$boards)
    {
        $byName = [];

        foreach ($boards as $board) {
            if (isset($byName[$board->name])) {
                throw InvalidLeaderboard::because(\sprintf('Two boards are named "%s".', $board->name));
            }

            $byName[$board->name] = $board;
        }

        ksort($byName, \SORT_STRING);
        $this->boards = $byName;
    }

    /**
     * The boards from plain values, as a configuration gives them:
     *
     * ```php
     * Boards::fromArray([
     *     'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'month'],
     *     'high-score' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'period' => 'week', 'ties' => 'shared', 'tie_breaks' => [['measure' => 'count', 'order' => 'asc']]],
     *     'summer' => ['measure' => 'total', 'verbs' => ['quest.*'], 'period' => 'season', 'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
     *     'level' => ['measure' => 'latest'],
     *     'weekly-xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver', 'gold'], 'formed_by' => 'volume']],
     *     'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7, 'title' => ['name' => 'regular', 'threshold' => 5]],
     *     'game-records' => ['measure' => 'best', 'verbs' => ['game.scored'], 'per_object' => true],
     *     'house-cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'chance' => ['dice' => 2, 'faces' => 6]],
     * ], 'America/Toronto');
     * ```
     *
     * A board ranks since ever unless it names its `period` — a sliding `window` counts its days in `window` —; it
     * reads its dates in its own `timezone`, or in the one given here. Its `tie_breaks`, up to two, each gather a
     * `measure` — count, total, highest, lowest, or reported — of the gestures' quantities or of a `context` integer, in
     * an `order`, `desc` by default. A weekly board may have a `league`: its `tiers` from the lowest, its `division_size`
     * (30 by default), how many of a division are `promoted` (7) and `relegated` (5), and whether its divisions are
     * `formed_by` the order of `arrival` or the `volume` of the week before. A board may rank `per_object`, give a
     * `title` from a `threshold` — to the `leader` alone, when it says so —, and add the `chance` of `dice` of so many `faces` to the score of a team.
     *
     * @param array<string, array{measure: string, verbs?: list<string>, context?: array<string, bool|int|string>, period?: string, timezone?: ?string, seasons?: list<array{code: string, start: string, end: string}>, ties?: string, snapshot_size?: int, league?: ?array{tiers: list<string>, division_size?: int, promoted?: int, relegated?: int, formed_by?: string}, tie_breaks?: list<array{measure: string, order?: string, context?: ?string}>, window?: int, per_object?: bool, title?: ?array{name: string, threshold: int, leader?: bool}, chance?: ?array{dice?: int, faces?: int}}> $boards
     */
    public static function fromArray(array $boards, string $timezone = 'UTC'): self
    {
        $list = [];

        foreach ($boards as $name => $board) {
            $zone = self::timezone($board['timezone'] ?? $timezone);
            $seasons = array_map(static fn (array $season): Period => new Period($season['code'], self::date($season['start'], $zone), self::date($season['end'], $zone)), $board['seasons'] ?? []);
            $period = $board['period'] ?? PeriodKind::Ever->value;

            $list[] = new Board(
                $name,
                Measure::tryFrom($board['measure']) ?? throw InvalidLeaderboard::because(\sprintf('The measure of the board "%s" is count, total, best, days, latest or highest; "%s" is none of them.', $name, $board['measure'])),
                new Schedule(PeriodKind::tryFrom($period) ?? throw InvalidLeaderboard::because(\sprintf('The period of the board "%s" is day, week, month, quarter, year, season, ever or window; "%s" is none of them.', $name, $period)), $zone, ...$seasons),
                $board['verbs'] ?? [],
                $board['context'] ?? [],
                Ties::tryFrom($board['ties'] ?? Ties::FirstReached->value) ?? throw InvalidLeaderboard::because(\sprintf('The ties of the board "%s" go to the "first" to reach the score, or are "shared".', $name)),
                $board['snapshot_size'] ?? 100,
                isset($board['league']) ? self::league($name, $board['league']) : null,
                array_map(static fn (array $tieBreak): TieBreak => self::tieBreak($name, $tieBreak), $board['tie_breaks'] ?? []),
                $board['window'] ?? 0,
                $board['per_object'] ?? false,
                isset($board['title']) ? new Title($board['title']['name'], $board['title']['threshold'], $board['title']['leader'] ?? false) : null,
                isset($board['chance']) ? new Chance($board['chance']['dice'] ?? 1, $board['chance']['faces'] ?? 6) : null,
            );
        }

        return new self(...$list);
    }

    /**
     * @return list<Board>
     */
    public function all(): array
    {
        return array_values($this->boards);
    }

    public function has(string $name): bool
    {
        return isset($this->boards[$name]);
    }

    /**
     * @throws UnknownBoard
     */
    public function get(string $name): Board
    {
        return $this->boards[$name] ?? throw UnknownBoard::named($name);
    }

    /**
     * The verb patterns the boards read from the journal, each once.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        $patterns = [];

        foreach ($this->boards as $board) {
            foreach ($board->verbs->patterns as $pattern) {
                $patterns[$pattern->value] = $pattern->value;
            }
        }

        return array_values($patterns);
    }

    /**
     * @param array{tiers: list<string>, division_size?: int, promoted?: int, relegated?: int, formed_by?: string} $league
     */
    private static function league(string $board, array $league): League
    {
        $formedBy = $league['formed_by'] ?? Formation::Arrival->value;

        return new League(
            $league['tiers'],
            $league['division_size'] ?? 30,
            $league['promoted'] ?? 7,
            $league['relegated'] ?? 5,
            Formation::tryFrom($formedBy) ?? throw InvalidLeaderboard::because(\sprintf('The divisions of the league of the board "%s" are formed by "arrival" or by "volume"; "%s" is neither.', $board, $formedBy)),
        );
    }

    /**
     * @param array{measure: string, order?: string, context?: ?string} $tieBreak
     */
    private static function tieBreak(string $board, array $tieBreak): TieBreak
    {
        $order = $tieBreak['order'] ?? Direction::Descending->value;

        return new TieBreak(
            Aggregate::tryFrom($tieBreak['measure']) ?? throw InvalidLeaderboard::because(\sprintf('A tie-break of the board "%s" gathers a count, a total, the highest, the lowest or a reported value; "%s" is none of them.', $board, $tieBreak['measure'])),
            Direction::tryFrom($order) ?? throw InvalidLeaderboard::because(\sprintf('A tie-break of the board "%s" orders "desc" or "asc"; "%s" is neither.', $board, $order)),
            $tieBreak['context'] ?? null,
        );
    }

    private static function timezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidLeaderboard::because(\sprintf('"%s" is not a timezone.', $timezone));
        }
    }

    private static function date(string $date, DateTimeZone $timezone): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($date, $timezone);
        } catch (Exception) {
            throw InvalidLeaderboard::because(\sprintf('"%s" is not a date.', $date));
        }
    }
}
