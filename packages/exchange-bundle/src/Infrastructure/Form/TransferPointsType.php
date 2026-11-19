<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The form a member gives points with: whom to, as the application names its members — a username, a profile
 * address —, and how many. Its data is `['recipient' => 'ada', 'amount' => 50]`; the application finds the member's
 * reference and passes it to `Transfers::transfer()` with its own key.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class TransferPointsType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipient', TextType::class, ['label' => 'Recipient'])
            ->add('amount', IntegerType::class, ['label' => 'Points', 'attr' => ['min' => 1]]);
    }
}
