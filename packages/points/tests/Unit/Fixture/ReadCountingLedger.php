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
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Recorded;

/**
 * A ledger that counts the statements read through it.
 */
final class ReadCountingLedger implements Ledger
{
    public int $statements = 0;

    public function __construct(
        private readonly Ledger $ledger,
    ) {
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
        return $this->ledger->balance($account, $currency);
    }

    #[Override]
    public function net(Account $to, Account $from, string $currency, ?DateTimeImmutable $since = null, ?DateTimeImmutable $until = null): int
    {
        return $this->ledger->net($to, $from, $currency, $since, $until);
    }

    #[Override]
    public function statement(Account $account, ?string $currency, int $after, int $limit): array
    {
        ++$this->statements;

        return $this->ledger->statement($account, $currency, $after, $limit);
    }

    #[Override]
    public function membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): array
    {
        return $this->ledger->membersCreditedBefore($currency, $cutoff);
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
}
