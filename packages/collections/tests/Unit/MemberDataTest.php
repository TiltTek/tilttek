<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\CopyMove;
use Tilttek\Collections\Erasure;
use Tilttek\Collections\MemberData;
use Tilttek\Collections\Source;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;

#[CoversClass(MemberData::class)]
#[CoversClass(Erasure::class)]
final class MemberDataTest extends TestCase
{
    public function testAMemberIsExportedThenErased(): void
    {
        $kit = Kit::standard();
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');
        $frost = $kit->collections->grantSet($one, 'frost', Source::Exchange, 'exchange:1')[0];
        $kit->dice->choose($one, $frost->id);
        $kit->clock->set('2026-11-07 13:00:00 UTC');

        $opening = $kit->packs->open($kit->packs->commit($one, 'crew-pack', 'shop:1'), 'mine');
        $kit->clock->set('2026-11-07 14:00:00 UTC');
        $kept = $kit->collections->grant($one, 'medal', Source::Granted, 'k1');
        $kit->collections->move([new CopyMove($kit->collections->grant($one, 'pirate', Source::Granted, 'k2')->id, $one, $two)]);
        $kit->collections->grant($two, 'deckhand', Source::Granted, 'k3');

        $export = $kit->data->export($one);
        self::assertSame(['exchange:1#frost-d6', 'shop:1#1', 'shop:1#2', 'k1'], array_column($export['copies'], 'key'));
        self::assertSame([['set' => 'frost', 'member' => 'member:1', 'completed_at' => '2026-11-07T12:00:00+00:00']], $export['completions']);
        self::assertSame([$opening->toArray()], $export['openings']);
        self::assertSame([['member' => 'member:1', 'sides' => 6, 'copy' => $frost->id]], $export['choices']);
        self::assertSame($kept->toArray(), $export['copies'][3]);

        self::assertEquals(new Erasure(4, 1, 1, 1), $kit->data->erase($one));
        self::assertSame(['copies' => [], 'completions' => [], 'openings' => [], 'choices' => []], $kit->data->export($one));
        self::assertCount(2, $kit->collections->copiesOf($two));
    }
}
