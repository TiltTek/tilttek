<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use ReflectionProperty;
use Tilttek\Dice\DiceRoller;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Engine\ReferenceCollector;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;
use Tilttek\Generator\Tests\Unit\Fixture\SteppingTimer;

#[CoversClass(Producer::class)]
#[CoversClass(ReferenceCollector::class)]
final class ProducerTest extends TestCase
{
    public function testTheFunctionsWorkDuringADrawOnly(): void
    {
        $producer = new Producer(Catalogs::of(['l' => ['a']]), new Limits(), 'en', new Grammars(), new DiceRoller(), new SteppingTimer(0.0));

        $this->expectExceptionObject(new LogicException('The generator functions are called during a draw only.'));

        $producer->pick('l');
    }

    public function testTheFunctionsFailAgainOnceADrawIsOver(): void
    {
        $producer = new Producer(Catalogs::of(['l' => ['a']], ['g' => ['templates' => "{{ pick('l') }}"]]), new Limits(), 'en', new Grammars(), new DiceRoller(), new SteppingTimer(0.0));
        self::assertSame('a', $producer->draw('g', new Draw(), new Randomizer())->first());

        $this->expectExceptionObject(new LogicException('The generator functions are called during a draw only.'));

        $producer->pick('l');
    }

    public function testAListKeepsTheEntriesOfABoundedNumberOfTagFilters(): void
    {
        self::assertSame(64, Producer::POOLS_PER_LIST);
        $picks = implode('', array_map(static fn (int $kind): string => "{{ pick('w', 2, where: {kind: 'k".$kind."'})|join }}", range(1, Producer::POOLS_PER_LIST + 1)));
        $entries = array_map(static fn (int $kind): array => ['text' => 'item', 'tags' => ['kind' => 'k'.$kind]], range(1, Producer::POOLS_PER_LIST + 1));
        $catalog = Catalogs::of(['w' => [...$entries, ['text' => 'sword', 'tags' => ['kind' => 'blade']]]], ['g' => ['templates' => $picks."{{ pick('w', where: {kind: 'blade'}) }}"]]);
        $producer = new Producer($catalog, new Limits(), 'en', new Grammars(), new DiceRoller(), new SteppingTimer(0.0));

        // The filter past the bound is computed at each pick, and still finds its entries.
        self::assertSame(str_repeat('item', Producer::POOLS_PER_LIST + 1).'sword', $producer->draw('g', new Draw(), new Randomizer())->first());
        $pools = new ReflectionProperty($producer, 'pools')->getValue($producer);
        self::assertIsArray($pools);
        self::assertIsArray($pools['w']);
        self::assertCount(Producer::POOLS_PER_LIST, $pools['w']);
    }

    public function testTheCollectorRunsAtTheDefaultPriority(): void
    {
        self::assertSame(0, new ReferenceCollector()->getPriority());
    }
}
