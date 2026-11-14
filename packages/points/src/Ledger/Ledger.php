<?php

declare(strict_types=1);

namespace Tilttek\Points\Ledger;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Points\Account;
use Tilttek\Points\Erasure;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\LotsCheckpoint;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Recorded;

/**
 * Where the ledger is kept. Movements are appended at positions that only grow, each written once per key, even when
 * two processes write it at the same moment. Two operations are the exceptions to "never rewritten": forgetting the
 * derived movements before a rebuild, and erasing a member.
 */
interface Ledger
{
    /**
     * Appends the movement, or returns the entry already written under its key.
     *
     * @throws MovementConflict when the key is written for another movement
     */
    public function append(Movement $movement, DateTimeImmutable $at): Recorded;

    /**
     * Appends the movement if the debited account's balance covers it, checked and written as one step that another
     * process cannot slip between; returns the entry already written under its key without checking again.
     *
     * @throws InsufficientPoints when the balance is smaller than the amount
     * @throws MovementConflict   when the key is written for another movement
     */
    public function appendCovered(Movement $movement, DateTimeImmutable $at): Recorded;

    /**
     * Appends the movement if the debited account has no entry in the movement's currency after the position, checked
     * and written as one step that a covered append from the same account cannot slip between; returns null, writing
     * nothing, when it has one. The entry already written under the key is returned without checking: its `new` is
     * false.
     *
     * @throws MovementConflict when the key is written for another movement
     */
    public function appendAfter(Movement $movement, DateTimeImmutable $at, int $after): ?Recorded;

    public function find(MovementKey $key): ?LedgerEntry;

    /**
     * The entries whose cause is this activity key, in the order of their positions.
     *
     * @return list<LedgerEntry>
     */
    public function causedBy(string $cause): array;

    /**
     * The sum of the account's movements in the currency: what it was credited, less what it was debited.
     */
    public function balance(Account $account, string $currency): int;

    /**
     * What one account received from another in the currency, less what it gave back to it, counting the movements
     * whose moment is from `since`, included, to `until`, excluded; a bound left null does not limit.
     */
    public function net(Account $to, Account $from, string $currency, ?DateTimeImmutable $since = null, ?DateTimeImmutable $until = null): int;

    /**
     * The entries of an account after a position, in the currency or in all of them, in the order of their positions,
     * at most `limit` of them.
     *
     * @return list<LedgerEntry>
     *
     * @throws InvalidPoints when the position is negative or the limit is below 1
     */
    public function statement(Account $account, ?string $currency, int $after, int $limit): array;

    /**
     * The members' accounts credited in the currency by a movement whose moment is `cutoff` or earlier, sorted: those
     * that may hold a credit due to expire.
     *
     * @return list<Account>
     */
    public function membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): array;

    /**
     * The checkpoint of the account's lots in the currency that `keepLots()` kept last; null when none is kept.
     */
    public function keptLots(Account $account, string $currency): ?LotsCheckpoint;

    /**
     * Keeps the checkpoint of its account's lots in its currency, in place of the one kept, unless that one stands at
     * the same position or a later one.
     */
    public function keepLots(LotsCheckpoint $checkpoint): void;

    /**
     * Deletes the derived movements — earned, reversed, expired — whose moment is `since` or later, all of them when
     * `since` is null, before the activity of that period is replayed, and every checkpoint of lots; returns how many
     * movements it deleted.
     */
    public function forgetDerived(?DateTimeImmutable $since): int;

    /**
     * Deletes the movements between the member and the ledger's own accounts and the checkpoints of the member's lots,
     * and writes `erased:member` in place of the member in the movements with another member.
     */
    public function eraseMember(ResourceReference $member): Erasure;
}
