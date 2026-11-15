<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Exception\BountyRefused;
use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * A bounty a member offered on a subject, its points paid in advance: the claims made on it, the awards its sponsor
 * gave, and where it stands. It is an explicit state machine — see `BountyState` —: each move checks the member, the
 * state and the time, and returns the bounty at its next version, or the same object when the move was made already;
 * a move the rules refuse throws `BountyRefused` and changes nothing.
 *
 * Claims are made until `endsAt`; the sponsor awards them until `graceEndsAt`, the whole amount to one claim or shared
 * between several. The bounty is settled once its whole amount is awarded, when its sponsor closes it, or once its
 * grace is over: the awards are paid, the rest goes back to the sponsor. Every time is in UTC, and times are compared
 * by their order, never by `==` or `===`.
 */
final readonly class Bounty
{
    public const int MAX_CLAIMS = 200;

    public const int MAX_CLAIMS_PER_MEMBER = 5;

    public const int MAX_AWARDS = 20;

    private const array FIELDS = ['id', 'sponsor', 'subject', 'reason', 'prize', 'unit', 'opened_at', 'ends_at', 'grace_ends_at', 'claims', 'awards', 'state', 'settled_at', 'version'];

    /**
     * @param list<Claim> $claims
     * @param list<Award> $awards
     */
    private function __construct(
        public string $id,
        public ResourceReference $sponsor,
        public ResourceReference $subject,
        public BountyReason $reason,
        public Prize $prize,
        public DateTimeImmutable $openedAt,
        public DateTimeImmutable $endsAt,
        public DateTimeImmutable $graceEndsAt,
        public array $claims,
        public array $awards,
        public BountyState $state,
        public ?DateTimeImmutable $settledAt,
        public int $version,
    ) {
    }

    /**
     * The bounty a draft asks for, offered at the moment by its sponsor, within the site's rules.
     *
     * @throws InvalidBounty when the draft is outside the rules: its length or its amount
     */
    public static function offer(BountyDraft $draft, ResourceReference $sponsor, DateTimeImmutable $at, BountyRules $rules): self
    {
        $at = Instant::utc($at);
        $length = $draft->endsAt->getTimestamp() - $at->getTimestamp();

        if ($length < $rules->shortest || $length > $rules->longest) {
            throw InvalidBounty::because(\sprintf('A bounty lasts %d to %d seconds; this one lasts %d.', $rules->shortest, $rules->longest, $length));
        }

        if ($draft->prize->amount < $rules->smallestPrize || $draft->prize->amount > $rules->largestPrize) {
            throw InvalidBounty::because(\sprintf('A bounty is %d to %d points; %d is not.', $rules->smallestPrize, $rules->largestPrize, $draft->prize->amount));
        }

        return new self($draft->id, $sponsor, $draft->subject, $draft->reason, $draft->prize, $at, $draft->endsAt, $draft->endsAt->modify(\sprintf('+%d seconds', $rules->grace)), [], [], BountyState::Open, null, 1);
    }

    /**
     * Whether the bounty is the one the draft asks for, by the same sponsor: an offer sent again.
     */
    public function matches(BountyDraft $draft, ResourceReference $sponsor): bool
    {
        return $this->id === $draft->id
            && $this->sponsor->equals($sponsor)
            && $this->subject->equals($draft->subject)
            && $this->reason === $draft->reason
            && $this->prize->equals($draft->prize)
            && Instant::write($this->endsAt) === Instant::write($draft->endsAt);
    }

    public function claim(string $id): ?Claim
    {
        foreach ($this->claims as $claim) {
            if ($claim->id === $id) {
                return $claim;
            }
        }

        return null;
    }

    public function award(string $id): ?Award
    {
        foreach ($this->awards as $award) {
            if ($award->id === $id) {
                return $award;
            }
        }

        return null;
    }

    /**
     * The points awarded so far.
     */
    public function awarded(): int
    {
        return array_sum(array_map(static fn (Award $award): int => $award->amount, $this->awards));
    }

    /**
     * The points awarded to each claimant, in the order of their first award: what the settlement pays.
     *
     * @return list<Payout>
     */
    public function payouts(): array
    {
        $members = [];
        $amounts = [];

        foreach ($this->awards as $award) {
            $claimant = $this->claim($award->claim)->claimant ?? $this->sponsor;
            $key = $claimant->toString();
            $members[$key] = $claimant;
            $amounts[$key] = ($amounts[$key] ?? 0) + $award->amount;
        }

        return array_map(static fn (string $key): Payout => new Payout($members[$key], $amounts[$key]), array_keys($members));
    }

    /**
     * A member claims the bounty, until it ends. The same claim sent again changes nothing.
     *
     * @throws InvalidBounty when the claim's identifier or evidence is not one the library takes
     * @throws BountyRefused
     */
    public function claimWith(string $claimId, ResourceReference $claimant, string $evidence, DateTimeImmutable $at): self
    {
        $existing = $this->claim($claimId);

        if ($existing instanceof Claim) {
            if ($existing->claimant->equals($claimant) && $existing->evidence === $evidence) {
                return $this;
            }

            throw BountyRefused::claimReused($this->id, $claimId);
        }

        if ($this->sponsor->equals($claimant)) {
            throw BountyRefused::sponsorClaims($this->id);
        }

        $this->open('it takes no claim');

        if ($at >= $this->endsAt) {
            throw BountyRefused::closed($this->id, 'claim');
        }

        $own = \count(array_filter($this->claims, static fn (Claim $claim): bool => $claim->claimant->equals($claimant)));

        if ($own >= self::MAX_CLAIMS_PER_MEMBER) {
            throw BountyRefused::full($this->id, 'claims of '.$claimant->toString(), self::MAX_CLAIMS_PER_MEMBER);
        }

        if (\count($this->claims) >= self::MAX_CLAIMS) {
            throw BountyRefused::full($this->id, 'claims', self::MAX_CLAIMS);
        }

        return clone ($this, ['claims' => [...$this->claims, new Claim($claimId, $claimant, $evidence, $at)], 'version' => $this->version + 1]);
    }

    /**
     * The sponsor awards points to a claim, until the grace ends; once the whole amount is awarded, the bounty is
     * settled. The same award sent again changes nothing.
     *
     * @throws InvalidBounty when the award's identifier or amount is not one the library takes
     * @throws BountyRefused
     */
    public function awardTo(string $awardId, string $claimId, int $amount, ResourceReference $actor, DateTimeImmutable $at): self
    {
        if (!$this->sponsor->equals($actor)) {
            throw BountyRefused::notSponsor($this->id, $actor);
        }

        $existing = $this->award($awardId);

        if ($existing instanceof Award) {
            if ($existing->claim === $claimId && $existing->amount === $amount) {
                return $this;
            }

            throw BountyRefused::awardReused($this->id, $awardId);
        }

        $this->open('it is not awarded');

        if ($at >= $this->graceEndsAt) {
            throw BountyRefused::closed($this->id, 'award');
        }

        if (!$this->claim($claimId) instanceof Claim) {
            throw BountyRefused::unknownClaim($this->id, $claimId);
        }

        $left = $this->prize->amount - $this->awarded();

        if ($amount > $left) {
            throw BountyRefused::overPrize($this->id, $left, $amount);
        }

        if (\count($this->awards) >= self::MAX_AWARDS) {
            throw BountyRefused::full($this->id, 'awards', self::MAX_AWARDS);
        }

        $awarded = clone ($this, ['awards' => [...$this->awards, new Award($awardId, $claimId, $amount, $at)], 'version' => $this->version + 1]);

        return $amount === $left ? $awarded->settled(BountyState::Awarded, $at) : $awarded;
    }

    /**
     * The sponsor closes the bounty with what it awarded: the awards are paid, the rest goes back to the sponsor.
     *
     * @throws BountyRefused
     */
    public function close(ResourceReference $actor, DateTimeImmutable $at): self
    {
        if (!$this->sponsor->equals($actor)) {
            throw BountyRefused::notSponsor($this->id, $actor);
        }

        if (BountyState::Awarded === $this->state) {
            return $this;
        }

        $this->open('it cannot be closed');

        if ([] === $this->awards) {
            throw BountyRefused::nothingAwarded($this->id);
        }

        return $this->settled(BountyState::Awarded, $at);
    }

    /**
     * The sponsor withdraws the bounty before anyone claims it; its points go back to the sponsor.
     *
     * @throws BountyRefused
     */
    public function cancel(ResourceReference $actor, DateTimeImmutable $at): self
    {
        if (!$this->sponsor->equals($actor)) {
            throw BountyRefused::notSponsor($this->id, $actor);
        }

        if (BountyState::Cancelled === $this->state) {
            return $this;
        }

        $this->open('it cannot be cancelled');

        if ([] !== $this->claims) {
            throw BountyRefused::claimsMade($this->id);
        }

        return $this->settled(BountyState::Cancelled, $at);
    }

    /**
     * The bounty ends once its grace is over: awarded when its sponsor awarded something — the awards paid, the rest
     * given back —, expired otherwise — everything given back.
     *
     * @throws BountyRefused
     */
    public function expire(DateTimeImmutable $at): self
    {
        if ($this->state->isSettled()) {
            return $this;
        }

        if ($at < $this->graceEndsAt) {
            throw BountyRefused::notDue($this->id);
        }

        return $this->settled([] === $this->awards ? BountyState::Expired : BountyState::Awarded, $at);
    }

    /**
     * Whether the bounty is open and its grace over at the moment.
     */
    public function isDue(DateTimeImmutable $at): bool
    {
        return BountyState::Open === $this->state && $at >= $this->graceEndsAt;
    }

    /**
     * Whether the bounty names the member: as its sponsor or as a claimant.
     */
    public function involves(ResourceReference $member): bool
    {
        return $this->sponsor->equals($member) || array_any($this->claims, static fn (Claim $claim): bool => $claim->claimant->equals($member));
    }

    /**
     * The member leaves the bounty, before their erasure. The open bounty they sponsor is settled at once: awarded when
     * they awarded something — the awards paid, the rest given back to them —, expired when it has claims and no award,
     * cancelled otherwise. Their claims on an open bounty are withdrawn, with the awards given to them, which are not
     * paid: those points stay to award. A settled bounty, or one where nothing of theirs waits, stays as it is.
     */
    public function leave(ResourceReference $member, DateTimeImmutable $at): self
    {
        if ($this->state->isSettled()) {
            return $this;
        }

        if ($this->sponsor->equals($member)) {
            return $this->settled([] !== $this->awards ? BountyState::Awarded : ([] !== $this->claims ? BountyState::Expired : BountyState::Cancelled), $at);
        }

        $claims = array_values(array_filter($this->claims, static fn (Claim $claim): bool => !$claim->claimant->equals($member)));

        if (\count($claims) === \count($this->claims)) {
            return $this;
        }

        $kept = array_map(static fn (Claim $claim): string => $claim->id, $claims);

        return clone ($this, [
            'claims' => $claims,
            'awards' => array_values(array_filter($this->awards, static fn (Award $award): bool => \in_array($award->claim, $kept, true))),
            'version' => $this->version + 1,
        ]);
    }

    /**
     * The bounty with the member replaced by the pseudonym wherever it names them — sponsor, claimant —, and the
     * evidence of their claims replaced by the pseudonym too.
     */
    public function pseudonymized(ResourceReference $member, ResourceReference $pseudonym): self
    {
        if (!$this->involves($member)) {
            return $this;
        }

        return clone ($this, [
            'sponsor' => $this->sponsor->equals($member) ? $pseudonym : $this->sponsor,
            'claims' => array_map(static fn (Claim $claim): Claim => $claim->claimant->equals($member) ? new Claim($claim->id, $pseudonym, $pseudonym->toString(), $claim->claimedAt) : $claim, $this->claims),
            'version' => $this->version + 1,
        ]);
    }

    /**
     * The bounty as a record to keep, which `fromArray()` reads back.
     *
     * @return array{id: string, sponsor: string, subject: string, reason: string, prize: int, unit: string, opened_at: string, ends_at: string, grace_ends_at: string, claims: list<array{id: string, claimant: string, evidence: string, claimed_at: string}>, awards: list<array{id: string, claim: string, amount: int, awarded_at: string}>, state: string, settled_at: ?string, version: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sponsor' => $this->sponsor->toString(),
            'subject' => $this->subject->toString(),
            'reason' => $this->reason->value,
            'prize' => $this->prize->amount,
            'unit' => $this->prize->unit,
            'opened_at' => Instant::write($this->openedAt),
            'ends_at' => Instant::write($this->endsAt),
            'grace_ends_at' => Instant::write($this->graceEndsAt),
            'claims' => array_map(static fn (Claim $claim): array => $claim->toArray(), $this->claims),
            'awards' => array_map(static fn (Award $award): array => $award->toArray(), $this->awards),
            'state' => $this->state->value,
            'settled_at' => $this->settledAt instanceof DateTimeImmutable ? Instant::write($this->settledAt) : null,
            'version' => $this->version,
        ];
    }

    /**
     * Reads back a record `toArray()` wrote, checked field by field and as a whole.
     *
     * @param array<array-key, mixed> $record
     *
     * @throws InvalidBounty when the record is not one `toArray()` writes
     */
    public static function fromArray(array $record): self
    {
        $read = new Reader($record, 'a bounty', self::FIELDS);
        $claims = [];
        $awards = [];

        foreach ($read->list('claims', self::MAX_CLAIMS) as $claim) {
            $claims[] = BountyRecord::claim($claim);
        }

        foreach ($read->list('awards', self::MAX_AWARDS) as $award) {
            $awards[] = BountyRecord::award($award);
        }

        $bounty = new self(
            Check::identifier($read->string('id'), 'A bounty identifier'),
            $read->member('sponsor'),
            $read->member('subject'),
            $read->enum('reason', BountyReason::class),
            new Prize($read->int('prize'), $read->string('unit')),
            $read->time('opened_at'),
            $read->time('ends_at'),
            $read->time('grace_ends_at'),
            $claims,
            $awards,
            $read->enum('state', BountyState::class),
            $read->nullableTime('settled_at'),
            $read->int('version'),
        );
        BountyRecord::verify($bounty);

        return $bounty;
    }

    /**
     * @throws BountyRefused
     */
    private function open(string $what): void
    {
        if (BountyState::Open !== $this->state) {
            throw BountyRefused::inState($this->id, $this->state, $what);
        }
    }

    private function settled(BountyState $state, DateTimeImmutable $at): self
    {
        return clone ($this, ['state' => $state, 'settledAt' => Instant::utc($at), 'version' => $this->version + 1]);
    }
}
