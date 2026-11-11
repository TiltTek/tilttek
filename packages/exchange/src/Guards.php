<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Exception\InvalidExchange;

/**
 * The guards of what members give each other — transfers of points, trades of items —: no member exchanges with
 * themselves, an account younger than the delay waits, and what a member gives over a period stays within the
 * allowance. Each guard is off until the application sets it, except the first.
 */
final readonly class Guards
{
    public ?DateInterval $newMemberDelay;

    public DateTimeZone $timezone;

    /**
     * @param ?string $newMemberDelay how long an account waits before it exchanges, as an ISO 8601 duration like `P7D`
     * @param string  $timezone       the timezone the allowance's periods are counted in
     */
    public function __construct(
        public ?Allowance $allowance = null,
        ?string $newMemberDelay = null,
        string $timezone = 'UTC',
        private MemberSince $memberSince = new NoMemberSince(),
    ) {
        try {
            $this->newMemberDelay = null === $newMemberDelay ? null : new DateInterval($newMemberDelay);
        } catch (Exception) {
            throw InvalidExchange::because(\sprintf('"%s" is not an ISO 8601 duration like "P7D".', $newMemberDelay));
        }

        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidExchange::because(\sprintf('"%s" is not a timezone.', $timezone));
        }
    }

    /**
     * Refuses an exchange between a member and themselves, or with an account younger than the delay.
     *
     * @throws ExchangeRefused
     */
    public function checkParties(ResourceReference $giver, ResourceReference $receiver, DateTimeImmutable $now): void
    {
        if ($giver->equals($receiver)) {
            throw ExchangeRefused::because(Refusal::SelfExchange, \sprintf('"%s" cannot exchange with themselves.', $giver->toString()));
        }

        if (!$this->newMemberDelay instanceof DateInterval) {
            return;
        }

        $joinedBy = $now->sub($this->newMemberDelay);

        foreach ([$giver, $receiver] as $member) {
            $joinedAt = $this->memberSince->joinedAt($member);

            if (!$joinedAt instanceof DateTimeImmutable || $joinedAt > $joinedBy) {
                throw ExchangeRefused::because(Refusal::NewMember, \sprintf('"%s" has not been a member long enough to exchange with others.', $member->toString()));
            }
        }
    }

    /**
     * The start, included, and the end, excluded, of the allowance's period holding the moment; both null without an
     * allowance or for one that counts ever.
     *
     * @return array{?DateTimeImmutable, ?DateTimeImmutable}
     */
    public function window(DateTimeImmutable $moment): array
    {
        return $this->allowance?->window($moment, $this->timezone) ?? [null, null];
    }

    /**
     * Refuses what would take the member past the allowance, given what they already gave in its period.
     *
     * @throws ExchangeRefused
     */
    public function checkAllowance(ResourceReference $giver, int $given, int $adding): void
    {
        if ($this->allowance instanceof Allowance && $given + $adding > $this->allowance->limit) {
            throw ExchangeRefused::because(Refusal::AllowanceReached, \sprintf('"%s" may give %d per %s; %d given already, %d more is too many.', $giver->toString(), $this->allowance->limit, $this->allowance->period->value, $given, $adding));
        }
    }
}
