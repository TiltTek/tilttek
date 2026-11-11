<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Replay\Replayer;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Tests\Unit\Fixture\ScriptedEngine;

/**
 * What every journal does: keep a roll, find it again, list an owner's rolls the latest first, and erase them.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testARollIsKeptAndFoundAgain(): void
    {
        $repository = $this->repository();
        $roll = $this->roll(1, 'member:1', '2026-11-07 03:00:00');
        $repository->save($roll);
        $this->clear();

        $found = $repository->get($roll->id());
        self::assertNotNull($found);
        self::assertSame([$roll->id(), $roll->expression(), $roll->seed()->hex(), $roll->result(), 'member:1', $roll->rolledAt()->getTimestamp()], [$found->id(), $found->expression(), $found->seed()->hex(), $found->result(), $found->owner()?->toString(), $found->rolledAt()->getTimestamp()]);
        self::assertNull($repository->get('00000000000000000000000000000000'));
    }

    public function testAnOwnersRollsAreListedTheLatestFirstAndErased(): void
    {
        $repository = $this->repository();
        $member = ResourceReference::of('member', '1');
        $repository->save($this->roll(1, 'member:1', '2026-11-07 03:00:00'));
        $repository->save($this->roll(30, 'member:1', '2026-11-07 04:00:00'));
        $repository->save($this->roll(13, 'member:1', '2026-11-07 04:00:00'));
        $repository->save($this->roll(60, 'member:2', '2026-11-07 05:00:00'));
        $repository->save($this->roll(90, null, '2026-11-07 05:00:00'));
        $this->clear();

        self::assertSame(['0d0000000e0000000f00000010000000', '1e0000001f0000002000000021000000', '01000000020000000300000004000000'], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->ofOwner($member, 50)));
        self::assertSame(['0d0000000e0000000f00000010000000'], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $repository->ofOwner($member, 1)));
        self::assertSame(3, $repository->eraseOwner($member));
        $this->clear();
        self::assertSame([], $repository->ofOwner($member, 50));
        self::assertSame(0, $repository->eraseOwner($member));
        self::assertCount(1, $repository->ofOwner(ResourceReference::of('member', '2'), 50));
        self::assertNotNull($repository->get('5a0000005b0000005c0000005d000000'));
    }

    abstract protected function repository(): RecordedRollRepository;

    protected function clear(): void
    {
    }

    private function roll(int $first, ?string $owner, string $at): RecordedRoll
    {
        $roll = new Replayer()->roll(new DiceParser()->parse('3d6+1'), ScriptedEngine::randomizer(...range($first, $first + 11)));

        return RecordedRoll::record($roll, null === $owner ? null : ResourceReference::fromString($owner), new DateTimeImmutable($at.' UTC'));
    }
}
