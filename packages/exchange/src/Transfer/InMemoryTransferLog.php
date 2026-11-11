<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Transfer;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Erasure;
use Tilttek\Exchange\ExchangeKey;

/**
 * The journal of transfers in memory, for tests and for applications that keep nothing between requests.
 */
final class InMemoryTransferLog implements TransferLog
{
    /**
     * @var array<string, Transfer>
     */
    private array $transfers = [];

    #[Override]
    public function record(Transfer $transfer): bool
    {
        if (isset($this->transfers[$transfer->key->value])) {
            return false;
        }

        $this->transfers[$transfer->key->value] = $transfer;

        return true;
    }

    #[Override]
    public function find(ExchangeKey $key): ?Transfer
    {
        return $this->transfers[$key->value] ?? null;
    }

    #[Override]
    public function given(ResourceReference $member, string $currency, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        $given = 0;

        foreach ($this->transfers as $transfer) {
            if ($transfer->from->equals($member)
                && $transfer->currency === $currency
                && (!$since instanceof DateTimeImmutable || $transfer->at >= $since)
                && (!$until instanceof DateTimeImmutable || $transfer->at < $until)) {
                $given += $transfer->amount;
            }
        }

        return $given;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return array_values(array_filter($this->transfers, static fn (Transfer $transfer): bool => $transfer->from->equals($member) || $transfer->to->equals($member)));
    }

    #[Override]
    public function latest(int $limit): array
    {
        return \array_slice(array_reverse(array_values($this->transfers)), 0, $limit);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $erased = Erasure::erasedMember();
        $changed = 0;

        foreach ($this->transfers as $key => $transfer) {
            $from = $transfer->from->equals($member) ? $erased : $transfer->from;
            $to = $transfer->to->equals($member) ? $erased : $transfer->to;

            if ($from !== $transfer->from || $to !== $transfer->to) {
                $this->transfers[$key] = new Transfer($transfer->key, $from, $to, $transfer->amount, $transfer->currency, $transfer->at);
                ++$changed;
            }
        }

        return $changed;
    }

    /**
     * Empties the log: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->transfers = [];
    }
}
