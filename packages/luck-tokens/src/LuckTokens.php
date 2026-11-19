<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

use Closure;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Randomizer;
use Symfony\Component\Clock\NativeClock;
use Tilttek\LuckTokens\Contract\HolderAccess;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Contract\TokenRules;
use Tilttek\LuckTokens\Event\HolderErased;
use Tilttek\LuckTokens\Event\SessionStarted;
use Tilttek\LuckTokens\Event\TokensGranted;
use Tilttek\LuckTokens\Event\TokensSpent;
use Tilttek\LuckTokens\Event\TokensTransferred;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Exception\TokenRefused;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\Store\SelfAccess;

/**
 * The tokens of a table — luck, inspiration, fate —, granted, spent and passed, and set again at the start of each
 * session, every move an entry of the table's ledger.
 *
 * Each operation carries an identifier chosen by the caller: sent again — a double click, a retried request —, it
 * writes nothing more and gives back what it wrote; the same identifier with other values is refused. A balance never
 * goes under zero nor over the cap of its kind. Two operations at once on the same table do not both write after the
 * same entry: the second reads the ledger again and is checked again, three times in all.
 *
 * The service keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class LuckTokens
{
    public const int MAX_ENTRIES = 100_000;

    public const int MAX_HOLDERS = 100;

    public const int ATTEMPTS = 3;

    /**
     * The kind of the pseudonyms that take the place of erased members: `erased:` and 32 random hexadecimal digits.
     */
    public const string ERASED_KIND = 'erased';

    public function __construct(
        private TokenRules $rules,
        private TokenLedger $ledger,
        private ?EventDispatcherInterface $events = null,
        private HolderAccess $access = new SelfAccess(),
        private ClockInterface $clock = new NativeClock(),
        private int $maxEntries = self::MAX_ENTRIES,
    ) {
    }

    /**
     * @throws TokenRefused when the table hands out no tokens
     */
    public function rules(ResourceReference $table): TableRules
    {
        return $this->rules->rulesFor($table) ?? throw TokenRefused::unknownTable($table);
    }

    /**
     * Grants tokens to a holder: by a game master, or by anyone when the kind allows it.
     *
     * @throws TokenRefused     when the rules do not allow it, the holder would go over the cap, or the identifier names
     *                          another operation
     * @throws InvalidTokenRule when the amount, the identifier or the reason is not one the library takes
     * @throws LedgerConflict   when other writers kept the ledger busy three times in a row
     */
    public function grant(ResourceReference $table, string $kind, ResourceReference $holder, int $amount, ResourceReference $actor, string $operation, ?string $reason = null): Receipt
    {
        $intent = LedgerEntry::intentOf(EntryType::Granted, $kind, $holder, $amount, $actor);

        return $this->write(
            $table,
            $operation,
            $actor,
            $reason,
            static fn (array $done): bool => $done[0]->intent() === $intent,
            function (TableRules $rules, Closure $held, Closure $entry) use ($kind, $holder, $amount, $actor): array {
                $token = $this->amount($rules->kind($kind), $amount);

                if (GrantPolicy::GameMasters === $token->grants && !$rules->isGameMaster($actor)) {
                    throw TokenRefused::notGameMaster($actor, \sprintf('grants "%s"', $kind));
                }

                $balance = $held($kind, $holder);

                if ($balance + $amount > $token->cap) {
                    throw TokenRefused::overCap($kind, $holder, $balance, $amount, $token->cap);
                }

                return [$entry(EntryType::Granted, $kind, $holder, $amount, $balance + $amount)];
            },
            static fn (array $entries): TokensGranted => new TokensGranted($entries[0]),
        );
    }

    /**
     * Spends tokens of a holder: by the holder, by whoever controls them, or by a game master.
     *
     * @throws TokenRefused     when the actor may not, the holder has too few, or the identifier names another operation
     * @throws InvalidTokenRule when the amount, the identifier or the reason is not one the library takes
     * @throws LedgerConflict   when other writers kept the ledger busy three times in a row
     */
    public function spend(ResourceReference $table, string $kind, ResourceReference $holder, int $amount, ResourceReference $actor, string $operation, ?string $reason = null): Receipt
    {
        $intent = LedgerEntry::intentOf(EntryType::Spent, $kind, $holder, $amount, $actor);

        return $this->write(
            $table,
            $operation,
            $actor,
            $reason,
            static fn (array $done): bool => $done[0]->intent() === $intent,
            function (TableRules $rules, Closure $held, Closure $entry) use ($kind, $holder, $amount, $actor): array {
                $this->amount($rules->kind($kind), $amount);
                $this->mayMove($rules, $actor, $holder);
                $balance = $held($kind, $holder);

                if ($amount > $balance) {
                    throw TokenRefused::insufficient($kind, $holder, $balance, $amount);
                }

                return [$entry(EntryType::Spent, $kind, $holder, -$amount, $balance - $amount)];
            },
            static fn (array $entries): TokensSpent => new TokensSpent($entries[0]),
        );
    }

    /**
     * Passes tokens of a kind that allows it from one holder to another, both entries written together.
     *
     * @throws TokenRefused     when the kind is not transferable, the actor may not, the giver has too few, the receiver
     *                          would go over the cap, or the identifier names another operation
     * @throws InvalidTokenRule when the amount, the identifier or the reason is not one the library takes
     * @throws LedgerConflict   when other writers kept the ledger busy three times in a row
     */
    public function transfer(ResourceReference $table, string $kind, ResourceReference $from, ResourceReference $to, int $amount, ResourceReference $actor, string $operation, ?string $reason = null): Receipt
    {
        $intent = LedgerEntry::intentOf(EntryType::TransferredOut, $kind, $from, $amount, $actor);

        return $this->write(
            $table,
            $operation,
            $actor,
            $reason,
            static fn (array $done): bool => $done[0]->intent() === $intent && $done[1]->holder->equals($to),
            function (TableRules $rules, Closure $held, Closure $entry) use ($kind, $from, $to, $amount, $actor): array {
                $token = $this->amount($rules->kind($kind), $amount);

                if (!$token->transferable) {
                    throw TokenRefused::notTransferable($kind);
                }

                if ($from->equals($to)) {
                    throw TokenRefused::sameHolder($from);
                }

                $this->mayMove($rules, $actor, $from);
                $given = $held($kind, $from);
                $received = $held($kind, $to);

                if ($amount > $given) {
                    throw TokenRefused::insufficient($kind, $from, $given, $amount);
                }

                if ($received + $amount > $token->cap) {
                    throw TokenRefused::overCap($kind, $to, $received, $amount, $token->cap);
                }

                return [
                    $entry(EntryType::TransferredOut, $kind, $from, -$amount, $given - $amount),
                    $entry(EntryType::TransferredIn, $kind, $to, $amount, $received + $amount),
                ];
            },
            static fn (array $entries): TokensTransferred => new TokensTransferred($entries[0], $entries[1]),
        );
    }

    /**
     * Starts a session: a game master sets every holder's tokens by the carry-over and the refresh of each kind. The
     * holders are those of the ledger, and those given — players joining the table, who receive the refresh.
     *
     * @param list<ResourceReference> $holders
     *
     * @throws TokenRefused     when the actor is not a game master, the session was started by another operation, or
     *                          the identifier names another operation
     * @throws InvalidTokenRule when the session, the identifier or the number of holders is not one the library takes
     * @throws LedgerConflict   when other writers kept the ledger busy three times in a row
     */
    public function startSession(ResourceReference $table, string $session, array $holders, ResourceReference $actor, string $operation): Receipt
    {
        LedgerEntry::identifier($session, 'A session');

        if (\count($holders) > self::MAX_HOLDERS) {
            throw InvalidTokenRule::because(\sprintf('A session starts with %d holders given at most.', self::MAX_HOLDERS));
        }

        $intent = LedgerEntry::intentOf(EntryType::SessionStarted, null, $actor, 0, $actor, $session);

        return $this->write(
            $table,
            $operation,
            $actor,
            null,
            static fn (array $done): bool => $done[0]->intent() === $intent,
            function (TableRules $rules, Closure $held, Closure $entry) use ($table, $session, $holders, $actor): array {
                if (!$rules->isGameMaster($actor)) {
                    throw TokenRefused::notGameMaster($actor, 'starts a session');
                }

                if ($this->ledger->hasSession($table, $session)) {
                    throw TokenRefused::sessionStarted($session);
                }

                $entries = [$entry(EntryType::SessionStarted, null, $actor, 0, 0, $session)];

                $everyone = $this->holders($this->ledger->holders($table), $holders);

                foreach ($rules->kinds() as $token) {
                    foreach ($everyone as $holder) {
                        $balance = $held($token->key, $holder);
                        $refreshed = $token->atSessionStart($balance);

                        if ($refreshed !== $balance) {
                            $entries[] = $entry(EntryType::Refreshed, $token->key, $holder, $refreshed - $balance, $refreshed, $session);
                        }
                    }
                }

                return $entries;
            },
            static fn (array $entries): SessionStarted => new SessionStarted($entries[0], \array_slice($entries, 1)),
        );
    }

    /**
     * Erases a member from every ledger: a pseudonym of its own — `erased:` and 32 random hexadecimal digits, drawn for
     * this erasure — takes their place as holder and as actor in every entry, and the balances, the positions and the
     * order stay, so that the other holders' audit still adds up. The tokens the member held stay with the pseudonym.
     * A member no entry names — one erased already — is left alone: nothing is written nor dispatched.
     *
     * The reasons are left as they were written: the application keeps member names out of them.
     */
    public function eraseHolder(ResourceReference $member): ?HolderErased
    {
        $pseudonym = ResourceReference::of(self::ERASED_KIND, new Randomizer()->getBytesFromString('0123456789abcdef', 32));
        $entries = $this->ledger->eraseHolder($member, $pseudonym);

        if (0 === $entries) {
            return null;
        }

        $erased = new HolderErased($pseudonym, $entries);
        $this->events?->dispatch($erased);

        return $erased;
    }

    /**
     * How many tokens of a kind a holder has.
     *
     * @throws TokenRefused when the table hands out no tokens, or not this kind
     */
    public function balance(ResourceReference $table, string $kind, ResourceReference $holder): int
    {
        $this->rules($table)->kind($kind);

        return $this->ledger->balance($table, $kind, $holder);
    }

    /**
     * The balance of every holder and kind the ledger names, zero included, in the order they first appear.
     *
     * @return list<Balance>
     */
    public function balances(ResourceReference $table): array
    {
        $balances = [];

        foreach ($this->ledger->entries($table) as $entry) {
            if (null !== $entry->kind) {
                $balances[$this->key($entry->kind, $entry->holder)] = new Balance($entry->kind, $entry->holder, $entry->balance);
            }
        }

        return array_values($balances);
    }

    /**
     * The ledger of the table, by position, or the entries of one holder — the audit the game master and the players
     * read.
     *
     * @return list<LedgerEntry>
     */
    public function log(ResourceReference $table, ?ResourceReference $holder = null): array
    {
        $entries = $this->ledger->entries($table);

        return $holder instanceof ResourceReference ? array_values(array_filter($entries, static fn (LedgerEntry $entry): bool => null !== $entry->kind && $entry->holder->equals($holder))) : $entries;
    }

    /**
     * @param Closure(list<LedgerEntry>): bool                                                                                                                                 $matches
     * @param Closure(TableRules, Closure(string, ResourceReference): int, Closure(EntryType, ?string, ResourceReference, int, int, ?string=): LedgerEntry): list<LedgerEntry> $build
     * @param Closure(list<LedgerEntry>): object                                                                                                                               $event
     */
    private function write(ResourceReference $table, string $operation, ResourceReference $actor, ?string $reason, Closure $matches, Closure $build, Closure $event): Receipt
    {
        LedgerEntry::identifier($operation, 'An operation');

        if (null !== $reason) {
            LedgerEntry::reason($reason);
        }

        $rules = $this->rules($table);

        for ($attempt = 1;; ++$attempt) {
            $done = $this->ledger->operation($table, $operation);

            if ([] !== $done) {
                return $matches($done) ? new Receipt($operation, $done, true) : throw TokenRefused::operationReused($operation);
            }

            $last = $this->ledger->last($table);
            $at = $this->clock->now();
            $position = $last;
            // The ledger's limit is checked at each entry built, before the next: a session of many holders stops at the
            // first entry past it rather than building them all.
            $maxEntries = $this->maxEntries;
            $entry = static function (EntryType $type, ?string $kind, ResourceReference $holder, int $change, int $balance, ?string $session = null) use ($table, $operation, $actor, $reason, $at, $last, $maxEntries, &$position): LedgerEntry {
                if ($position >= $maxEntries) {
                    throw TokenRefused::ledgerFull($table, $last);
                }

                return new LedgerEntry($table, ++$position, $operation, $type, $kind, $holder, $change, $balance, $actor, $at, $reason, $session);
            };
            $held = fn (string $kind, ResourceReference $holder): int => $this->ledger->balance($table, $kind, $holder);
            $entries = $build($rules, $held, $entry);

            try {
                $this->ledger->append($table, $last, $entries);
            } catch (LedgerConflict $conflict) {
                if ($attempt >= self::ATTEMPTS) {
                    throw $conflict;
                }

                continue;
            }

            $this->events?->dispatch($event($entries));

            return new Receipt($operation, $entries);
        }
    }

    private function mayMove(TableRules $rules, ResourceReference $actor, ResourceReference $holder): void
    {
        if (!$rules->isGameMaster($actor) && !$this->access->controls($actor, $holder)) {
            throw TokenRefused::notHolder($actor, $holder);
        }
    }

    private function amount(TokenKind $token, int $amount): TokenKind
    {
        if ($amount < 1 || $amount > $token->cap) {
            throw InvalidTokenRule::because(\sprintf('An amount of "%s" is 1 to %d, its cap; not %d.', $token->key, $token->cap, $amount));
        }

        return $token;
    }

    /**
     * The holders of the ledger, then those given that it does not name yet.
     *
     * @param list<ResourceReference> $known
     * @param list<ResourceReference> $given
     *
     * @return list<ResourceReference>
     */
    private function holders(array $known, array $given): array
    {
        $holders = [];

        foreach ([...$known, ...$given] as $holder) {
            $holders[$holder->toString()] ??= $holder;
        }

        return array_values($holders);
    }

    private function key(string $kind, ResourceReference $holder): string
    {
        return $kind."\n".$holder->toString();
    }
}
