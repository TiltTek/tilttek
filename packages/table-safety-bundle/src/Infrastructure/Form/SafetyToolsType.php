<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\TableSafety\SafetyTool;

/**
 * The safety tools a table announces, as checkboxes: for the announcement of a table, where naming them is recommended
 * and never required. Its data is a list of `SafetyTool`.
 *
 * @extends AbstractType<list<SafetyTool>>
 */
final class SafetyToolsType extends AbstractType
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => SafetyTool::class,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'label' => 'tools.label',
            'help' => 'tools.recommended',
            'choice_label' => static fn (SafetyTool $tool): string => 'tools.'.$tool->value,
            'translation_domain' => 'tilttek_table_safety',
        ]);
    }

    #[Override]
    public function getParent(): string
    {
        return EnumType::class;
    }
}
