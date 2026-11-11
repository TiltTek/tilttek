<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Dice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Copy;
use Tilttek\Collections\CopyMove;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\Source;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;

#[CoversClass(DiceShelf::class)]
final class DiceShelfTest extends TestCase
{
    public function testAMemberChoosesADieTheyOwnForItsSides(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $chosen = static fn (int $sides, string $who = 'member:1'): ?string => $kit->dice->chosen(Kit::ref($who), $sides)?->code;
        $kit->collections->welcome($member, 'welcome:1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k1');

        $frost = $kit->collections->grantSet($member, 'frost', Source::Exchange, 'exchange:1')[0];

        self::assertSame(['ember-d6', 'ember-d20', 'frost-d6'], array_map(static fn (Copy $copy): string => $copy->item, $kit->dice->dice($member)));
        self::assertNull($chosen(6));

        $choice = $kit->dice->choose($member, $frost->id);
        self::assertSame([6, $frost->id, 'member:1'], [$choice->sides, $choice->copy, $choice->member->toString()]);
        self::assertSame('frost-d6', $chosen(6));
        self::assertNull($chosen(20));
        self::assertNull($chosen(6, 'member:2'));

        self::assertTrue($kit->dice->clear($member, 6));
        self::assertFalse($kit->dice->clear($member, 6));
        self::assertNull($chosen(6));
    }

    public function testADieTradedAwayIsNoLongerRolled(): void
    {
        $kit = Kit::standard();
        $member = Kit::ref('member:1');
        $frost = $kit->collections->grantSet($member, 'frost', Source::Exchange, 'exchange:1')[0];
        $kit->dice->choose($member, $frost->id);
        $kit->collections->move([new CopyMove($frost->id, $member, Kit::ref('member:2'))]);

        self::assertNull($kit->dice->chosen($member, 6));
        $kit->collections->copies->eraseOwner(Kit::ref('member:2'));
        self::assertNull($kit->dice->chosen($member, 6));
    }

    public function testOnlyTheMembersOwnDiceAreChosen(): void
    {
        $kit = Kit::standard();
        $pirate = $kit->collections->grant(Kit::ref('member:1'), 'pirate', Source::Granted, 'k1');
        $die = $kit->collections->grant(Kit::ref('member:2'), 'ember-d6', Source::Granted, 'k2');

        foreach ([[$die->id, \sprintf('The copy "%s" does not belong to "member:1".', $die->id)], [$pirate->id, '"pirate" is not a die.']] as [$copy, $message]) {
            try {
                $kit->dice->choose(Kit::ref('member:1'), $copy);
                self::fail($message);
            } catch (CollectionRefused $collectionRefused) {
                self::assertSame($message, $collectionRefused->getMessage());
            }
        }

        self::assertSame([], $kit->choices->ofMember(Kit::ref('member:1')));

        $this->expectExceptionObject(NotFound::of('copy', 'nothing'));
        $kit->dice->choose(Kit::ref('member:1'), 'nothing');
    }
}
