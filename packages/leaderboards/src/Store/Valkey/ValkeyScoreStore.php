<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store\Valkey;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contract\ValkeyConnection;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Names;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\ScoreStore;
use UnexpectedValueException;

/**
 * The contributions and the scores in Valkey: each ranking is a sorted set read in rank order by its index — the first
 * lines, a member's rank and how many are ranked each cost a logarithm of the ranking's size, not a count of it.
 *
 * Every member of a ranking's sorted set has the score 0 and a key that sorts as the board ranks: the score from the
 * highest, each tie-break in its direction, the moment it was reached, then the member's reference — so a member's rank
 * is how many keys sort before theirs, and a shared rank how many sort before the score and tie-breaks alone. The
 * directions are the boards' own, read from the boards given; a ranking asked in other directions, or among a circle,
 * is ordered in memory from the scores of its members.
 *
 * Each write that touches several keys runs as one script, so that it is whole or not at all. Every key starts with
 * the prefix between braces, a hash tag that keeps them on one node of a cluster. A moment is kept to the microsecond in
 * the keys, and as seconds with their fraction in the indexes of time.
 */
final readonly class ValkeyScoreStore implements ScoreStore
{
    public const string DEFAULT_PREFIX = 'tilttek_leaderboards';

    /**
     * How many contributions one script removes at most, unless the store is given another number.
     */
    public const int BATCH = 500;

    private const string ADD = <<<'LUA'
        if redis.call('HSETNX', KEYS[1], ARGV[1], ARGV[2]) == 0 then return 0 end
        redis.call('SADD', KEYS[2], ARGV[1])
        redis.call('ZADD', KEYS[3], ARGV[3], ARGV[1])
        redis.call('ZADD', KEYS[4], ARGV[3], ARGV[1])
        redis.call('ZADD', KEYS[5], ARGV[3], ARGV[1])
        redis.call('SADD', KEYS[6], ARGV[1])
        return 1
        LUA;

    private const string REMOVE = <<<'LUA'
        local removed = 0
        for index, field in ipairs(ARGV) do
            local base = 2 + (index - 1) * 4
            removed = removed + redis.call('HDEL', KEYS[1], field)
            redis.call('ZREM', KEYS[2], field)
            redis.call('SREM', KEYS[base + 1], field)
            redis.call('ZREM', KEYS[base + 2], field)
            redis.call('ZREM', KEYS[base + 3], field)
            redis.call('SREM', KEYS[base + 4], field)
        end
        return removed
        LUA;

    private const string SAVE = <<<'LUA'
        local held = redis.call('HGET', KEYS[1], ARGV[1])
        if held then redis.call('ZREM', KEYS[2], cjson.decode(held)[4]) end
        redis.call('HSET', KEYS[1], ARGV[1], ARGV[2])
        redis.call('ZADD', KEYS[2], 0, ARGV[3])
        redis.call('SADD', KEYS[3], ARGV[1])
        return 1
        LUA;

    private const string DELETE = <<<'LUA'
        local held = redis.call('HGET', KEYS[1], ARGV[1])
        if not held then return 0 end
        redis.call('ZREM', KEYS[2], cjson.decode(held)[4])
        redis.call('HDEL', KEYS[1], ARGV[1])
        redis.call('SREM', KEYS[3], ARGV[1])
        return 1
        LUA;

    private string $prefix;

    /**
     * @var int<1, 10000>
     */
    private int $batch;

    public function __construct(
        private ValkeyConnection $connection,
        private Boards $boards,
        string $prefix = self::DEFAULT_PREFIX,
        int $batch = self::BATCH,
    ) {
        $this->prefix = '{'.Names::check($prefix, 'prefix').'}:';

        if ($batch < 1 || $batch > 10_000) {
            throw InvalidLeaderboard::because(\sprintf('A script removes 1 to 10000 contributions at a time; %d is not a batch.', $batch));
        }

        $this->batch = $batch;
    }

    #[Override]
    public function add(Contribution $contribution): bool
    {
        $field = self::encode([$contribution->board, $contribution->activity]);

        return 1 === $this->connection->execute(
            'EVAL',
            self::ADD,
            '6',
            $this->key('contribution'),
            $this->key('activity', $contribution->activity),
            $this->key('of', $contribution->board, $contribution->period, $contribution->member->toString()),
            $this->key('time'),
            $this->key('board', $this->boardOf($contribution->board)),
            $this->key('member', $contribution->member->toString()),
            $field,
            self::encode([$contribution->period, $contribution->member->toString(), $contribution->amount, $this->micro($contribution->occurredAt), $contribution->tieBreaks]),
            $this->seconds($contribution->occurredAt),
        );
    }

    #[Override]
    public function remove(string $activity): array
    {
        return $this->removeFields($this->strings($this->connection->execute('SMEMBERS', $this->key('activity', $activity))));
    }

    #[Override]
    public function contributions(string $board, string $period, ResourceReference $member): array
    {
        return $this->contributionsOf($this->strings($this->connection->execute('ZRANGE', $this->key('of', $board, $period, $member->toString()), '0', '-1')));
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        $fields = $since instanceof DateTimeImmutable
            ? $this->connection->execute('ZRANGE', $this->key('time'), $this->seconds($since), '+inf', 'BYSCORE')
            : $this->connection->execute('ZRANGE', $this->key('time'), '0', '-1');

        return $this->removeFields($this->strings($fields));
    }

    #[Override]
    public function expire(string $board, DateTimeImmutable $before): array
    {
        return $this->removeFields($this->strings($this->connection->execute('ZRANGE', $this->key('board', $board), '-inf', '('.$this->seconds($before), 'BYSCORE')));
    }

    #[Override]
    public function save(Score $score): void
    {
        $this->connection->execute(
            'EVAL',
            self::SAVE,
            '3',
            $this->key('score'),
            $this->key('rank', $score->board, $score->period),
            $this->key('member-score', $score->member->toString()),
            self::encode([$score->board, $score->period, $score->member->toString()]),
            self::encode([$score->value, $this->micro($score->reachedAt), $score->tieBreaks, $this->sortKey($score)]),
            $this->sortKey($score),
        );
    }

    #[Override]
    public function delete(string $board, string $period, ResourceReference $member): void
    {
        $this->connection->execute(
            'EVAL',
            self::DELETE,
            '3',
            $this->key('score'),
            $this->key('rank', $board, $period),
            $this->key('member-score', $member->toString()),
            self::encode([$board, $period, $member->toString()]),
        );
    }

    #[Override]
    public function score(string $board, string $period, ResourceReference $member): ?Score
    {
        $held = $this->connection->execute('HGET', $this->key('score'), self::encode([$board, $period, $member->toString()]));

        return \is_string($held) ? $this->scoreOf($board, $period, $member->toString(), $held) : null;
    }

    #[Override]
    public function top(string $board, string $period, int $limit, Ranking $ranking, ?Circle $among = null): array
    {
        if ($among instanceof Circle || !$this->keyedFor($board, $ranking)) {
            return \array_slice($ranking->sort($this->scoresOf($board, $period, $among)), 0, $limit);
        }

        $members = array_map(
            fn (string $key): string => substr($key, $this->headLength($board)),
            $this->strings($this->connection->execute('ZRANGE', $this->key('rank', $board, $period), '0', (string) ($limit - 1))),
        );

        return $this->scoresFor($board, $period, $members);
    }

    #[Override]
    public function ahead(Score $score, Ranking $ranking, ?Circle $among = null): int
    {
        if ($among instanceof Circle || !$this->keyedFor($score->board, $ranking)) {
            $key = $ranking->byArrival() ? $ranking->key($score) : $ranking->valuesKey($score);

            return \count(array_filter($this->scoresOf($score->board, $score->period, $among), static fn (Score $other): bool => ($ranking->byArrival() ? $ranking->key($other) : $ranking->valuesKey($other)) < $key));
        }

        $bound = $ranking->byArrival() ? $ranking->key($score) : $ranking->valuesKey($score);

        return $this->integer($this->connection->execute('ZLEXCOUNT', $this->key('rank', $score->board, $score->period), '-', '('.$bound));
    }

    #[Override]
    public function participants(string $board, string $period, ?Circle $among = null): int
    {
        if ($among instanceof Circle) {
            return \count($this->scoresOf($board, $period, $among));
        }

        return $this->integer($this->connection->execute('ZCARD', $this->key('rank', $board, $period)));
    }

    #[Override]
    public function of(ResourceReference $member): array
    {
        $fields = $this->strings($this->connection->execute('SMEMBERS', $this->key('member-score', $member->toString())));
        $scores = [];

        foreach ($this->values('score', $fields) as $field => $held) {
            [$board, $period] = $this->decodeStrings($field);
            $scores[] = $this->scoreOf($board, $period, $member->toString(), $held);
        }

        usort($scores, static fn (Score $a, Score $b): int => [$a->board, $a->period] <=> [$b->board, $b->period]);

        return $scores;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $removed = \count($this->removeFields($this->strings($this->connection->execute('SMEMBERS', $this->key('member', $member->toString())))));

        foreach ($this->strings($this->connection->execute('SMEMBERS', $this->key('member-score', $member->toString()))) as $field) {
            [$board, $period] = $this->decodeStrings($field);
            $removed += $this->integer($this->connection->execute('EVAL', self::DELETE, '3', $this->key('score'), $this->key('rank', $board, $period), $this->key('member-score', $member->toString()), $field));
        }

        return $removed;
    }

    /**
     * The sorted-set key of the score, as the board ranks: `Ranking::key()` in the board's directions.
     */
    public function sortKey(Score $score): string
    {
        return $this->rankingOf($score->board)->key($score);
    }

    /**
     * Whether the board's sorted sets are keyed in the directions of the ranking asked.
     */
    private function keyedFor(string $board, Ranking $ranking): bool
    {
        return $this->rankingOf($board)->tieBreaks === $ranking->tieBreaks;
    }

    /**
     * The ranking of the board the key belongs to; one without tie-breaks for a board no longer declared.
     */
    private function rankingOf(string $board): Ranking
    {
        $name = $this->boardOf($board);

        return $this->boards->has($name) ? $this->boards->get($name)->ranking : new Ranking();
    }

    /**
     * The length of a sort key before the member's reference.
     */
    private function headLength(string $board): int
    {
        return Ranking::VALUE_DIGITS * (1 + \count($this->rankingOf($board)->tieBreaks)) + Ranking::MOMENT_DIGITS;
    }

    /**
     * The scores of the board in the period, among the circle's members when one is given.
     *
     * @return list<Score>
     */
    private function scoresOf(string $board, string $period, ?Circle $among): array
    {
        if ($among instanceof Circle) {
            return $this->scoresFor($board, $period, $among->members);
        }

        $keys = $this->strings($this->connection->execute('ZRANGE', $this->key('rank', $board, $period), '0', '-1'));

        return $this->scoresFor($board, $period, array_map(fn (string $key): string => substr($key, $this->headLength($board)), $keys));
    }

    /**
     * The scores the members hold on the board in the period, in the order given, leaving out those who hold none.
     *
     * @param list<string> $members as `kind:identifier`
     *
     * @return list<Score>
     */
    private function scoresFor(string $board, string $period, array $members): array
    {
        $fields = array_map(static fn (string $member): string => self::encode([$board, $period, $member]), $members);
        $scores = [];

        foreach ($this->values('score', $fields) as $field => $held) {
            $scores[] = $this->scoreOf($board, $period, $this->decodeStrings($field)[2], $held);
        }

        return $scores;
    }

    /**
     * The values of the hash's fields that it holds, by field, in the order given.
     *
     * @param list<string> $fields
     *
     * @return array<string, string>
     */
    private function values(string $hash, array $fields): array
    {
        if ([] === $fields) {
            return [];
        }

        $reply = $this->connection->execute('HMGET', $this->key($hash), ...$fields);
        $values = [];

        foreach (\is_array($reply) ? $reply : [] as $index => $value) {
            if (\is_string($value) && isset($fields[$index])) {
                $values[$fields[$index]] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<string> $fields
     *
     * @return list<Contribution>
     */
    private function contributionsOf(array $fields): array
    {
        $contributions = [];

        foreach ($this->values('contribution', $fields) as $field => $value) {
            [$board, $activity] = $this->decodeStrings($field);
            $held = $this->decode($value);

            if (!\is_string($held[0] ?? null) || !\is_string($held[1] ?? null) || !\is_int($held[2] ?? null) || !\is_int($held[3] ?? null) || !\is_array($held[4] ?? null)) {
                throw new UnexpectedValueException(\sprintf('The contribution "%s" is not one the leaderboards wrote.', $field));
            }

            $contributions[] = new Contribution($board, $held[0], ResourceReference::fromString($held[1]), $activity, $held[2], $this->moment($held[3]), $this->integers($held[4]));
        }

        return $contributions;
    }

    /**
     * Removes the contributions, by batches, and returns them.
     *
     * @param list<string> $fields
     *
     * @return list<Contribution>
     */
    private function removeFields(array $fields): array
    {
        $removed = [];

        foreach (array_chunk($fields, $this->batch) as $batch) {
            $contributions = $this->contributionsOf($batch);
            $keys = [$this->key('contribution'), $this->key('time')];

            foreach ($contributions as $contribution) {
                $keys[] = $this->key('activity', $contribution->activity);
                $keys[] = $this->key('of', $contribution->board, $contribution->period, $contribution->member->toString());
                $keys[] = $this->key('board', $this->boardOf($contribution->board));
                $keys[] = $this->key('member', $contribution->member->toString());
            }

            if ([] !== $contributions) {
                $this->connection->execute('EVAL', self::REMOVE, (string) \count($keys), ...$keys, ...array_map(static fn (Contribution $contribution): string => self::encode([$contribution->board, $contribution->activity]), $contributions));
            }

            array_push($removed, ...$contributions);
        }

        return $removed;
    }

    private function key(string $kind, string ...$parts): string
    {
        return $this->prefix.$kind.([] === $parts ? '' : ':'.self::encode(array_values($parts)));
    }

    /**
     * The board's name in a key its scores are kept under, without the digest of an object.
     */
    private function boardOf(string $key): string
    {
        return explode(Board::OBJECT_SEPARATOR, $key, 2)[0];
    }

    private function scoreOf(string $board, string $period, string $member, string $held): Score
    {
        $values = $this->decode($held);

        if (!\is_int($values[0] ?? null) || !\is_int($values[1] ?? null) || !\is_array($values[2] ?? null)) {
            throw new UnexpectedValueException(\sprintf('The score of "%s" on "%s" in "%s" is not one the leaderboards wrote.', $member, $board, $period));
        }

        return new Score($board, $period, ResourceReference::fromString($member), $values[0], $this->moment($values[1]), $this->integers($values[2]));
    }

    /**
     * @param list<mixed> $values
     */
    private static function encode(array $values): string
    {
        return json_encode($values, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $json): array
    {
        $values = json_decode($json, true, 4, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);

        return \is_array($values) ? $values : [];
    }

    /**
     * @return list<string>
     */
    private function decodeStrings(string $json): array
    {
        return array_values(array_filter($this->decode($json), \is_string(...)));
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<int>
     */
    private function integers(array $values): array
    {
        return array_values(array_filter($values, \is_int(...)));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $reply): array
    {
        return \is_array($reply) ? array_values(array_filter($reply, \is_string(...))) : [];
    }

    private function integer(mixed $reply): int
    {
        return \is_int($reply) ? $reply : (int) (\is_string($reply) ? $reply : 0);
    }

    private function micro(DateTimeImmutable $moment): int
    {
        return $moment->getTimestamp() * 1_000_000 + (int) $moment->format('u');
    }

    private function moment(int $micro): DateTimeImmutable
    {
        $seconds = intdiv($micro, 1_000_000);
        $fraction = $micro - $seconds * 1_000_000;

        if ($fraction < 0) {
            --$seconds;
            $fraction += 1_000_000;
        }

        $moment = DateTimeImmutable::createFromFormat('U.u', \sprintf('%d.%06d', $seconds, $fraction), new DateTimeZone('UTC'));

        return false === $moment ? throw InvalidLeaderboard::because(\sprintf('%d is not a moment.', $micro)) : $moment;
    }

    private function seconds(DateTimeImmutable $moment): string
    {
        return \sprintf('%d.%06d', $moment->getTimestamp(), (int) $moment->format('u'));
    }
}
