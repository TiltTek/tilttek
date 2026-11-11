<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\QuestState;
use Tilttek\Quests\QuestStatus;
use Tilttek\Quests\Scope;
use Tilttek\Quests\Step;
use Tilttek\QuestsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\QuestsBundle\Infrastructure\Event\DuelExpired;
use Tilttek\QuestsBundle\Infrastructure\Event\DuelWon;
use Tilttek\QuestsBundle\Infrastructure\Twig\QuestsExtension;
use Tilttek\QuestsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(DispatchingObserver::class)]
#[CoversClass(DuelWon::class)]
#[CoversClass(DuelExpired::class)]
#[CoversClass(QuestsExtension::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheObserverDispatchesEvents(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /**
             * @var list<object>
             */
            public array $events = [];

            #[Override]
            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };
        $observer = new DispatchingObserver($dispatcher);
        $member = ResourceReference::fromString('member:1');
        $at = new DateTimeImmutable('2026-11-07 UTC');
        $completed = new QuestCompleted(new Quest('q', [new Step('s', new Scope(['a.b']))]), new Completion('q', '', $member, $at, 'k1'), [$member]);
        $duel = new Duel('d1', 'q', $member, ResourceReference::fromString('member:2'), $at);

        $observer->completed($completed);
        $observer->duelWon($duel);
        $observer->duelExpired($duel);

        self::assertEquals([$completed, new DuelWon($duel), new DuelExpired($duel)], $dispatcher->events);
        self::assertSame($duel, $dispatcher->events[1] instanceof DuelWon ? $dispatcher->events[1]->duel : null);
        self::assertSame($duel, $dispatcher->events[2] instanceof DuelExpired ? $dispatcher->events[2]->duel : null);
    }

    public function testTheTwigFunctions(): void
    {
        $kit = $this->kit();
        $kit->at('2026-11-07 09:00');
        $kit->duelService->challenge('d1', 'race', ResourceReference::fromString('member:1'), ResourceReference::fromString('member:2'));
        $kit->duelService->accept('d1', ResourceReference::fromString('member:2'));
        $kit->record('k1', 'member:1', 'game.played', '2026-11-07 10:00');

        $extension = new QuestsExtension($kit->quests, $kit->duelService, 'quests');

        self::assertSame('quests', $extension->domain());
        self::assertSame(['one' => QuestState::Completed, 'two' => QuestState::UnderWay], array_combine(
            array_map(static fn (QuestStatus $status): string => $status->quest->code, $extension->board('member:1')),
            array_map(static fn (QuestStatus $status): QuestState => $status->state, $extension->board('member:1')),
        ));
        self::assertSame(1, $extension->status('member:1', 'two')->progress->steps[0]->value);
        self::assertSame(1, $extension->duel('d1')->challenger->steps[0]->value);
    }

    private function kit(): Kit
    {
        return Kit::of([
            'one' => ['steps' => [['code' => 'play', 'verbs' => ['game.played']]]],
            'two' => ['steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
            'race' => ['kind' => 'duel', 'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
        ]);
    }
}
