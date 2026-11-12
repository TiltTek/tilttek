<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Application;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\GeneratorBundle\Application\Contract\StateLock;
use Tilttek\GeneratorBundle\Application\Exception\InvalidStateKey;
use Tilttek\GeneratorBundle\Application\MemoryTables;
use Tilttek\GeneratorBundle\Infrastructure\State\InMemoryStateStore;

#[CoversClass(MemoryTables::class)]
#[CoversClass(InvalidStateKey::class)]
final class MemoryTablesTest extends TestCase
{
    public function testADrawGoesOnFromTheStateOfItsKeyAndKeepsTheNewOne(): void
    {
        $store = new InMemoryStateStore();
        $tables = $this->tables($store);

        self::assertNull($tables->state('days', 'campaign'));
        self::assertSame(['Day 1 of spring', 'Day 2 of spring'], $tables->draw('days', 'campaign', 2)->results);
        self::assertSame(['day' => '2', 'season' => 'spring'], $tables->state('days', 'campaign'));
        self::assertSame(['Day 3 of spring'], $tables->draw('days', 'campaign')->results);
        self::assertSame(['Day 1 of spring'], $tables->draw('days', 'other')->results);
        self::assertSame(['day' => '3', 'season' => 'spring'], $store->load('days', 'campaign'));
    }

    public function testTheDrawTakesItsCountUniquenessAndSeed(): void
    {
        $tables = $this->tables(new InMemoryStateStore());
        $result = $tables->draw('name.short', 'campaign', 9, true, 'x');

        self::assertCount(6, $result->results);
        self::assertTrue($result->isShort());
        self::assertSame($result->results, $tables->draw('name.short', 'other', 9, true, 'x')->results);
        self::assertNull($tables->state('name.short', 'campaign'));
        self::assertCount(9, $tables->draw('name.short', 'campaign', 9)->results);
    }

    public function testTheApplicationSetsAndForgetsAState(): void
    {
        $tables = $this->tables(new InMemoryStateStore());

        $tables->set('days', 'campaign', ['day' => 9, 'season' => 'winter']);
        self::assertSame(['day' => '9', 'season' => 'winter'], $tables->state('days', 'campaign'));
        self::assertSame(['Day 10 of winter'], $tables->draw('days', 'campaign')->results);

        $tables->forget('days', 'campaign');
        self::assertNull($tables->state('days', 'campaign'));
        self::assertSame(['Day 1 of spring'], $tables->draw('days', 'campaign')->results);
    }

    public function testAStateIsReadAsADrawReadsIt(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('draw', 'a state is a map of names to texts of at most 1024 characters'));

        $this->tables(new InMemoryStateStore())->set('days', 'campaign', ['day' => ['9']]);
    }

    public function testAKeyIsAShortText(): void
    {
        $tables = $this->tables(new InMemoryStateStore());
        $tables->set('days', str_repeat('é', 200), ['day' => '1']);

        self::assertSame(['day' => '1'], $tables->state('days', str_repeat('é', 200)));

        foreach (['state' => static fn (): ?array => $tables->state('days', ''), 'set' => static fn () => $tables->set('days', '', []), 'forget' => static fn () => $tables->forget('days', str_repeat('k', 201)), 'draw' => static fn (): DrawResult => $tables->draw('days', '')] as $call => $closure) {
            try {
                $closure();
                self::fail(\sprintf('%s takes any key.', $call));
            } catch (InvalidStateKey $invalid) {
                self::assertStringEndsWith('of a state is a text of 1 to 200 characters.', $invalid->getMessage());
            }
        }

        self::assertSame('The key "'.str_repeat('k', 32).'" of a state is a text of 1 to 200 characters.', InvalidStateKey::of(str_repeat('k', 201))->getMessage());
        self::assertSame('The key "k'.str_repeat('é', 31).'" of a state is a text of 1 to 200 characters.', InvalidStateKey::of('k'.str_repeat('é', 200))->getMessage());
    }

    public function testALockHoldsEveryChangeOfAState(): void
    {
        $store = new InMemoryStateStore();
        $lock = new class($store) implements StateLock {
            /**
             * @var list<string>
             */
            public array $held = [];

            public bool $holding = false;

            public function __construct(private readonly InMemoryStateStore $store)
            {
            }

            public function hold(string $generator, string $key, Closure $work): mixed
            {
                $this->held[] = $generator.'|'.$key.'|'.json_encode($this->store->load($generator, $key));
                $this->holding = true;

                try {
                    return $work();
                } finally {
                    $this->holding = false;
                }
            }
        };
        $tables = $this->tables($store, $lock);

        self::assertSame(['Day 1 of spring'], $tables->draw('days', 'campaign')->results);
        $tables->set('days', 'campaign', ['day' => '5', 'season' => 'autumn']);
        self::assertSame(['day' => '5', 'season' => 'autumn'], $tables->state('days', 'campaign'));
        self::assertSame(['Day 6 of autumn'], $tables->draw('days', 'campaign')->results);
        $tables->forget('days', 'campaign');

        self::assertNull($tables->state('days', 'campaign'));
        self::assertFalse($lock->holding);
        self::assertSame([
            'days|campaign|null',
            'days|campaign|{"day":"1","season":"spring"}',
            'days|campaign|{"day":"5","season":"autumn"}',
            'days|campaign|{"day":"6","season":"autumn"}',
        ], $lock->held);
    }

    public function testTheDrawLoadsAndSavesInsideTheLock(): void
    {
        $store = new InMemoryStateStore();
        $lock = new class($store) implements StateLock {
            /**
             * @var list<?array<string, string>>
             */
            public array $after = [];

            public function __construct(private readonly InMemoryStateStore $store)
            {
            }

            public function hold(string $generator, string $key, Closure $work): mixed
            {
                $this->store->save($generator, $key, ['day' => '41', 'season' => 'summer']);
                $result = $work();
                $this->after[] = $this->store->load($generator, $key);

                return $result;
            }
        };

        self::assertSame(['Day 42 of summer'], $this->tables($store, $lock)->draw('days', 'campaign')->results);
        self::assertSame([['day' => '42', 'season' => 'summer']], $lock->after);
    }

    public function testAnInvalidKeyTakesNoLock(): void
    {
        $lock = new class implements StateLock {
            public int $held = 0;

            public function hold(string $generator, string $key, Closure $work): mixed
            {
                ++$this->held;

                return $work();
            }
        };
        $tables = $this->tables(new InMemoryStateStore(), $lock);

        foreach ([static fn (): DrawResult => $tables->draw('days', ''), static fn () => $tables->set('days', '', []), static fn () => $tables->forget('days', '')] as $closure) {
            try {
                $closure();
                self::fail('An empty key is taken.');
            } catch (InvalidStateKey) {
            }
        }

        self::assertSame(0, $lock->held);
    }

    private function tables(InMemoryStateStore $store, ?StateLock $lock = null): MemoryTables
    {
        $fixture = \dirname(__DIR__, 2).'/Fixture';

        return new MemoryTables(new Generator(new FileLoader()->catalog([$fixture.'/generators', $fixture.'/memory.yaml'])), $store, $lock);
    }
}
