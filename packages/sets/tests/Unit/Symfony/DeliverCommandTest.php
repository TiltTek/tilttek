<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit\Symfony;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Event\TierReached;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\Symfony\DeliverCommand;
use Tilttek\Sets\Tests\Unit\Fixture\FailingDispatcher;
use Tilttek\Sets\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Sets\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Sets\Tier;

#[CoversClass(DeliverCommand::class)]
final class DeliverCommandTest extends TestCase
{
    private InMemoryCompletionStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryCompletionStore();
        $at = new DateTimeImmutable('2026-11-13 12:00:00 UTC');

        foreach (['2', '1', '3'] as $id) {
            $this->store->add(new Completion('starter', ResourceReference::of('member', $id), 'half', 1, $at));
            $this->store->add(new Completion('starter', ResourceReference::of('member', $id), null, 1, $at));
        }
    }

    private function tester(EventDispatcherInterface $events = new RecordingDispatcher()): CommandTester
    {
        $definitions = new InMemoryDefinitionStore();
        $definitions->add(new SetDefinition('starter', 1, ['a', 'b'], [new Tier('half', required: 1)]));

        $application = new Application();
        $application->addCommand(new DeliverCommand(new Sets($definitions, $this->store, new FrozenClock(), $events)));

        return new CommandTester($application->find('tilttek:sets:deliver'));
    }

    /**
     * The display on one line, the blocks of SymfonyStyle unwrapped.
     */
    private function display(CommandTester $tester): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    public function testTheCommandIsNamed(): void
    {
        $attributes = new ReflectionClass(DeliverCommand::class)->getAttributes(AsCommand::class);

        self::assertCount(1, $attributes);
        self::assertSame('tilttek:sets:deliver', $attributes[0]->newInstance()->name);
        self::assertSame('tilttek:sets:deliver', DeliverCommand::NAME);
    }

    public function testThePendingCompletionsAreDelivered(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1']));
        self::assertSame('[OK] 2 completions delivered.', $this->display($tester));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('[OK] 4 completions delivered.', $this->display($tester));
        self::assertSame([], $this->store->holdersWithUndelivered(10));
    }

    public function testALimitOutOfBoundsFails(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '0']));
        self::assertSame('[ERROR] A pending delivery tells 1 to 10000 holders; 0 is not.', $this->display($tester));
        self::assertCount(3, $this->store->holdersWithUndelivered(10));
    }

    public function testAFailingHandlerFailsTheRunAndSaysHowManyWereDelivered(): void
    {
        $tester = $this->tester(new FailingDispatcher(static fn (object $event): bool => $event instanceof TierReached));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame('[ERROR] A handler threw for 1 of the 3 holders told; 5 completions were delivered all the same. The first exception is the previous one. RuntimeException: The handler failed, time 1.', $this->display($tester));
    }

    public function testTheCompletionsOfOneHolderAreDelivered(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--holder' => 'member:1']));
        self::assertSame('[OK] 2 completions delivered to member:1.', $this->display($tester));
        self::assertCount(2, $this->store->holdersWithUndelivered(10));

        self::assertSame(Command::SUCCESS, $tester->execute(['--holder' => 'member:1', '--limit' => '0']));
        self::assertSame('[OK] 0 completions delivered to member:1.', $this->display($tester));
    }

    public function testAHolderThatDoesNotReadFails(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--holder' => 'member']));
        self::assertSame('[ERROR] "member" is not a resource reference of the form "kind:identifier".', $this->display($tester));

        self::assertSame(Command::FAILURE, $tester->execute(['--holder' => 'member:a@b.c']));
        self::assertStringStartsWith('[ERROR] The holder "member:…" has an identifier with "@"', $this->display($tester));
        self::assertCount(3, $this->store->holdersWithUndelivered(10));
    }

    public function testAFailingHandlerFailsTheDeliveryToOneHolder(): void
    {
        $tester = $this->tester(new FailingDispatcher(static fn (object $event): bool => $event instanceof TierReached));

        self::assertSame(Command::FAILURE, $tester->execute(['--holder' => 'member:3']));
        self::assertSame('[ERROR] A handler threw: the completions it was told stay undelivered. RuntimeException: The handler failed, time 1.', $this->display($tester));
        self::assertCount(1, $this->store->undelivered(ResourceReference::of('member', '3')));
    }
}
