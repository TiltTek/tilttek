<?php

declare(strict_types=1);

namespace Tilttek\SetsBundle\Tests\Functional;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Tilttek\Sets\Holdings;
use Tilttek\Sets\Sets;
use Tilttek\SetsBundle\Infrastructure\Console\DeliverCommand;
use Tilttek\SetsBundle\Infrastructure\DependencyInjection\SetsServices;
use Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletions;
use Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletionsHandler;
use Tilttek\SetsBundle\Infrastructure\Scheduler\DeliverySchedule;
use Tilttek\SetsBundle\Tests\Functional\App\TestKernel;
use Tilttek\SetsBundle\TilttekSetsBundle;

/**
 * The bundle in an application: `Sets` on the application's definitions, the command found by the console, and the
 * schedule and the handler declared to the Scheduler and to Messenger.
 */
#[CoversClass(TilttekSetsBundle::class)]
#[CoversClass(SetsServices::class)]
#[CoversClass(DeliverCommand::class)]
#[CoversClass(DeliverPendingCompletionsHandler::class)]
#[CoversClass(DeliverySchedule::class)]
final class SetsBundleTest extends KernelTestCase
{
    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testTheSetsAreRecordedAndTheCommandResumesTheirDelivery(): void
    {
        $kernel = self::bootKernel();
        $sets = self::getContainer()->get(Sets::class);
        self::assertInstanceOf(Sets::class, $sets);

        $progress = $sets->record('starter', ResourceReference::of('member', '1'), Holdings::of(['a']));
        self::assertCount(1, $progress->recorded);

        $tester = new CommandTester(new Application($kernel)->find('tilttek:sets:deliver'));
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('0 completions delivered.', $tester->getDisplay());
    }

    public function testTheScheduleAndTheHandlerAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $receivers = $container->get('messenger.receiver_locator');
        self::assertInstanceOf(ContainerInterface::class, $receivers);
        self::assertInstanceOf(SchedulerTransport::class, $receivers->get('scheduler_tilttek_sets'));

        $bus = $container->get('messenger.default_bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $handled = $bus->dispatch(new DeliverPendingCompletions(10))->last(HandledStamp::class);
        self::assertInstanceOf(HandledStamp::class, $handled);
        self::assertSame(0, $handled->getResult());
    }
}
