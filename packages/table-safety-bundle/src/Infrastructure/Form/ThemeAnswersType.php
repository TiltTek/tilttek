<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\TableThemes;

/**
 * The form of a participant's answers about the themes of a table: one group per category of the catalogue, then the
 * table's own themes, and in each a choice per theme — a boundary for the lines and veils, a level for the consent
 * checklist —, left empty for an open or unanswered theme. Its data is the answer of each theme, by category then by
 * theme key; `ThemeAnswers` turns it into the library's values and back.
 *
 * @extends AbstractType<array<string, array<string, Boundary|ConsentLevel|null>>>
 */
final class ThemeAnswersType extends AbstractType
{
    /** The group of the themes the table added. */
    public const string TABLE_GROUP = 'table';

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $themes = $options['themes'];
        \assert($themes instanceof TableThemes);
        $locale = \is_string($options['locale']) ? $options['locale'] : 'en';
        $class = \is_string($options['answer']) ? $options['answer'] : Boundary::class;
        $choice = [
            'class' => $class,
            'expanded' => true,
            'required' => false,
            'placeholder' => Boundary::class === $class ? 'answers.open' : 'answers.unanswered',
            'choice_label' => static fn (Boundary|ConsentLevel $answer): string => 'answers.'.$answer->value,
            'choice_translation_domain' => 'tilttek_table_safety',
        ];

        foreach ($themes->catalogue->categories() as $category) {
            $group = $builder->create($category->key, FormType::class, ['label' => $category->label($locale), 'translation_domain' => false, 'inherit_data' => false]);

            foreach ($themes->catalogue->themesIn($category->key) as $theme) {
                $group->add($theme->key, EnumType::class, [...$choice, 'label' => $themes->label($theme->key, $locale), 'translation_domain' => false]);
            }

            $builder->add($group);
        }

        if ([] !== $themes->custom) {
            $group = $builder->create(self::TABLE_GROUP, FormType::class, ['label' => 'answers.table_themes', 'translation_domain' => 'tilttek_table_safety']);

            foreach (array_keys($themes->custom) as $key) {
                $group->add((string) $key, EnumType::class, [...$choice, 'label' => $themes->label((string) $key, $locale), 'translation_domain' => false]);
            }

            $builder->add($group);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('themes')->setAllowedTypes('themes', TableThemes::class);
        $resolver->setDefaults(['answer' => Boundary::class, 'locale' => null, 'translation_domain' => 'tilttek_table_safety']);
        $resolver->setAllowedValues('answer', [Boundary::class, ConsentLevel::class]);
        $resolver->setAllowedTypes('locale', ['null', 'string']);
    }
}
