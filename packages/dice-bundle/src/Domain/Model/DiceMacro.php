<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\MacroLevel;

/**
 * A macro: a name that stands for an expression in the library's notation, kept at a level — a site, a table or a
 * member — for its owner, an opaque reference the application gives. The level, the owner and the name identify it.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'tilttek_dice_macro_owner', columns: ['owner'])]
final class DiceMacro
{
    public const string TABLE = 'tilttek_dice_macro';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'level', length: 16)]
        private readonly string $level,
        #[ORM\Id]
        #[ORM\Column(name: 'owner', length: 320)]
        private readonly string $owner,
        #[ORM\Id]
        #[ORM\Column(name: 'name', length: 32)]
        private readonly string $name,
        #[ORM\Column(name: 'expression', type: 'text')]
        private string $expression,
    ) {
    }

    public static function define(MacroLevel $level, ResourceReference $owner, string $name, string $expression): self
    {
        return new self($level->value, $owner->toString(), $name, $expression);
    }

    public function redefine(string $expression): void
    {
        $this->expression = $expression;
    }

    /**
     * Whether this is the macro of this name kept at this level for this owner.
     */
    public function is(MacroLevel $level, ResourceReference $owner, string $name): bool
    {
        return $level->value === $this->level && $owner->toString() === $this->owner && $name === $this->name;
    }

    public function level(): MacroLevel
    {
        return MacroLevel::from($this->level);
    }

    public function owner(): ResourceReference
    {
        return ResourceReference::fromString($this->owner);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function expression(): string
    {
        return $this->expression;
    }
}
