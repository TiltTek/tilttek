<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

use Psr\Clock\ClockInterface;
use Tilttek\TableRules\Contract\RulebookStore;
use Tilttek\TableRules\Contract\TableRulesObserver;
use Tilttek\TableRules\Contract\VariantCatalogue;
use Tilttek\TableRules\Diff\RulebookDiff;
use Tilttek\TableRules\Diff\RulebookDiffer;
use Tilttek\TableRules\Event\PlayerForgotten;
use Tilttek\TableRules\Event\RulebookPublished;
use Tilttek\TableRules\Event\RulesAccepted;
use Tilttek\TableRules\Event\RulesDeclined;
use Tilttek\TableRules\Event\RulesEvent;
use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Exception\RulesConflict;
use Tilttek\TableRules\Exception\RulesNotFound;
use Tilttek\TableRules\Exception\RulesRefused;
use Tilttek\TableRules\Internal\Identifier;
use Tilttek\TableRules\View\PlayerStatus;
use Tilttek\TableRules\View\RosterView;

/**
 * The service of a table's rules: it publishes a new version of the house rules and of the variants chosen, records
 * each player's acceptance or refusal of the version that holds, and says where each player stands and what changed
 * since the version the player answered. A player accepts explicitly: being at the table accepts nothing. It reads
 * from the store, changes, keeps, then tells the observer what happened, each fact once. It keeps no state between
 * calls: everything is in the store, and the time comes from the PSR-20 clock.
 *
 * The service trusts its caller to say who may publish the rules, and that a player answers for no one else.
 */
