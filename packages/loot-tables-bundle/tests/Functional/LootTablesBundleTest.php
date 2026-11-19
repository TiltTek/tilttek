<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Application\OptionDraws;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootPrint;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Form\PhysicalDiceType;
use Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf;
use Tilttek\LootTablesBundle\Tests\Functional\App\TestKernel;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;
use Twig\Environment;

/**
 * The bundle in an application with the commerce core: the draws committed when the workflow places the order, shown
 * to the buyer, revealed by the core once the order is paid, kept in Doctrine, verified and rolled again from their
 * seed; physical dice entered at a register through the form; the production sheet.
 */
#[CoversNothing]
final class LootTablesBundleTest extends KernelTestCase
{
    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        if (null === TestKernel::kit()) {
            self::markTestSkipped("The PHIL UX kit is not installed: the buyer's draws have no recipes to draw with.");
        }

        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testTheWorkflowCommitsThePlacedOrderAndTheCoreDrawsItOncePaid(): void
    {
        $order = Order::open('1042', Currency::of('CAD'));
        $order->add(Shop::sellable('shirt'), 2, Money::of(2_500, Currency::of('CAD')));
        $order->add(Shop::sellable('epitaph'), 1, Money::of(2_500, Currency::of('CAD')), LineConfiguration::of(['epitaph' => OptionValue::toDraw()]));
        self::assertSame([], $this->service(LootDrawRepository::class)->ofOrder('1042'));

        $this->service(OrderLifecycle::class)->place($order, new DateTimeImmutable('2026-11-08 10:00:00'));
        self::assertCount(3, $this->service(LootResults::class)->ofOrder('1042'));

        $payment = Payment::create($order, 'card', $order->total(), new DateTimeImmutable('2026-11-08 10:05:00'));
        $payment->apply(PaymentNotification::of('evt_1', 'pi', PaymentEvent::Captured));
        self::assertSame(2, $this->service(OptionDraws::class)->draw($order, OrderPayments::of($order, [$payment])));

        $results = $this->service(LootResults::class);
        $draws = $results->ofOrder('1042');
        self::assertCount(3, $draws);
        $shirt = Shop::line($order, 'shirt');
        $loot = $shirt->configuration->value('loot');
        self::assertNotNull($loot);
        self::assertCount(2, $loot->draws);

        foreach ($draws as $draw) {
            self::assertTrue($results->verify($draw));
            $result = $results->result($draw);
            self::assertNotNull($result);
            self::assertSame($result->faces(), $results->replay($draw)?->faces());

            if ('loot-text' === $draw->drawer()) {
                self::assertSame(Shop::line($order, 'epitaph')->configuration->value('epitaph')?->draws, [$draw->drawn()]);
            } else {
                self::assertSame($loot->draws[$draw->unit() - 1], DrawText::write($result));
            }
        }
    }

    public function testPhysicalDiceAtARegister(): void
    {
        $order = Shop::order(['shirt' => 1], '1043');
        $reference = Shop::line($order, 'shirt')->reference;
        $this->service(PhysicalDice::class)->enter($order, $reference, 'loot', ['53']);

        self::assertSame(['5-3: Glass ring, Crown'], $order->line($reference)?->configuration->value('loot')?->draws);
        $draws = $this->service(LootResults::class)->ofOrder('1043');
        self::assertCount(1, $draws);
        self::assertSame('53', $this->service(LootResults::class)->shopCode($draws[0]));
    }

    public function testTheBuyerSeesTheCommitmentsWithoutJavaScriptThenTheSeedRevealed(): void
    {
        $order = Shop::order(['shirt' => 1], '1044');
        $this->service(OrderRepository::class)->add($order);
        $this->service(LootCommitments::class)->commit($order);
        $twig = $this->service(Environment::class);
        $template = $twig->createTemplate('{{ component("TilttekLootTables:Commitments", {order: "1044", id: "loot"}) }}');

        $html = $template->render();

        [$draw] = $this->service(LootResults::class)->ofOrder('1044');
        self::assertStringNotContainsString('data-controller', $html);
        self::assertStringNotContainsString('<input', $html);
        self::assertStringContainsString('<section id="loot" class="tilttek-loot-commitments" aria-labelledby="loot-title">', $html);
        self::assertStringContainsString('<h2 id="loot-title">Vos tirages</h2>', $html);
        self::assertMatchesRegularExpression('{<li data-slot="card" data-size="sm" class="isCard isSmall tilttek-loot-commitments__draw" data-draw="'.$draw->id().'">}', $html);
        self::assertMatchesRegularExpression('{<h3 data-slot="card-title" class="isCardTitle">\s*Shirt, Loot, unité 1\s*</h3>}', $html);
        self::assertMatchesRegularExpression('{<span data-slot="badge" data-variant="secondary" class="isBadge isSecondary tilttek-loot-commitments__state">\s*Scellé jusqu’au paiement\s*</span>}', $html);
        self::assertStringContainsString('<dt>Engagement</dt>', $html);
        self::assertStringContainsString('<code class="tilttek-loot-commitments__commitment">'.$draw->commitment().'</code>', $html);
        self::assertStringNotContainsString('Graine révélée', $html);

        $payment = Payment::create($order, 'card', $order->total(), new DateTimeImmutable('2026-11-08 10:05:00'));
        $payment->apply(PaymentNotification::of('evt_2', 'pi', PaymentEvent::Captured));
        $this->service(OptionDraws::class)->draw($order, OrderPayments::of($order, [$payment]));
        $revealed = $template->render();
        [$draw] = $this->service(LootResults::class)->ofOrder('1044');

        self::assertMatchesRegularExpression('{data-variant="default" class="isBadge tilttek-loot-commitments__state">\s*Conforme à son engagement\s*</span>}', $revealed);
        self::assertStringContainsString('<dt>Graine révélée</dt>', $revealed);
        self::assertStringContainsString('<code class="tilttek-loot-commitments__seed">'.$draw->revealedSeed().'</code>', $revealed);
        self::assertStringContainsString('<dt>Code de la boutique</dt>', $revealed);
        self::assertStringContainsString('<li>Rings : ', $revealed);
    }

