<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpression;

/**
 * A text field for a dice expression, checked by the `DiceExpression` constraint in the notation of `locale`, the
 * canonical one when it is null. Its data is the text as typed; the spelling checker is off.
 *
 * @extends AbstractType<string|null>
 */
final class DiceExpressionType extends AbstractType
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'locale' => null,
            'constraints' => static fn (Options $options): array => [new DiceExpression(locale: \is_string($options['locale']) ? $options['locale'] : null)],
            'attr' => ['spellcheck' => 'false', 'autocomplete' => 'off'],
        ]);
        $resolver->setAllowedTypes('locale', ['null', 'string']);
    }

    #[Override]
    public function getParent(): string
    {
        return TextType::class;
    }
}
