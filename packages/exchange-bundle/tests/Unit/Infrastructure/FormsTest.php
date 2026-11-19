<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\ExchangeBundle\Infrastructure\Form\OfferType;
use Tilttek\ExchangeBundle\Infrastructure\Form\RedeemRewardType;
use Tilttek\ExchangeBundle\Infrastructure\Form\TransferPointsType;

#[CoversClass(RedeemRewardType::class)]
#[CoversClass(TransferPointsType::class)]
#[CoversClass(OfferType::class)]
final class FormsTest extends TestCase
{
    public function testAMemberReservesAReward(): void
    {
        $form = $this->factory()->create(RedeemRewardType::class);

        self::assertSame(['discount-10 (100)' => 'discount-10', 'seat (250)' => 'seat'], $form->get('reward')->getConfig()->getOption('choices'));
        self::assertSame('Reward', $form->get('reward')->getConfig()->getOption('label'));
        self::assertSame('Quantity', $form->get('quantity')->getConfig()->getOption('label'));
        self::assertSame(['min' => 1], $form->get('quantity')->getConfig()->getOption('attr'));
        self::assertSame(1, $form->get('quantity')->getData());

        $form->submit(['reward' => 'seat', 'quantity' => '2']);
        self::assertTrue($form->isSynchronized());
        self::assertSame(['reward' => 'seat', 'quantity' => 2], $form->getData());

        $only = $this->factory()->create(RedeemRewardType::class, null, ['rewards' => ['seat', 'gone']]);
        self::assertSame(['seat (250)' => 'seat'], $only->get('reward')->getConfig()->getOption('choices'));
    }

    public function testTheRewardsOptionNamesRewards(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(RedeemRewardType::class, null, ['rewards' => 'seat']);
    }

    public function testAMemberGivesPoints(): void
    {
        $form = $this->factory()->create(TransferPointsType::class);

        self::assertSame('Recipient', $form->get('recipient')->getConfig()->getOption('label'));
        self::assertSame('Points', $form->get('amount')->getConfig()->getOption('label'));
        self::assertSame(['min' => 1], $form->get('amount')->getConfig()->getOption('attr'));

        $form->submit(['recipient' => 'ada', 'amount' => '50']);
        self::assertSame(['recipient' => 'ada', 'amount' => 50], $form->getData());
    }

    public function testAMemberMakesAnOffer(): void
    {
        $form = $this->factory()->create(OfferType::class, null, [
            'offered_choices' => ['Red dragon' => 'card:12', 'Blue knight' => 'card:13'],
            'requested_choices' => ['Green elf' => 'card:31'],
        ]);

        self::assertSame(['Red dragon' => 'card:12', 'Blue knight' => 'card:13'], $form->get('offered')->getConfig()->getOption('choices'));
        self::assertTrue($form->get('offered')->getConfig()->getOption('multiple'));
        self::assertTrue($form->get('offered')->getConfig()->getOption('expanded'));
        self::assertTrue($form->get('requested')->getConfig()->getOption('multiple'));
        self::assertTrue($form->get('requested')->getConfig()->getOption('expanded'));
        self::assertSame(['Items you give', 'Items you ask for', 'Points you add', 'Points you ask for'], [
            $form->get('offered')->getConfig()->getOption('label'),
            $form->get('requested')->getConfig()->getOption('label'),
            $form->get('offered_points')->getConfig()->getOption('label'),
            $form->get('requested_points')->getConfig()->getOption('label'),
        ]);
        self::assertSame([0, 0], [$form->get('offered_points')->getData(), $form->get('requested_points')->getData()]);
        self::assertSame([['min' => 0], ['min' => 0]], [$form->get('offered_points')->getConfig()->getOption('attr'), $form->get('requested_points')->getConfig()->getOption('attr')]);

        $form->submit(['offered' => ['card:13'], 'requested' => ['card:31'], 'offered_points' => '5', 'requested_points' => '0']);
        self::assertSame(['offered' => ['card:13'], 'requested' => ['card:31'], 'offered_points' => 5, 'requested_points' => 0], $form->getData());
    }

    public function testAnOfferNeedsItsChoices(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->factory()->create(OfferType::class, null, ['offered_choices' => []]);
    }

    public function testTheChoicesAreItemReferences(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(OfferType::class, null, ['offered_choices' => [], 'requested_choices' => [1]]);
    }

    public function testTheOfferedChoicesAreItemReferences(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(OfferType::class, null, ['offered_choices' => [1], 'requested_choices' => []]);
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addType(new RedeemRewardType(new InMemoryCatalog(new Reward('seat', 250), new Reward('discount-10', 100))))
            ->getFormFactory();
    }
}
