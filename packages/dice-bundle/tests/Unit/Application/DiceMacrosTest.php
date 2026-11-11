<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Application;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\DiceBundle\Application\DiceMacros;
use Tilttek\DiceBundle\Application\Exception\InvalidMacro;
use Tilttek\DiceBundle\Application\MacroResolution;
use Tilttek\DiceBundle\Application\MacroSession;
use Tilttek\DiceBundle\Application\MacroShelves;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryDiceMacroRepository;

#[CoversClass(DiceMacros::class)]
#[CoversClass(MacroResolution::class)]
#[CoversClass(MacroSession::class)]
#[CoversClass(MacroShelves::class)]
#[CoversClass(InvalidMacro::class)]
#[CoversClass(DiceMacro::class)]
final class DiceMacrosTest extends TestCase
{
    private InMemoryDiceMacroRepository $repository;

    private DiceMacros $macros;

    private MacroShelves $shelves;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryDiceMacroRepository();
        $this->macros = new DiceMacros(new DiceRoller(), $this->repository);
        $this->shelves = new MacroShelves(ResourceReference::of('site', '1'), ResourceReference::of('table', '7'), ResourceReference::of('member', '3'));
    }

    public function testAMacroIsAnOperandReadAsOneToken(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Site, 'a', '1d4');
        $this->macros->define($this->shelves, MacroLevel::Site, 'aa', '1d20');

        self::assertSame('1d20+1d4*2', $this->canonical('$aa+$a*2'));
        self::assertSame('(1d20+1d4)*2', $this->canonical('($aa+$a)*2'));
    }

    public function testANameIsLookedForOnTheMembersShelfThenTheTablesThenTheSites(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Site, 'attack', '1d20');
        self::assertSame('1d20', $this->canonical('$attack'));

        $this->macros->define($this->shelves, MacroLevel::Table, 'attack', '1d20+2');
        self::assertSame('1d20+2', $this->canonical('$attack'));

        $this->macros->define($this->shelves, MacroLevel::Member, 'Attack', '1d20+5');
        self::assertSame('1d20+5', $this->canonical('$ATTACK'));
        self::assertSame('1d20+2', $this->canonical('$attack', new MacroShelves($this->shelves->site, $this->shelves->table)));
        self::assertSame('1d20', $this->canonical('$attack', new MacroShelves($this->shelves->site)));
        self::assertSame('1d20+5', $this->canonical('$attack', new MacroShelves(member: $this->shelves->member)));
    }

    public function testTheNamesOfAMacroAreLookedForFromItsOwnLevelOn(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Site, 'bonus', '2');
        $this->macros->define($this->shelves, MacroLevel::Site, 'attack', '1d20+$bonus');
        $this->macros->define($this->shelves, MacroLevel::Member, 'bonus', '9');
        $this->macros->define($this->shelves, MacroLevel::Table, 'damage', '1d8+$bonus');

        self::assertSame('1d20+2, 1d8+2, 9', $this->canonical('$attack; $damage; $bonus'));
    }

    public function testAMacroOfSeveralInstructionsIsRolledAlone(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Table, 'init', '1d20; $1+2');

        self::assertSame('1d20, $1+2', $this->canonical('$init'));
    }

    public function testACommandOfDiceParserNamesMacros(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Member, 'fire', '8d6');

        self::assertSame('8d6+1d6sd', new DiceRoller()->write($this->macros->readRolisteam('$fire+1d6s', $this->shelves)));
    }

    public function testAMacroIsReadInTheReadersLanguage(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Site, 'attack', '1d20');

        self::assertSame('1d20+2d6', new DiceRoller()->write($this->macros->parse('$attack+2W6', $this->shelves, 'de')));
    }

    public function testADefinitionReplacesTheMacroOfTheSameName(): void
    {
        $first = $this->macros->define($this->shelves, MacroLevel::Site, 'attack', '1d20');
        $second = $this->macros->define($this->shelves, MacroLevel::Site, 'ATTACK', '1d20+1');

        self::assertSame($first, $second);
        self::assertSame(['attack', '1d20+1', MacroLevel::Site, 'site:1'], [$second->name(), $second->expression(), $second->level(), $second->owner()->toString()]);
        self::assertSame([$second], $this->macros->of(MacroLevel::Site, ResourceReference::of('site', '1')));
    }

    public function testAMacroIsDefinedOnlyWhenItParses(): void
    {
        $this->expectExceptionObject(InvalidExpression::at('$attack', 0, 'The macro "attack" does not parse: Unexpected "x"'));

        try {
            $this->macros->define($this->shelves, MacroLevel::Site, 'attack', '1d20x');
        } finally {
            self::assertSame([], $this->macros->of(MacroLevel::Site, ResourceReference::of('site', '1')));
        }
    }

    public function testAMacroThatNamesItselfIsRefused(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Site, 'first', '1d6');
        $this->macros->define($this->shelves, MacroLevel::Site, 'second', '$first+1');

        try {
            $this->macros->define($this->shelves, MacroLevel::Site, 'first', '$second+1');
            self::fail('The macro is defined.');
        } catch (InvalidExpression $invalidExpression) {
            self::assertSame('The macro "first" does not parse: The macro "second" does not parse: The macro "first" does not parse: The macro "first" names itself', $invalidExpression->reason);
        }

        self::assertSame('1d6', $this->repository->get(MacroLevel::Site, ResourceReference::of('site', '1'), 'first')?->expression());
    }

    public function testAMacroAtALevelNamesNoneOfTheLevelsBeforeIt(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Member, 'bonus', '9');

        $this->expectExceptionObject(InvalidExpression::at('$attack', 0, 'The macro "attack" does not parse: No macro is named "bonus"'));

        $this->macros->define($this->shelves, MacroLevel::Table, 'attack', '1d20+$bonus');
    }

    public function testAnInputExpandsAFewMacrosAtMost(): void
    {
        $this->macros->define($this->shelves, MacroLevel::Site, 'one', '1');
        $this->macros->define($this->shelves, MacroLevel::Site, 'four', '$one+$one+$one+$one');

        self::assertSame('1+1+1+1', $this->canonical('$four'));
        self::assertCount(25, explode('+', $this->canonical('$four+$four+$four+$four+$four+$four+1')));

        $this->expectExceptionObject(LimitExceeded::of('macros an input expands', 32, 35));
        $this->macros->parse('$four+$four+$four+$four+$four+$four+$four', $this->shelves);
    }

    public function testAMacroIsDefinedForTheOwnerOfItsLevel(): void
    {
        $this->expectExceptionObject(InvalidMacro::withoutOwner(MacroLevel::Member));

        $this->macros->define(new MacroShelves(ResourceReference::of('site', '1')), MacroLevel::Member, 'attack', '1d20');
    }

    public function testAMacroHasAName(): void
    {
        $this->expectExceptionObject(InvalidMacro::named('2attack'));

        $this->macros->define($this->shelves, MacroLevel::Site, '2Attack', '1d20');
    }

    public function testAMacroIsRemovedAndAnOwnersMacrosErased(): void
    {
        $member = ResourceReference::of('member', '3');
        $this->macros->define($this->shelves, MacroLevel::Member, 'attack', '1d20');
        $this->macros->define($this->shelves, MacroLevel::Member, 'stealth', '1d20+3');

        self::assertTrue($this->macros->remove(MacroLevel::Member, $member, 'ATTACK'));
        self::assertFalse($this->macros->remove(MacroLevel::Member, $member, 'attack'));
        self::assertSame(['stealth'], array_map(static fn (DiceMacro $macro): string => $macro->name(), $this->macros->of(MacroLevel::Member, $member)));
        self::assertSame(1, $this->macros->eraseOwner($member));
        self::assertSame([], $this->macros->of(MacroLevel::Member, $member));
    }

    public function testShelvesFromALevel(): void
    {
        $shelves = $this->shelves;

        self::assertSame([[MacroLevel::Member, 'member:3'], [MacroLevel::Table, 'table:7'], [MacroLevel::Site, 'site:1']], self::listed($shelves));
        self::assertSame([[MacroLevel::Table, 'table:7'], [MacroLevel::Site, 'site:1']], self::listed($shelves->from(MacroLevel::Table)));
        self::assertSame([[MacroLevel::Site, 'site:1']], self::listed($shelves->from(MacroLevel::Site)));
        self::assertSame($shelves, $shelves->from(MacroLevel::Member));
        self::assertSame([[MacroLevel::Member, 'member:3']], self::listed(new MacroShelves(member: $shelves->member)));
    }

    /**
     * @return list<array{MacroLevel, string}>
     */
    private static function listed(MacroShelves $shelves): array
    {
        return array_map(static fn (array $shelf): array => [$shelf[0], $shelf[1]->toString()], $shelves->shelves());
    }

    private function canonical(string $input, ?MacroShelves $shelves = null): string
    {
        return new DiceRoller()->write($this->macros->parse($input, $shelves ?? $this->shelves));
    }
}
