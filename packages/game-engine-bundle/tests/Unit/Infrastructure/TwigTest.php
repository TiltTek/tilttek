<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;
use Tilttek\GameEngineBundle\Infrastructure\Twig\GameEngineExtension;
use Tilttek\GameEngineBundle\Infrastructure\Twig\KitComponents;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

#[CoversClass(GameEngineExtension::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class TwigTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testATableIsWhatAnyoneAtItMayKnow(): void
    {
        $tables = new GameTables(new Tables(new Games(new RollRace()), new InMemoryLogStore()));
        $tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 2], self::SEED);

        $extension = new GameEngineExtension($tables, 'games', new KitComponents('ui'), new TableTopic());

        self::assertSame([
            'table' => 'table:1',
            'game' => 'roll-race',
            'sequence' => 0,
            'phase' => 'race',
            'round' => 1,
            'to_act' => ['ann'],
            'over' => false,
            'outcome' => null,
            'commitment' => hash('sha256', self::SEED),
            'topic' => 'urn:tilttek-game-engine:table:table:1',
        ], $extension->table('table:1'));
        self::assertSame('games', $extension->domain());
        self::assertSame('ui:Card', $extension->kit('Card'));

        $tables->play('table:1', new Move('roll.roll', 'ann'));
        $over = new GameEngineExtension($tables)->table('table:1');
        self::assertTrue($over['over']);
        self::assertSame([], $over['to_act']);
        self::assertSame('target-reached', $over['outcome']['reason'] ?? null);
        self::assertNull($over['topic']);
        self::assertSame('tilttek_game_engine', new GameEngineExtension($tables)->domain());
        self::assertSame('Card', new GameEngineExtension($tables)->kit('Card'));
    }

    public function testTheRecipesAreNamedAfterTheirSubdirectory(): void
    {
        self::assertSame('Badge', new KitComponents()->name('Badge'));
        self::assertSame('Badge', new KitComponents(null)->name('Badge'));
        self::assertSame('Badge', new KitComponents('/')->name('Badge'));
        self::assertSame('ui:Badge', new KitComponents('/ui/')->name('Badge'));
        self::assertSame('ui:kit:Card:Title', new KitComponents('ui/kit')->name('Card:Title'));
    }

    public function testTheSubdirectoryIsThatOfTheToolkitUnlessTheBundleNamesOne(): void
    {
        foreach ([[null, 'ui', 'ui'], [null, null, ''], [null, 3, ''], ['mine', 'ui', 'mine']] as [$configured, $toolkit, $expected]) {
            $container = new ContainerBuilder();
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$configured]);

            if (null !== $toolkit) {
                $container->setParameter('.ux_toolkit.components.subdirectory', $toolkit);
            }

            new KitSubdirectoryPass()->process($container);
            self::assertSame($expected, $container->getDefinition(KitComponents::class)->getArgument(0));
        }

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
    }
}
