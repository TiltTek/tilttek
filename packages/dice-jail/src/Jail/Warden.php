<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Jail;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Tilttek\DiceJail\Contract\JailStore;
use Tilttek\DiceJail\Exception\InvalidJailEntry;
use Tilttek\DiceJail\Exception\JailConflict;
use Tilttek\DiceJail\Exception\JailRefused;

/**
 * The dice jail: a player sends an unlucky die to jail, for a while or until they release it, and the history of each
 * die is kept.
 *
 * Jailing is a gesture of play. It needs no statistics and changes nothing in how the die rolls; the evidence a player
 * may attach is what the table was shown. The warden holds no state between calls: everything is in the store.
 */
final readonly class Warden
{
    /** The most dice a keeper holds in jail at once. */
    public const int MAX_INMATES = 1_000;

    /** The longest identifier of a keeper or a die, in bytes. */
    public const int MAX_IDENTIFIER = 128;

    /** The longest reason, in characters. */
    public const int MAX_REASON = 500;

    public function __construct(
        private JailStore $store,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Sends the die to jail.
     *
     * @param string             $keeper the stable identifier of the member whose die it is, never an email address
     * @param ?DateTimeImmutable $until  when the sentence ends by itself; null to keep the die until released
     *
     * @throws InvalidJailEntry when the keeper, the die or the reason is refused
     * @throws JailRefused      when the die is in jail already, the sentence ends now or before, or the jail is full
     * @throws JailConflict     when another writer changed the die's history at the same time
     */
    public function jail(string $keeper, string $die, string $reason = '', ?DateTimeImmutable $until = null, ?Evidence $evidence = null): Inmate
    {
        $this->check($keeper, $die, $reason);
        $now = $this->clock->now();

        if ($until instanceof DateTimeImmutable && $until <= $now) {
            throw JailRefused::sentenceInThePast();
        }

        $events = $this->store->events($keeper, $die);

        if ($this->serving($events, $now) instanceof Inmate) {
            throw JailRefused::alreadyJailed($die);
        }

        if (\count($this->inmates($keeper)) >= self::MAX_INMATES) {
            throw JailRefused::full(self::MAX_INMATES);
        }

        $event = new JailEvent(EventKind::Jailed, $keeper, $die, \count($events) + 1, $now, $reason, $until, $evidence);
        $this->store->append($event);

        return new Inmate($event);
    }

    /**
     * Releases the die before its sentence ends.
     *
     * @throws InvalidJailEntry when the keeper, the die or the reason is refused
     * @throws JailRefused      when the die is not in jail, or has served its sentence already
     * @throws JailConflict     when another writer changed the die's history at the same time
     */
    public function release(string $keeper, string $die, string $reason = ''): JailEvent
    {
        $this->check($keeper, $die, $reason);
        $now = $this->clock->now();
        $events = $this->store->events($keeper, $die);

        if (!$this->serving($events, $now) instanceof Inmate) {
            throw JailRefused::notJailed($die);
        }

        $event = new JailEvent(EventKind::Released, $keeper, $die, \count($events) + 1, $now, $reason);
        $this->store->append($event);

        return $event;
    }

    /** The die, if it is in jail now. */
    public function inmate(string $keeper, string $die): ?Inmate
    {
        return $this->serving($this->store->events($keeper, $die), $this->clock->now());
    }

    /**
     * The keeper's dice in jail now, in the order they first went to jail.
     *
     * @return list<Inmate>
     */
    public function inmates(string $keeper): array
    {
        $now = $this->clock->now();
        $inmates = [];

        foreach ($this->store->dice($keeper) as $die) {
            $inmate = $this->serving($this->store->events($keeper, $die), $now);

            if ($inmate instanceof Inmate) {
                $inmates[] = $inmate;
            }
        }

        return $inmates;
    }

    /**
     * Every time the die went to jail and came out, in order.
     *
     * @return list<JailEvent>
     */
    public function history(string $keeper, string $die): array
    {
        return $this->store->events($keeper, $die);
    }

    /**
     * @param list<JailEvent> $events
     */
    private function serving(array $events, DateTimeImmutable $now): ?Inmate
    {
        $last = array_last($events);

        if (!$last instanceof JailEvent || EventKind::Jailed !== $last->kind) {
            return null;
        }

        $inmate = new Inmate($last);

        return $inmate->isServing($now) ? $inmate : null;
    }

    /**
     * @throws InvalidJailEntry
     */
    private function check(string $keeper, string $die, string $reason): void
    {
        if (!$this->isIdentifier($keeper)) {
            throw InvalidJailEntry::identifier('keeper', self::MAX_IDENTIFIER);
        }

        if (str_contains($keeper, '@')) {
            throw InvalidJailEntry::emailKeeper();
        }

        if (!$this->isIdentifier($die)) {
            throw InvalidJailEntry::identifier('die', self::MAX_IDENTIFIER);
        }

        if (!mb_check_encoding($reason, 'UTF-8') || mb_strlen($reason) > self::MAX_REASON || 1 === preg_match('/[^\P{Cc}\n]/u', $reason)) {
            throw InvalidJailEntry::reason(self::MAX_REASON);
        }
    }

    private function isIdentifier(string $value): bool
    {
        return '' !== $value
            && \strlen($value) <= self::MAX_IDENTIFIER
            && mb_check_encoding($value, 'UTF-8')
            && 0 === preg_match('/\p{Cc}/u', $value);
    }
}
