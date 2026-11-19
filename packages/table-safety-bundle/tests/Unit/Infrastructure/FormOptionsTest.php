<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use Phil\ContentWarnings\BaseCatalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Question;
use Tilttek\TableSafety\SessionZero\QuestionKind;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\TableSettings;
use Tilttek\TableSafety\TableThemes;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SafetyToolsType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SessionZeroType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\StarsAndWishesType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\TableSettingsType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswersType;

/**
 * The options each form gives its fields: labels, translation domains, widgets and what is required.
 */
#[CoversClass(ThemeAnswersType::class)]
#[CoversClass(SessionZeroType::class)]
#[CoversClass(StarsAndWishesType::class)]
#[CoversClass(SafetyToolsType::class)]
#[CoversClass(TableSettingsType::class)]
final class FormOptionsTest extends TestCase
{
    private const string DOMAIN = 'tilttek_table_safety';

    public function testTheToolsAreCheckboxesNamedByTheirKey(): void
    {
        $config = $this->forms()->create(SafetyToolsType::class)->getConfig();

        self::assertTrue($config->getOption('expanded'));
        self::assertSame(self::DOMAIN, $config->getOption('translation_domain'));
        self::assertSame('tools.label', $config->getOption('label'));
        self::assertSame('tools.x-card', $this->label($config, SafetyTool::XCard));
    }

    public function testEachQuestionOfASessionZeroKeepsItsOptions(): void
    {
        $template = Templates::get('online')->with(new Question('note', QuestionKind::Text, 'A note?', required: true))->with(new Question('mood', QuestionKind::Choice, 'Mood?', ['a' => 'A', 'b' => 'B']))->with(new Question('numbers', QuestionKind::Choices, 'Pick', ['1' => 'One', '2' => 'Two']));
        $form = $this->forms()->create(SessionZeroType::class, null, ['template' => $template]);

        self::assertSame(self::DOMAIN, $form->getConfig()->getOption('translation_domain'));

        $tone = $form->get('tone')->getConfig();
        self::assertTrue($tone->getOption('expanded'));
        self::assertNull($tone->getOption('placeholder'));
        self::assertSame(self::DOMAIN, $tone->getOption('choice_translation_domain'));

        $comfort = $form->get('comfort')->getConfig();
        self::assertSame('session_zero.questions.comfort', $comfort->getOption('label'));
        self::assertSame(self::DOMAIN, $comfort->getOption('translation_domain'));
        self::assertFalse($comfort->getOption('required'));

        $note = $form->get('note')->getConfig();
        self::assertSame('A note?', $note->getOption('label'));
        self::assertFalse($note->getOption('translation_domain'));
        self::assertTrue($note->getOption('required'));

        $mood = $form->get('mood')->getConfig();
        self::assertNull($mood->getOption('placeholder'));
        self::assertFalse($mood->getOption('required'));

        $numbers = $form->get('numbers')->getConfig();
        self::assertSame(['One' => '1', 'Two' => '2'], $numbers->getOption('choices'));
        self::assertTrue($numbers->getOption('expanded'));
        self::assertFalse($numbers->getOption('required'));
        self::assertFalse($numbers->getOption('choice_translation_domain'));
    }

    public function testAnOptionWithoutALabelIsTranslatedFromItsKeys(): void
    {
        $template = Templates::get('online')->with(new Question('mood', QuestionKind::Choice, null, ['1' => null, '2' => null]));
        $form = $this->forms()->create(SessionZeroType::class, null, ['template' => $template]);

        self::assertSame(['session_zero.options.mood.1' => '1', 'session_zero.options.mood.2' => '2'], $form->get('mood')->getConfig()->getOption('choices'));
    }

    public function testStarsAndWishesAreLabelledAndOptional(): void
    {
        $form = $this->forms()->create(StarsAndWishesType::class);

        self::assertSame(self::DOMAIN, $form->getConfig()->getOption('translation_domain'));

        foreach (['stars' => 'feedback.stars', 'wishes' => 'feedback.wishes'] as $field => $label) {
            $config = $form->get($field)->getConfig();
            self::assertSame($label, $config->getOption('label'));
            self::assertSame('feedback.one_per_line', $config->getOption('help'));
            self::assertFalse($config->getOption('required'));
        }
    }

