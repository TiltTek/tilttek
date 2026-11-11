<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;

/**
 * What every store of macros does: keep a macro, find it again by its level, its owner and its name, list an owner's
 * macros by name, remove one and erase an owner's.
 */
abstract class MacroRepositoryTestCase extends TestCase
{
    public function testAMacroIsKeptFoundAgainAndRemoved(): void
    {
        $repository = $this->repository();
        $site = ResourceReference::of('site', '1');
        $repository->save(DiceMacro::define(MacroLevel::Site, $site, 'attack', '1d20+5'));
        $this->clear();

        $found = $repository->get(MacroLevel::Site, $site, 'attack');
        self::assertNotNull($found);
        self::assertSame([MacroLevel::Site, 'site:1', 'attack', '1d20+5'], [$found->level(), $found->owner()->toString(), $found->name(), $found->expression()]);
        self::assertNull($repository->get(MacroLevel::Table, $site, 'attack'));
        self::assertNull($repository->get(MacroLevel::Site, ResourceReference::of('site', '2'), 'attack'));
        self::assertNull($repository->get(MacroLevel::Site, $site, 'attacks'));

        $found->redefine('1d20+6');
        $repository->save($found);
        $this->clear();
        self::assertSame('1d20+6', $repository->get(MacroLevel::Site, $site, 'attack')?->expression());

        $found = $repository->get(MacroLevel::Site, $site, 'attack');
        self::assertNotNull($found);
        $repository->remove($found);
        $this->clear();
        self::assertNull($repository->get(MacroLevel::Site, $site, 'attack'));
    }

    public function testAnOwnersMacrosAreListedByNameAndErased(): void
    {
        $repository = $this->repository();
        $member = ResourceReference::of('member', '1');
        $repository->save(DiceMacro::define(MacroLevel::Member, $member, 'stealth', '1d20+7'));
        $repository->save(DiceMacro::define(MacroLevel::Member, $member, 'attack', '1d20+5'));
        $repository->save(DiceMacro::define(MacroLevel::Table, $member, 'odd', '1d6'));
        $repository->save(DiceMacro::define(MacroLevel::Member, ResourceReference::of('member', '2'), 'attack', '1d20'));
        $this->clear();

        self::assertSame(['attack', 'stealth'], array_map(static fn (DiceMacro $macro): string => $macro->name(), $repository->ofOwner(MacroLevel::Member, $member)));
        self::assertSame(3, $repository->eraseOwner($member));
        $this->clear();
        self::assertSame([], $repository->ofOwner(MacroLevel::Member, $member));
        self::assertSame([], $repository->ofOwner(MacroLevel::Table, $member));
        self::assertSame(0, $repository->eraseOwner($member));
        self::assertNotNull($repository->get(MacroLevel::Member, ResourceReference::of('member', '2'), 'attack'));
    }

    abstract protected function repository(): DiceMacroRepository;

    protected function clear(): void
    {
    }
}
