<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Domain\Repository;

use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;

/**
 * The macros of the sites, the tables and the members.
 */
interface DiceMacroRepository
{
    public function get(MacroLevel $level, ResourceReference $owner, string $name): ?DiceMacro;

    public function save(DiceMacro $macro): void;

    public function remove(DiceMacro $macro): void;

    /**
     * The macros kept at a level for an owner, by name.
     *
     * @return list<DiceMacro>
     */
    public function ofOwner(MacroLevel $level, ResourceReference $owner): array;

    /**
     * Erases every macro of an owner, at every level.
     *
     * @return int how many macros were erased
     */
    public function eraseOwner(ResourceReference $owner): int;
}
