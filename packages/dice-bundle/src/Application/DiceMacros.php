<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Phil\Resource\ResourceReference;
use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\DiceBundle\Application\Exception\InvalidMacro;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;

/**
 * The macros of the sites, the tables and the members: `$attack + 2` takes the tree of the macro `attack` as an operand,
 * read as one token — never by replacing text, so that a macro `a` leaves `aa` alone. A name is looked for on the
 * member's shelf, then the table's, then the site's; a macro is defined only when it parses there, and an input expands
 * 32 macros at most, those of a macro within a macro counted each time.
 */
final readonly class DiceMacros
{
    public const int EXPANSIONS = 32;

    public function __construct(
        private DiceRoller $roller,
        private DiceMacroRepository $macros,
    ) {
    }

    /**
     * An input in a language's notation, its macros looked for on these shelves.
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     */
    public function parse(string $input, MacroShelves $shelves, ?string $locale = null): Pool
    {
        return $this->roller->parse($input, $locale, $this->resolution($shelves));
    }

    /**
     * A command of Rolisteam's DiceParser, its macros looked for on these shelves.
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     */
    public function readRolisteam(string $input, MacroShelves $shelves): Pool
    {
        return $this->roller->readRolisteam($input, $this->resolution($shelves));
    }

    /**
     * Keeps a macro at a level, for the owner the shelves give there, replacing the one of the same name; its
     * expression, in the library's notation, must parse with the macros of its level and those after it.
     *
     * @throws InvalidMacro      when the name is not one, or the shelves give no owner at this level
     * @throws InvalidExpression when the expression does not parse, or names the macro itself
     * @throws LimitExceeded     when it goes past a limit
     */
    public function define(MacroShelves $shelves, MacroLevel $level, string $name, string $expression): DiceMacro
    {
        $owner = $shelves->owner($level) ?? throw InvalidMacro::withoutOwner($level);
        $name = strtolower($name);

        if (1 !== preg_match(Macros::NAME_PATTERN, $name)) {
            throw InvalidMacro::named($name);
        }

        $candidate = DiceMacro::define($level, $owner, $name, $expression);
        $this->roller->parse('$'.$name, macros: new MacroResolution($this->roller, $this->macros, $shelves->from($level), new MacroSession(self::EXPANSIONS), $candidate));
        $macro = $this->macros->get($level, $owner, $name);

        if (!$macro instanceof DiceMacro) {
            $this->macros->save($candidate);

            return $candidate;
        }

        $macro->redefine($expression);
        $this->macros->save($macro);

        return $macro;
    }

    /**
     * Removes a macro; the macros that name it are refused from then on.
     *
     * @return bool whether there was one
     */
    public function remove(MacroLevel $level, ResourceReference $owner, string $name): bool
    {
        $macro = $this->macros->get($level, $owner, strtolower($name));

        if ($macro instanceof DiceMacro) {
            $this->macros->remove($macro);
        }

        return $macro instanceof DiceMacro;
    }

    /**
     * The macros kept at a level for an owner, by name.
     *
     * @return list<DiceMacro>
     */
    public function of(MacroLevel $level, ResourceReference $owner): array
    {
        return $this->macros->ofOwner($level, $owner);
    }

    /**
     * Erases an owner's macros at every level, when the member, the table or the site is erased.
     */
    public function eraseOwner(ResourceReference $owner): int
    {
        return $this->macros->eraseOwner($owner);
    }

    private function resolution(MacroShelves $shelves): MacroResolution
    {
        return new MacroResolution($this->roller, $this->macros, $shelves, new MacroSession(self::EXPANSIONS));
    }
}
