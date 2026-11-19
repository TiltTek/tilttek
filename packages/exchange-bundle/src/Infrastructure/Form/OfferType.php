<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form of an offer or a counter-offer: the member's items to give and the other member's items to ask for, among
 * the choices the application passes — labels to item references, from its inventory —, and the points each side
 * adds. Its data is `['offered' => ['card:12'], 'requested' => ['card:31'], 'offered_points' => 0,
 * 'requested_points' => 0]`, which the application passes to `Trades::offer()` or `Trades::counter()`.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class OfferType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offered', ChoiceType::class, ['label' => 'Items you give', 'choices' => $options['offered_choices'], 'multiple' => true, 'expanded' => true])
            ->add('requested', ChoiceType::class, ['label' => 'Items you ask for', 'choices' => $options['requested_choices'], 'multiple' => true, 'expanded' => true])
            ->add('offered_points', IntegerType::class, ['label' => 'Points you add', 'data' => 0, 'attr' => ['min' => 0]])
            ->add('requested_points', IntegerType::class, ['label' => 'Points you ask for', 'data' => 0, 'attr' => ['min' => 0]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['offered_choices', 'requested_choices']);
        $resolver->setAllowedTypes('offered_choices', 'string[]');
        $resolver->setAllowedTypes('requested_choices', 'string[]');
    }
}
