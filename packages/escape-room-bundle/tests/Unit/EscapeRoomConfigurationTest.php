<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Rooms;
use Tilttek\EscapeRoomBundle\TilttekEscapeRoomBundle;

/**
 * The defaults, the choices and the limits of the configuration tree.
 */
#[CoversClass(TilttekEscapeRoomBundle::class)]
final class EscapeRoomConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, int, int, int}>
     */
    public static function limits(): iterable
    {
        yield 'duration' => [['rooms', 'a', 'duration'], Room::MIN_DURATION, Room::MAX_DURATION, 3_600];
        yield 'hint penalty' => [['rooms', 'a', 'hint_penalty'], 0, Room::MAX_PENALTY, 0];
        yield 'skip penalty' => [['rooms', 'a', 'skip_penalty'], 0, Room::MAX_PENALTY, 0];
        yield 'smallest team' => [['rooms', 'a', 'min_players'], 1, Room::MAX_PLAYERS, 2];
        yield 'largest team' => [['rooms', 'a', 'max_players'], 1, Room::MAX_PLAYERS, 6];
        yield 'guess limit' => [['guesses', 'limit'], 1, 1_000, 10];
        yield 'guess window' => [['guesses', 'seconds'], 1, 86_400, 60];
        yield 'batch' => [['schedule', 'batch'], 1, 1_000, 100];
        yield 'poll' => [['mercure', 'poll'], 1, 300, 5];
        yield 'leaderboard' => [['leaderboard_size'], 1, 1_000, 10];
    }

    /**
     * @param list<string> $path
     */
    #[DataProvider('limits')]
    public function testAValueIsAcceptedFromItsLowestToItsHighestAndDefaultsBetween(array $path, int $min, int $max, int $default): void
    {
        self::assertSame($default, $this->read($this->process($this->with($path, null)), $path));
        self::assertSame($min, $this->read($this->process($this->with($path, $min)), $path));
        self::assertSame($max, $this->read($this->process($this->with($path, $max)), $path));

        foreach ([$min - 1, $max + 1] as $refused) {
            try {
                $this->process($this->with($path, $refused));
                self::fail(\sprintf('%d is refused for %s.', $refused, implode('.', $path)));
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString($path[\count($path) - 1], $exception->getMessage());
            }
        }
    }

    public function testBothStoresAreAccepted(): void
    {
        self::assertSame('doctrine', $this->process([])['store']);
        self::assertSame('doctrine', $this->process(['store' => 'doctrine'])['store']);
        self::assertSame('memory', $this->process(['store' => 'memory'])['store']);
    }

    public function testBothEffectsAreAccepted(): void
    {
        self::assertSame('confetti', $this->read($this->process([]), ['celebration', 'effect']));
        self::assertSame('confetti', $this->read($this->process(['celebration' => ['effect' => 'confetti']]), ['celebration', 'effect']));
        self::assertSame('fireworks', $this->read($this->process(['celebration' => ['effect' => 'fireworks']]), ['celebration', 'effect']));
    }

    public function testTheIdentifierOfARoomKeepsItsDashes(): void
    {
        $rooms = $this->read($this->process(['rooms' => ['old-crypt' => ['title' => 'T', 'scenario' => Rooms::config()['cell']['scenario']]]]), ['rooms']);
        self::assertIsArray($rooms);

        self::assertSame(['old-crypt'], array_keys($rooms));
    }

    /**
     * @param list<string> $path
     *
     * @return array<string, mixed>
     */
    private function with(array $path, ?int $value): array
    {
        $room = ['title' => 'T', 'scenario' => Rooms::config()['cell']['scenario']];

        if (null === $value) {
            return ['rooms' => ['a' => $room]];
        }

        if ('rooms' === $path[0]) {
            return ['rooms' => ['a' => [$path[2] => $value] + $room]];
        }

        $config = ['rooms' => ['a' => $room]];

        if (1 === \count($path)) {
            $config[$path[0]] = $value;
        } else {
            $config[$path[0]] = [$path[1] => $value];

            if ('schedule' === $path[0]) {
                $config['schedule']['enabled'] = true;
            }
        }

        return $config;
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string>            $path
     */
    private function read(array $config, array $path): mixed
    {
        foreach ($path as $key) {
            self::assertIsArray($config);
            self::assertArrayHasKey($key, $config);
            $config = $config[$key];
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = new TilttekEscapeRoomBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return new Processor()->process($configuration->getConfigTreeBuilder()->buildTree(), [$config]);
    }
}
