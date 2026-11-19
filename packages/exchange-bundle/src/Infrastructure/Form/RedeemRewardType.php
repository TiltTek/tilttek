<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\Exchange\Reward\Catalog;

/**
 * The form a member reserves a reward with: the reward, among those of the catalogue or those the application lists
 * in `rewards`, each shown with its price, and the units. Its data is `['reward' => 'discount-10', 'quantity' => 1]`, which the application passes
 * to `Redemptions::reserve()` with its own key.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class RedeemRewardType extends AbstractType
{
    public function __construct(
        private readonly Catalog $catalog,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $only = $options['rewards'];
        $choices = [];

        foreach ($this->catalog->all() as $reward) {
            if (!\is_array($only) || \in_array($reward->code, $only, true)) {
                $choices[\sprintf('%s (%d)', $reward->code, $reward->price)] = $reward->code;
            }
        }

        $builder
            ->add('reward', ChoiceType::class, ['label' => 'Reward', 'choices' => $choices])
            ->add('quantity', IntegerType::class, ['label' => 'Quantity', 'data' => 1, 'attr' => ['min' => 1]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('rewards', null);
        $resolver->setAllowedTypes('rewards', ['null', 'string[]']);
    }
}
