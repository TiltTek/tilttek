<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\SurpriseKind;

/**
 * The surprise of one box, as an array `{kind, reference, quantity}`: what it grants, the ledger, badge, content or
 * pool it refers to, and how many — a field left empty is one point of the ledger `points`.
 *
 * @extends AbstractType<array{kind: string, reference: string, quantity: int}>
 */
final class SurpriseType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', ChoiceType::class, [
                'label' => 'form.surprise.kind',
                'empty_data' => SurpriseKind::Points->value,
                'choices' => array_combine(
                    array_map(static fn (SurpriseKind $kind): string => 'surprise.kind.'.$kind->value, SurpriseKind::cases()),
                    array_map(static fn (SurpriseKind $kind): string => $kind->value, SurpriseKind::cases()),
                ),
            ])
            ->add('reference', TextType::class, ['label' => 'form.surprise.reference', 'empty_data' => 'points', 'attr' => ['maxlength' => 255]])
            ->add('quantity', IntegerType::class, ['label' => 'form.surprise.quantity', 'empty_data' => '1', 'attr' => ['min' => 1, 'max' => Surprise::MAX_QUANTITY]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'tilttek_advent_calendar', 'empty_data' => static fn (): array => ['kind' => SurpriseKind::Points->value, 'reference' => 'points', 'quantity' => 1]]);
    }
}
