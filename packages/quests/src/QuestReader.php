<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Schedule\Dated;
use Tilttek\Quests\Schedule\Permanent;
use Tilttek\Quests\Schedule\Schedule;
use Tilttek\Quests\Schedule\ScheduleReader;

/**
 * Reads a quest from plain values, as a configuration or a database row holds it:
 *
 * ```yaml
 * kind: personal            # community, team or duel
 * steps:
 *     - { code: play, verbs: ['game.played'], count: 3 }
 *     - { code: read, verbs: ['book.read'], total: 300, context: { system: dnd5e } }
 * ordered: true
 * admission: accept         # or automatic, for a personal or community quest
 * opens: '2026-12-01'       # and closes, read in the reader's timezone
 * closes: '2027-01-01'
 * after: first-steps
 * rewards: [{ kind: points, code: xp, amount: 50 }, { kind: badge, code: explorer }]
 * duel: { length: P7D }     # for a duel
 * ```
 *
 * A step without `count` nor `total` asks for one activity; a step without `code` is named `step-1`, `step-2`… after
 * its place. Schedule readers given to the reader add keys of their own, such as dates that come back each year.
 */
final readonly class QuestReader
{
    private const array KEYS = ['kind', 'steps', 'ordered', 'admission', 'opens', 'closes', 'after', 'rewards', 'duel'];

    private const array STEP_KEYS = ['code', 'verbs', 'context', 'count', 'total'];

    private DateTimeZone $timezone;

    /**
     * @param list<ScheduleReader> $schedules
     */
    public function __construct(
        string $timezone = 'UTC',
        private array $schedules = [],
    ) {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (DateInvalidTimeZoneException) {
            throw InvalidQuest::because(\sprintf('"%s" is not a timezone.', $timezone));
        }
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidQuest
     */
    public function read(string $code, array $definition): Quest
    {
        $known = array_merge(self::KEYS, ...array_map(static fn (ScheduleReader $reader): array => $reader->keys(), $this->schedules));
        $unknown = array_diff(array_keys($definition), $known);

        if ([] !== $unknown) {
            throw InvalidQuest::because(\sprintf('The quest "%s" has keys no quest has: %s.', $code, implode(', ', $unknown)));
        }

        $kind = QuestKind::tryFrom($this->string($definition, 'kind', $code) ?? QuestKind::Personal->value)
            ?? throw InvalidQuest::because(\sprintf('The kind of "%s" is personal, community, team or duel.', $code));
        $admission = $this->string($definition, 'admission', $code);
        $duel = $definition['duel'] ?? [];

        if (!\is_array($duel)) {
            throw InvalidQuest::because(\sprintf('The duel of "%s" holds its length.', $code));
        }

        return new Quest(
            $code,
            $this->steps($definition['steps'] ?? null, $code),
            $kind,
            $this->boolean($definition, 'ordered', $code),
            $this->schedule($definition, $code),
            null === $admission ? null : (Admission::tryFrom($admission) ?? throw InvalidQuest::because(\sprintf('The admission of "%s" is automatic or accept.', $code))),
            $this->string($definition, 'after', $code),
            $this->rewards($definition['rewards'] ?? [], $code),
            $this->string($duel, 'length', $code),
        );
    }

    /**
     * @return list<Step>
     */
    private function steps(mixed $values, string $code): array
    {
        if (!\is_array($values) || !array_is_list($values)) {
            throw InvalidQuest::because(\sprintf('The steps of "%s" are a list.', $code));
        }

        $steps = [];

        foreach ($values as $place => $step) {
            if (!\is_array($step) || [] !== array_diff(array_keys($step), self::STEP_KEYS)) {
                throw InvalidQuest::because(\sprintf('A step of "%s" holds its code, verbs, context, and count or total.', $code));
            }

            $count = $this->integer($step, 'count', $code);
            $total = $this->integer($step, 'total', $code);

            if (null !== $count && null !== $total) {
                throw InvalidQuest::because(\sprintf('A step of "%s" asks for a count or a total, not both.', $code));
            }

            [$target, $measure] = null === $total ? [$count ?? 1, StepMeasure::Count] : [$total, StepMeasure::Total];
            $steps[] = new Step($this->string($step, 'code', $code) ?? 'step-'.($place + 1), $this->scope($step, $code), $target, $measure);
        }

        return $steps;
    }

    /**
     * @param array<array-key, mixed> $step
     */
    private function scope(array $step, string $code): Scope
    {
        $verbs = $step['verbs'] ?? null;
        $context = $step['context'] ?? [];

        if (!\is_array($verbs) || !\is_array($context)) {
            throw InvalidQuest::because(\sprintf('A step of "%s" names its verbs as a list of patterns, and its context as values by name.', $code));
        }

        $patterns = [];

        foreach ($verbs as $verb) {
            if (!\is_string($verb)) {
                throw InvalidQuest::because(\sprintf('A step of "%s" names its verbs as a list of patterns.', $code));
            }

            $patterns[] = $verb;
        }

        $conditions = [];

        foreach ($context as $name => $value) {
            if (!\is_string($name) || (!\is_bool($value) && !\is_int($value) && !\is_string($value))) {
                throw InvalidQuest::because(\sprintf('The context of a step of "%s" holds integers, booleans and strings by name.', $code));
            }

            $conditions[$name] = $value;
        }

        return new Scope($patterns, $conditions);
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private function schedule(array $definition, string $code): Schedule
    {
        foreach ($this->schedules as $reader) {
            $schedule = $reader->read($definition, $this->timezone);

            if ($schedule instanceof Schedule) {
                return $schedule;
            }
        }

        $opens = $this->date($definition, 'opens', $code);
        $closes = $this->date($definition, 'closes', $code);

        return !$opens instanceof DateTimeImmutable && !$closes instanceof DateTimeImmutable ? new Permanent() : new Dated($opens, $closes);
    }

    /**
     * @return list<Reward>
     */
    private function rewards(mixed $values, string $code): array
    {
        if (!\is_array($values) || !array_is_list($values)) {
            throw InvalidQuest::because(\sprintf('The rewards of "%s" are a list.', $code));
        }

        $rewards = [];

        foreach ($values as $reward) {
            if (!\is_array($reward)) {
                throw InvalidQuest::because(\sprintf('A reward of "%s" holds its kind, code and amount.', $code));
            }

            $rewards[] = new Reward(
                $this->string($reward, 'kind', $code) ?? '',
                $this->string($reward, 'code', $code) ?? '',
                $this->integer($reward, 'amount', $code) ?? 1,
            );
        }

        return $rewards;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function string(array $values, string $key, string $code): ?string
    {
        $value = $values[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw InvalidQuest::because(\sprintf('The "%s" of "%s" is a string.', $key, $code));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function integer(array $values, string $key, string $code): ?int
    {
        $value = $values[$key] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw InvalidQuest::because(\sprintf('The "%s" of "%s" is an integer.', $key, $code));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function boolean(array $values, string $key, string $code): bool
    {
        $value = $values[$key] ?? false;

        if (!\is_bool($value)) {
            throw InvalidQuest::because(\sprintf('The "%s" of "%s" is true or false.', $key, $code));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function date(array $values, string $key, string $code): ?DateTimeImmutable
    {
        $value = $this->string($values, $key, $code);

        if (null === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $this->timezone);
        } catch (DateMalformedStringException) {
            throw InvalidQuest::because(\sprintf('The "%s" of "%s" is a date; "%s" is not.', $key, $code, $value));
        }
    }
}
