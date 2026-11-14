<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Points\Account;
use Tilttek\Points\Erasure;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\LotsCheckpoint;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Recorded;

/**
 * A ledger that runs what another process does right after a read through it — a statement or a balance —, as many
 * times as told: the step that slips between a read and the write that follows.
 */
final class InterleavingLedger implements Ledger
{
    /**
     * @var callable(): mixed
     */
    private $meanwhile;

    /**
     * @param 'balance'|'statement' $after
     * @param callable(): mixed     $meanwhile
     */
    public function __construct(
        private readonly Ledger $ledger,
        private readonly string $after,
        callable $meanwhile,
        private int $times = 1,
    ) {
        $this->meanwhile = $meanwhile;
    }

    #[Override]
    public function append(Movement $movement, DateTimeImmutable $at): Recorded
    {
        return $this->ledger->append($movement, $at);
    }

    #[Override]
    public function appendCovered(Movement $movement, DateTimeImmutable $at): Recorded
    {
        return $this->ledger->appendCovered($movement, $at);
    }

    #[Override]
    public function appendAfter(Movement $movement, DateTimeImmutable $at, int $after): ?Recorded
    {
        return $this->ledger->appendAfter($movement, $at, $after);
    }

    #[Override]
    public function find(MovementKey $key): ?LedgerEntry
    {
        return $this->ledger->find($key);
    }

    #[Override]
    public function causedBy(string $cause): array
    {
        return $this->ledger->causedBy($cause);
    }

    #[Override]
    public function balance(Account $account, string $currency): int
    {
        $balance = $this->ledger->balance($account, $currency);
        $this->meanwhile('balance');

        return $balance;
    }

    #[Override]
    public function net(Account $to, Account $from, string $currency, ?DateTimeImmutable $since = null, ?DateTimeImmutable $until = null): int
    {
        return $this->ledger->net($to, $from, $currency, $since, $until);
    }

    #[Override]
    public function statement(Account $account, ?string $currency, int $after, int $limit): array
    {
        $statement = $this->ledger->statement($account, $currency, $after, $limit);
        $this->meanwhile('statement');

        return $statement;
    }

    #[Override]
    public function membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): array
    {
        return $this->ledger->membersCreditedBefore($currency, $cutoff);
    }

    #[Override]
    public function keptLots(Account $account, string $currency): ?LotsCheckpoint
    {
        return $this->ledger->keptLots($account, $currency);
    }

    #[Override]
    public function keepLots(LotsCheckpoint $checkpoint): void
    {
        $this->ledger->keepLots($checkpoint);
    }

    #[Override]
    public function forgetDerived(?DateTimeImmutable $since): int
    {
        return $this->ledger->forgetDerived($since);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        return $this->ledger->eraseMember($member);
    }

    private function meanwhile(string $read): void
    {
        if ($read === $this->after && $this->times > 0) {
            --$this->times;
            ($this->meanwhile)();
        }
    }
}