final readonly class TableRules
{
    public function __construct(
        private RulebookStore $store,
        private ClockInterface $clock,
        private ?TableRulesObserver $observer = null,
        private ?VariantCatalogue $variants = null,
        private RulebookDiffer $differ = new RulebookDiffer(),
    ) {
    }

    /**
     * The table's rulebook as kept, without version at revision 0 when none was ever kept.
     *
     * @throws InvalidRules when the identifier is not one
     */
    public function rulebook(string $table): Rulebook
    {
        return $this->store->rulebook(Identifier::check($table, 'a table')) ?? new Rulebook($table);
    }

    /**
     * A version of the table's rules.
     *
     * @throws InvalidRules  when the identifier is not one
     * @throws RulesNotFound when the table has no version of the number
     */
    public function version(string $table, int $number): RulebookVersion
    {
        $current = $this->rulebook($table)->current;
        if ($current instanceof RulebookVersion && $current->number === $number) {
            return $current;
        }

        return ($number < 1 ? null : $this->store->version($table, $number)) ?? throw RulesNotFound::version($table, $number);
    }

    /**
     * Publishes the next version of the table's rules, which holds from now on. The decisions of the players are kept:
     * when the version requires acceptance, each player answers it anew; when it does not — a typo fixed, a sentence
     * made clearer — an acceptance of the versions since the last one that required it still holds.
     *
     * @param string       $author             the identifier of who publishes it
     * @param list<Rule>   $rules              in the order the table reads them
     * @param list<string> $variants           the identifiers of the rule variants of the game system chosen
     * @param ?string      $system             the identifier of the game system the variants belong to
     * @param ?string      $note               what changed, for the players
     * @param bool         $requiresAcceptance whether every player answers this version anew; the first always requires it
     *
     * @throws InvalidRules  when a value is out of its shape or its bounds, or the catalogue has no such variant
     * @throws RulesRefused  when the rules, the variants and the system are those that hold, or the table has the most versions
     * @throws RulesConflict when the rulebook changed since it was read
     */
    public function publish(
        string $table,
        string $author,
        array $rules,
        array $variants = [],
        ?string $system = null,
        ?string $note = null,
        bool $requiresAcceptance = true,
    ): Rulebook {
        $before = $this->rulebook($table);
        $number = ($before->current->number ?? 0) + 1;
        if ($number > RulebookVersion::MAX_NUMBER) {
            throw RulesRefused::because(\sprintf('The rules of the table %s have the most versions.', $table));
        }

        $requiresAcceptance = $requiresAcceptance || 1 === $number;
        $version = new RulebookVersion($number, $rules, $this->clock->now(), $author, $variants, $system, $note, $requiresAcceptance);
        foreach ($version->variants as $variant) {
            if ($this->variants instanceof VariantCatalogue && !$this->variants->has($system, $variant)) {
                throw InvalidRules::because(\sprintf('The game system offers no variant %s.', $variant));
            }
        }

        if ($before->current instanceof RulebookVersion && $before->current->sameRulesAs($version)) {
            throw RulesRefused::because(\sprintf('The rules of the table %s are already those.', $table));
        }

        $after = $before->with($version, $requiresAcceptance ? $number : $before->baseline, $before->acceptances);

        return $this->keep($after, [new RulebookPublished($table, $number, $requiresAcceptance)], $version);
    }

    /**
     * Records that the player accepts the version that holds. The player read that version: when another holds by now,
     * the acceptance is refused, for the player to read the new one. Accepting again what the player accepted changes
     * nothing.
     *
     * @param int $version the number of the version the player read
     *
     * @throws InvalidRules  when an identifier is not one
     * @throws RulesNotFound when the table has no rules published
     * @throws RulesRefused  when the version is not the one that holds, or the rulebook keeps the most players
     * @throws RulesConflict when the rulebook changed since it was read
     */
    public function accept(string $table, string $player, int $version): Rulebook
    {
        return $this->decide($table, $player, $version, Decision::Accepted);
    }

    /**
     * Records that the player declines the version that holds, as `accept()` records an acceptance.
     *
     * @param int $version the number of the version the player read
     *
     * @throws InvalidRules  when an identifier is not one
     * @throws RulesNotFound when the table has no rules published
     * @throws RulesRefused  when the version is not the one that holds, or the rulebook keeps the most players
     * @throws RulesConflict when the rulebook changed since it was read
     */
    public function decline(string $table, string $player, int $version): Rulebook
    {
        return $this->decide($table, $player, $version, Decision::Declined);
    }

    /**
     * Forgets the last decision of the player: the player left the table, or asked to be forgotten. Nothing changes
     * when the rulebook keeps none.
     *
     * @throws InvalidRules  when an identifier is not one
     * @throws RulesConflict when the rulebook changed since it was read
     */
    public function forget(string $table, string $player): Rulebook
    {
        $before = $this->rulebook($table);
        if (!$before->acceptance(Identifier::check($player, 'a player')) instanceof Acceptance) {
            return $before;
        }

        $acceptances = $before->acceptances;
        unset($acceptances[$player]);

        return $this->keep($before->with($before->current, $before->baseline, $acceptances), [new PlayerForgotten($table, $player)]);
    }

    /**
     * Where the player stands with the table's rules, and what changed since the version the player last answered.
     *
     * @throws InvalidRules when an identifier is not one
     */
    public function status(string $table, string $player): PlayerStatus
    {
        $rulebook = $this->rulebook($table);
        $cache = [];

        return $this->statusOf($rulebook, Identifier::check($player, 'a player'), $cache);
    }

    /**
     * Where each player asked stands with the table's rules. The application gives the players of the table: the
     * rulebook knows only those who answered.
     *
     * @param list<string> $players the identifiers of the players, each once
     *
     * @throws InvalidRules when an identifier is not one, a player is given twice, or there are too many
     */
    public function roster(string $table, array $players): RosterView
    {
        if (\count($players) > Rulebook::MAX_PLAYERS) {
            throw InvalidRules::because(\sprintf('A roster holds %d players at most.', Rulebook::MAX_PLAYERS));
        }

        $rulebook = $this->rulebook($table);
        $cache = [];
        $statuses = [];
        foreach ($players as $player) {
            if (isset($statuses[Identifier::check($player, 'a player')])) {
                throw InvalidRules::because(\sprintf('The player %s is given twice.', $player));
            }

            $statuses[$player] = $this->statusOf($rulebook, $player, $cache);
        }

        return new RosterView($rulebook->table, $rulebook->current?->number, array_values($statuses));
    }

    /**
     * What changed from one version of the table's rules to another, in either order.
     *
     * @throws InvalidRules  when the identifier is not one
     * @throws RulesNotFound when the table has no version of one of the numbers
     */
    public function diff(string $table, int $from, int $to): RulebookDiff
    {
        return $this->differ->diff($this->version($table, $from), $this->version($table, $to));
    }

    /**
     * @param array<int, RulebookDiff> $cache the changes since each version answered, within one call
     */
    private function statusOf(Rulebook $rulebook, string $player, array &$cache): PlayerStatus
    {
        $acceptance = $rulebook->acceptance($player);
        $current = $rulebook->current;
        $changes = null;
        if ($acceptance instanceof Acceptance && $current instanceof RulebookVersion && $acceptance->version !== $current->number) {
            $changes = $cache[$acceptance->version] ??= $this->differ->diff($this->version($rulebook->table, $acceptance->version), $current);
        }

        return new PlayerStatus(
            $player,
            $rulebook->state($player),
            $current?->number,
            $acceptance?->version,
            $acceptance?->decision,
            $acceptance?->decidedAt,
            $changes,
        );
    }

    private function decide(string $table, string $player, int $version, Decision $decision): Rulebook
    {
        $before = $this->rulebook($table);
        Identifier::check($player, 'a player');
        $current = $before->current ?? throw RulesNotFound::rulebook($table);
        if ($version !== $current->number) {
            throw RulesRefused::because(\sprintf('The version %d is not the rules of the table %s that hold.', $version, $table));
        }

        $previous = $before->acceptance($player);
        if ($previous instanceof Acceptance && $previous->version === $version && $previous->decision === $decision) {
            return $before;
        }

        if (!$previous instanceof Acceptance && \count($before->acceptances) >= Rulebook::MAX_PLAYERS) {
            throw RulesRefused::because(\sprintf('The rules of the table %s keep the decisions of the most players.', $table));
        }

        $acceptances = $before->acceptances;
        $acceptances[$player] = new Acceptance($player, $version, $decision, $this->clock->now());
        $event = Decision::Accepted === $decision ? new RulesAccepted($table, $player, $version) : new RulesDeclined($table, $player, $version);

        return $this->keep($before->with($current, $before->baseline, $acceptances), [$event]);
    }

    /**
     * @param list<RulesEvent> $events
     */
    private function keep(Rulebook $rulebook, array $events, ?RulebookVersion $published = null): Rulebook
    {
        $kept = $this->store->save($rulebook, $published);
        foreach ($events as $event) {
            $this->observer?->notify($event);
        }

        return $kept;
    }
}
