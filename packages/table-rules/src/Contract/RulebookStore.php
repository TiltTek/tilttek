<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Contract;

use Tilttek\TableRules\Exception\RulesConflict;
use Tilttek\TableRules\Rulebook;
use Tilttek\TableRules\RulebookVersion;

/**
 * Keeps each table's rulebook at a revision, and every version published.
 */
interface RulebookStore
{
    /**
     * The table's rulebook, or null when none was ever kept.
     */
    public function rulebook(string $table): ?Rulebook;

    /**
     * A version of the table's rules, or null when the store has none of the number.
     */
    public function version(string $table, int $number): ?RulebookVersion;

    /**
     * Keeps the rulebook at the next revision, with the version just published when one is given, both or neither,
     * and returns the rulebook kept. A rulebook at revision 0 is new; any other is kept only when the store holds it at
     * that revision.
     *
     * @throws RulesConflict when the rulebook is new and the store has one for the table, or the store holds it at
     *                       another revision; nothing is kept
     */
    public function save(Rulebook $rulebook, ?RulebookVersion $published = null): Rulebook;
}
