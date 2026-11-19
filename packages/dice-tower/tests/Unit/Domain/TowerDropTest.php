<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Domain;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceTower\Domain\Model\TowerDrop;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(TowerDrop::class)]
final class TowerDropTest extends TestCase
{
    public function testADropHasNoPositionUntilTheLogKeepsIt(): void
    {
        $drop = TowerDrop::drop(Tower::table(), 'aaaa', null, new DateTimeImmutable());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The drop has no position until the tower log keeps it.');

        $drop->position();
    }
}