    public function testTheFieldsOfTheSettingsOfATable(): void
    {
        $form = $this->forms()->create(TableSettingsType::class, TableSettingsType::data(TableSettings::defaults()));

        self::assertSame(self::DOMAIN, $form->getConfig()->getOption('translation_domain'));
        self::assertNull($form->get('tools')->getConfig()->getOption('help'));

        $named = $form->get('named_signals')->getConfig();
        self::assertSame('settings.named_signals', $named->getOption('label'));
        self::assertSame('settings.named_signals_help', $named->getOption('help'));
        self::assertFalse($named->getOption('required'));

        $audience = $form->get('signals_seen_by')->getConfig();
        self::assertSame('settings.signals_seen_by', $audience->getOption('label'));
        self::assertTrue($audience->getOption('expanded'));
        self::assertSame('settings.audience.game-master', $this->label($audience, Audience::GameMaster));

        $themes = $form->get('custom_themes')->getConfig();
        self::assertSame('settings.custom_themes', $themes->getOption('label'));
        self::assertSame('settings.one_per_line', $themes->getOption('help'));
        self::assertFalse($themes->getOption('required'));

        $template = $form->get('template')->getConfig();
        self::assertSame('settings.template', $template->getOption('label'));
        self::assertSame(['keep' => 'keep', ...array_combine(Templates::keys(), Templates::keys())], $template->getOption('choices'));
        self::assertSame('settings.templates.one-shot', $this->label($template, 'one-shot'));

        $questions = $form->get('questions')->getConfig();
        self::assertSame('settings.questions', $questions->getOption('label'));
        self::assertSame('settings.one_per_line', $questions->getOption('help'));
        self::assertFalse($questions->getOption('required'));
    }

    public function testTheSettingsOfATableKeepOnlyTheToolsAndReadMissingFieldsAsOff(): void
    {
        $settings = TableSettingsType::settings(TableSettings::defaults(), ['tools' => ['junk', SafetyTool::XCard, 3, SafetyTool::OpenDoor]]);

        self::assertSame([SafetyTool::XCard, SafetyTool::OpenDoor], $settings->tools);
        self::assertFalse($settings->namedSignals);
        self::assertSame(Audience::Table, $settings->signalsSeenBy);
    }

    public function testTheQuestionsWrittenBeforeAreReplacedByTheOnesSubmitted(): void
    {
        $current = TableSettings::defaults()->withSessionZero(Templates::get('online')->with(new Question('own-1', QuestionKind::Text, 'One?'))->with(new Question('own-2', QuestionKind::Text, 'Two?')));
        $settings = TableSettingsType::settings($current, ['template' => 'keep', 'questions' => 'Only?']);

        self::assertNull($settings->sessionZero->question('own-2'));
        self::assertSame('Only?', $settings->sessionZero->question('own-1')?->prompt);

        $fresh = TableSettingsType::settings($current, ['template' => 'convention']);
        self::assertSame('convention', $fresh->sessionZero->key);
    }

    public function testAKeyEndingOnAHyphenAfterTheCutIsTrimmed(): void
    {
        $settings = TableSettingsType::settings(TableSettings::defaults(), ['custom_themes' => str_repeat('a', 49).' bbb']);

        self::assertSame(['table-'.str_repeat('a', 49)], array_keys($settings->customThemes));
    }

    public function testTheAnswersAreLabelledAndGroupedWithoutTranslatingTheThemes(): void
    {
        $themes = new TableThemes(BaseCatalogue::load(), ['table-clowns' => 'Clowns']);
        $form = $this->forms()->create(ThemeAnswersType::class, null, ['themes' => $themes, 'locale' => 'en']);

        self::assertSame(self::DOMAIN, $form->getConfig()->getOption('translation_domain'));
        self::assertFalse($form->get('violence')->getConfig()->getOption('translation_domain'));

        $gore = $form->get('violence')->get('gore')->getConfig();
        self::assertFalse($gore->getOption('translation_domain'));
        self::assertSame(self::DOMAIN, $gore->getOption('choice_translation_domain'));
        self::assertSame('answers.line', $this->label($gore, Boundary::Line));

        $table = $form->get('table');
        self::assertSame('answers.table_themes', $table->getConfig()->getOption('label'));
        self::assertSame(self::DOMAIN, $table->getConfig()->getOption('translation_domain'));
        self::assertFalse($table->get('table-clowns')->getConfig()->getOption('translation_domain'));

        $consent = $this->forms()->create(ThemeAnswersType::class, null, ['themes' => $themes, 'answer' => ConsentLevel::class]);
        self::assertSame('answers.red', $this->label($consent->get('violence')->get('gore')->getConfig(), ConsentLevel::Red));
    }

    public function testTheAnswerAndTheLocaleAreChecked(): void
    {
        $themes = new TableThemes(BaseCatalogue::load());

        try {
            $this->forms()->create(ThemeAnswersType::class, null, ['themes' => $themes, 'answer' => stdClass::class]);
            self::fail('An answer that is neither a boundary nor a consent level is refused.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            self::assertStringContainsString('The option "answer" with value', $invalidOptionsException->getMessage());
        }

        $this->expectException(InvalidOptionsException::class);
        $this->forms()->create(ThemeAnswersType::class, null, ['themes' => $themes, 'locale' => 5]);
    }

    /**
     * @param FormConfigInterface<mixed> $config
     */
    private function label(FormConfigInterface $config, object|string $value): string
    {
        $label = $config->getOption('choice_label');
        self::assertIsCallable($label);

        $text = $label($value);
        self::assertIsString($text);

        return $text;
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypes([new ThemeAnswersType(), new SessionZeroType(), new StarsAndWishesType(), new SafetyToolsType(), new TableSettingsType()])
            ->getFormFactory();
    }
}
