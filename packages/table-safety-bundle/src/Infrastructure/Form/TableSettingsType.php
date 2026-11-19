<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Question;
use Tilttek\TableSafety\SessionZero\QuestionKind;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\TableSettings;

/**
 * The game master's form of a table's settings: its tools, whether its signals are named, who sees the X-card and
 * Script Change, the themes it adds — one label per line —, the built-in template its session zero starts from, and
 * questions of its own — one per line, each a text question. `settings()` turns the data into `TableSettings`.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class TableSettingsType extends AbstractType
{
    /** The choice that keeps the table's session zero as it is. */
    public const string KEEP = 'keep';

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tools', SafetyToolsType::class, ['help' => null])
            ->add('named_signals', CheckboxType::class, ['label' => 'settings.named_signals', 'help' => 'settings.named_signals_help', 'required' => false])
            ->add('signals_seen_by', EnumType::class, [
                'class' => Audience::class,
                'expanded' => true,
                'label' => 'settings.signals_seen_by',
                'choice_label' => static fn (Audience $audience): string => 'settings.audience.'.$audience->value,
            ])
            ->add('custom_themes', TextareaType::class, ['label' => 'settings.custom_themes', 'help' => 'settings.one_per_line', 'required' => false])
            ->add('template', ChoiceType::class, [
                'label' => 'settings.template',
                'choices' => [self::KEEP => self::KEEP, ...array_combine(Templates::keys(), Templates::keys())],
                'choice_label' => static fn (string $key): string => 'settings.templates.'.$key,
            ])
            ->add('questions', TextareaType::class, ['label' => 'settings.questions', 'help' => 'settings.one_per_line', 'required' => false]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'tilttek_table_safety']);
    }

    /**
     * The form's data for the table's settings.
     *
     * @return array{tools: list<SafetyTool>, named_signals: bool, signals_seen_by: Audience, custom_themes: string, template: string, questions: string}
     */
    public static function data(TableSettings $settings): array
    {
        $written = array_filter($settings->sessionZero->questions(), static fn (Question $question): bool => $question->isWritten());

        return [
            'tools' => $settings->tools,
            'named_signals' => $settings->namedSignals,
            'signals_seen_by' => $settings->signalsSeenBy,
            'custom_themes' => implode("\n", $settings->customThemes),
            'template' => self::KEEP,
            'questions' => implode("\n", array_map(static fn (Question $question): string => (string) $question->prompt, $written)),
        ];
    }

    /**
     * The settings the form's data stands for, from the table's current ones.
     *
     * @param array<array-key, mixed> $data what the form returned
     */
    public static function settings(TableSettings $current, array $data): TableSettings
    {
        $tools = array_values(array_filter(\is_array($data['tools'] ?? null) ? $data['tools'] : [], static fn (mixed $tool): bool => $tool instanceof SafetyTool));
        $template = \is_string($data['template'] ?? null) && Templates::has($data['template']) ? Templates::get($data['template']) : $current->sessionZero;

        foreach ($template->questions() as $question) {
            if ($question->isWritten()) {
                $template = $template->without($question->key);
            }
        }

        foreach (StarsAndWishesType::lines($data['questions'] ?? null) as $i => $prompt) {
            $template = $template->with(new Question('own-'.($i + 1), QuestionKind::Text, $prompt));
        }

        return new TableSettings(
            $tools,
            true === ($data['named_signals'] ?? false),
            ($data['signals_seen_by'] ?? null) instanceof Audience ? $data['signals_seen_by'] : Audience::Table,
            self::themes(StarsAndWishesType::lines($data['custom_themes'] ?? null)),
            $template,
        );
    }

    /**
     * Each label under a key made of it: lower case ASCII words joined by hyphens, prefixed so that it never meets a
     * key of the catalogue.
     *
     * @param list<string> $labels
     *
     * @return array<string, string>
     */
    private static function themes(array $labels): array
    {
        $slugger = new AsciiSlugger();
        $themes = [];

        foreach ($labels as $label) {
            $slug = substr($slugger->slug($label)->lower()->toString(), 0, 50);
            $themes['table-'.('' === $slug ? hash('xxh32', $label) : trim($slug, '-'))] = $label;
        }

        return $themes;
    }
}
