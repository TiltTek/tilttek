<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\TableSafety\SessionZero\Question;
use Tilttek\TableSafety\SessionZero\QuestionKind;
use Tilttek\TableSafety\SessionZero\Template;

/**
 * The form of a session zero: one field per question of the table's template, in order — a text area, a group of
 * radio buttons or a group of checkboxes. A question the game master wrote shows their prompt and labels; a built-in
 * one is translated from its key. Its data is the answers by question key, as `Template::answer()` takes them.
 *
 * @extends AbstractType<array<string, string|list<string>|null>>
 */
final class SessionZeroType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $template = $options['template'];
        \assert($template instanceof Template);

        foreach ($template->questions() as $question) {
            $label = $question->prompt ?? 'session_zero.questions.'.$question->key;
            $common = ['label' => $label, 'required' => $question->required, 'translation_domain' => $question->isWritten() ? false : 'tilttek_table_safety'];

            if (QuestionKind::Text === $question->kind) {
                $builder->add($question->key, TextareaType::class, [...$common, 'attr' => ['maxlength' => Question::MAX_ANSWER_LENGTH, 'rows' => 3]]);

                continue;
            }

            $builder->add($question->key, ChoiceType::class, [
                ...$common,
                'choices' => $this->choices($question),
                'expanded' => true,
                'multiple' => QuestionKind::Choices === $question->kind,
                'placeholder' => false,
                'choice_translation_domain' => $question->isWritten() ? false : 'tilttek_table_safety',
            ]);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('template')->setAllowedTypes('template', Template::class);
        $resolver->setDefaults(['translation_domain' => 'tilttek_table_safety']);
    }

    /**
     * @return array<string, string> the option keys by their label
     */
    private function choices(Question $question): array
    {
        $choices = [];

        foreach ($question->options as $option => $label) {
            $choices[$label ?? 'session_zero.options.'.$question->key.'.'.$option] = (string) $option;
        }

        return $choices;
    }
}