    public function testTheCommandRollsARevealedDrawAgain(): void
    {
        $order = Shop::order(['shirt' => 1], '1047');
        [$draw] = $this->service(LootCommitments::class)->commit($order);
        $payment = Payment::create($order, 'card', $order->total(), new DateTimeImmutable('2026-11-08 10:05:00'));
        $payment->apply(PaymentNotification::of('evt_3', 'pi', PaymentEvent::Captured));
        $this->service(OptionDraws::class)->draw($order, OrderPayments::of($order, [$payment]));
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $tester = new CommandTester(new Application($kernel)->find('tilttek:loot-tables:replay'));

        self::assertSame(0, $tester->execute(['draw' => $draw->id()]));
        self::assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testAnOrderWithoutDrawsSaysSo(): void
    {
        $html = $this->service(Environment::class)->createTemplate('{{ component("TilttekLootTables:Commitments", {order: "404"}) }}')->render();

        self::assertStringContainsString('<section id="tilttek-loot-commitments" class="tilttek-loot-commitments" aria-labelledby="tilttek-loot-commitments-title">', $html);

        self::assertStringContainsString('Cette commande n’a rien à tirer.', $html);
    }

    public function testTheRegisterEntersTheDiceThroughTheForm(): void
    {
        $order = Shop::order(['shirt' => 2], '1045');
        $reference = Shop::line($order, 'shirt')->reference;
        $physicalDice = $this->service(PhysicalDice::class);
        $form = $this->formFactory()->create(PhysicalDiceType::class, null, $physicalDice->toEnter($order, $reference, 'loot'));

        $component = $this->service(Environment::class)->createTemplate('{{ component("TilttekLootTables:PhysicalDice", {form: form}) }}');
        $html = $component->render(['form' => $form->createView()]);
        self::assertStringContainsString('<div class="tilttek-loot-physical-dice">', $html);
        self::assertStringContainsString('<legend>Dés de Starter box</legend>', $html);
        self::assertStringContainsString('<label for="physical_dice_unit_1" class="required">Unité 1</label>', $html);
        self::assertStringContainsString('Un caractère par dé, 2 dés, dans cet ordre : Rings, Hats.', $html);
        self::assertStringContainsString('pattern="[123456]{2}"', $html);
        self::assertStringContainsString('<button type="submit">Saisir les dés</button>', $html);

        $form->submit(['unit_1' => '17', 'unit_2' => '25']);
        self::assertFalse($form->isValid());
        self::assertStringContainsString('« 17 » n’est pas un jet des dés de cette boîte : un caractère par dé, 2 dés.', $component->render(['form' => $form->createView()]));

        $form = $this->formFactory()->create(PhysicalDiceType::class, null, $physicalDice->toEnter($order, $reference, 'loot'));
        $form->submit(['unit_1' => '36', 'unit_2' => '25']);
        self::assertTrue($form->isValid());
        $physicalDice->enter($order, $reference, 'loot', PhysicalDiceType::codes($form));

        self::assertSame(['3-6: Gold ring, Bare head', '2-5: Silver ring, Pot'], $order->line($reference)?->configuration->value('loot')?->draws);
    }

    public function testTheProductionSheetReadsTheResults(): void
    {
        $order = Shop::order(['shirt' => 1], '1046');
        $this->service(PhysicalDice::class)->enter($order, Shop::line($order, 'shirt')->reference, 'loot', ['53']);

        $pdf = $this->service(LootSheetPdf::class)->render('1046');

        self::assertStringStartsWith('%PDF-', $pdf);
        $printed = $this->service(LootPrint::class)->compose('1046');
        self::assertCount(1, $printed);
        self::assertSame('53', $printed[0]->shopCode);
        self::assertSame(['/loot/crown.png'], $printed[0]->images());
    }

    private function formFactory(): FormFactoryInterface
    {
        $factory = self::getContainer()->get('test.form_factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        return $factory;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
