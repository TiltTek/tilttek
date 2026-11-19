<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use Phil\ContentWarnings\BaseCatalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Feedback\StarsAndWishes;
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
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswers;
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswersType;

#[CoversClass(ThemeAnswersType::class)]
#[CoversClass(ThemeAnswers::class)]
#[CoversClass(SessionZeroType::class)]
#[CoversClass(StarsAndWishesType::class)]
#[CoversClass(SafetyToolsType::class)]
#[CoversClass(TableSettingsType::class)]
final class FormsTest extends TestCase
{
    public function testTheLinesAndVeilsAreGroupedByCategory(): void
    {
        $themes = new TableThemes(BaseCatalogue::load(), ['table-clowns' => 'Clowns']);
        $form = $this->forms()->createNamed('lines_and_veils', ThemeAnswersType::class, ThemeAnswers::grouped($themes, ['gore' => Boundary::Line, 'table-clowns' => Boundary::Veil, 'unknown' => Boundary::Line]), ['themes' => $themes, 'locale' => 'fr']);

        self::assertTrue($form->has('violence'));
        self::assertTrue($form->get('violence')->has('gore'));
        self::assertSame('Violence', $form->get('violence')->getConfig()->getOption('label'));
        self::assertSame('Gore', $form->get('violence')->get('gore')->getConfig()->getOption('label'));
        self::assertSame('answers.open', $form->get('violence')->get('gore')->getConfig()->getOption('placeholder'));
        self::assertTrue($form->get('violence')->get('gore')->getConfig()->getOption('expanded'));
        self::assertFalse($form->get('violence')->get('gore')->getConfig()->getOption('required'));
        self::assertSame(Boundary::Line, $form->get('violence')->get('gore')->getData());
        self::assertSame(Boundary::Veil, $form->get('table')->get('table-clowns')->getData());
        self::assertSame('Clowns', $form->get('table')->get('table-clowns')->getConfig()->getOption('label'));

        $form->submit(['violence' => ['gore' => 'veil', 'torture' => 'line'], 'table' => ['table-clowns' => '']]);

        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertIsArray($data);
        self::assertSame(['gore' => 'veil', 'torture' => 'line'], ThemeAnswers::linesAndVeils($data)->toArray());
    }

    public function testTheChecklistTakesLevels(): void
    {
        $themes = new TableThemes(BaseCatalogue::load());
        $form = $this->forms()->createNamed('consent', ThemeAnswersType::class, [], ['themes' => $themes, 'answer' => ConsentLevel::class]);

        self::assertFalse($form->has('table'));
        self::assertSame('answers.unanswered', $form->get('violence')->get('gore')->getConfig()->getOption('placeholder'));

        $form->submit(['violence' => ['gore' => 'red', 'torture' => 'green']]);
        $data = $form->getData();
        self::assertIsArray($data);

        self::assertSame(['gore' => 'red', 'torture' => 'green'], ThemeAnswers::checklist($data)->toArray());
        self::assertSame([], ThemeAnswers::checklist(['violence' => 'none', 'other' => ['gore' => Boundary::Line]])->toArray());
        self::assertSame([], ThemeAnswers::linesAndVeils(['other' => ['gore' => ConsentLevel::Red]])->toArray());
    }

    public function testTheSessionZeroFollowsItsTemplate(): void
    {
        $template = Templates::get('online')->with(new Question('snacks', QuestionKind::Choice, 'Snacks?', ['chips' => 'Chips', 'fruit' => 'Fruit']));
        $form = $this->forms()->createNamed('session_zero', SessionZeroType::class, ['tone' => 'dark'], ['template' => $template]);

        $tone = $form->get('tone')->getConfig();
        self::assertInstanceOf(ChoiceType::class, $tone->getType()->getInnerType());
        self::assertSame('session_zero.questions.tone', $tone->getOption('label'));
        self::assertSame('tilttek_table_safety', $tone->getOption('translation_domain'));
        self::assertTrue($tone->getOption('required'));
        self::assertFalse($tone->getOption('multiple'));
        self::assertSame(['session_zero.options.tone.light' => 'light', 'session_zero.options.tone.mixed' => 'mixed', 'session_zero.options.tone.serious' => 'serious', 'session_zero.options.tone.dark' => 'dark'], $tone->getOption('choices'));
        self::assertTrue($form->get('pillars')->getConfig()->getOption('multiple'));
        self::assertInstanceOf(TextareaType::class, $form->get('comfort')->getConfig()->getType()->getInnerType());
        self::assertSame(['maxlength' => 2000, 'rows' => 3], $form->get('comfort')->getConfig()->getOption('attr'));
        self::assertSame('Snacks?', $form->get('snacks')->getConfig()->getOption('label'));
        self::assertFalse($form->get('snacks')->getConfig()->getOption('translation_domain'));
        self::assertFalse($form->get('snacks')->getConfig()->getOption('choice_translation_domain'));
        self::assertSame(['Chips' => 'chips', 'Fruit' => 'fruit'], $form->get('snacks')->getConfig()->getOption('choices'));

        $form->submit(['tone' => 'light', 'pillars' => ['social'], 'recording' => 'never', 'comfort' => 'Breaks', 'snacks' => 'fruit']);

        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertIsArray($data);
        self::assertSame(['tone' => 'light', 'pillars' => ['social'], 'recording' => 'never', 'comfort' => 'Breaks', 'snacks' => 'fruit'], $template->answer(array_filter($data, static fn (mixed $answer): bool => null !== $answer))->toArray());
    }

    public function testStarsAndWishesAreOnePerLine(): void
    {
        self::assertSame(['a', 'b c'], StarsAndWishesType::lines(" a \r\n\n b c \n"));
        self::assertSame([], StarsAndWishesType::lines(null));
        self::assertSame([], StarsAndWishesType::lines(''));
        self::assertEquals(new StarsAndWishes(['a'], ['b', 'c']), StarsAndWishesType::feedback(['stars' => 'a', 'wishes' => "b\nc"]));
        self::assertEquals(new StarsAndWishes(), StarsAndWishesType::feedback([]));
        self::assertSame(['stars' => "a\nb", 'wishes' => ''], StarsAndWishesType::data(new StarsAndWishes(['a', 'b'])));

        $form = $this->forms()->create(StarsAndWishesType::class);
        self::assertSame('feedback.stars', $form->get('stars')->getConfig()->getOption('label'));
        self::assertSame(['maxlength' => 5010, 'rows' => 4], $form->get('wishes')->getConfig()->getOption('attr'));
        self::assertFalse($form->get('wishes')->getConfig()->getOption('required'));
    }

    public function testTheToolsOfAnAnnouncementAreNeverRequired(): void
    {
        $form = $this->forms()->create(SafetyToolsType::class);

        self::assertFalse($form->getConfig()->getOption('required'));
        self::assertTrue($form->getConfig()->getOption('multiple'));
        self::assertSame('tools.recommended', $form->getConfig()->getOption('help'));

        $form->submit(['x-card', 'open-door']);
        self::assertSame([SafetyTool::XCard, SafetyTool::OpenDoor], $form->getData());
    }

    public function testTheSettingsOfATable(): void
    {
        $current = TableSettings::defaults()->withSessionZero(Templates::get('convention')->with(new Question('own-1', QuestionKind::Text, 'Old?')));
        $data = TableSettingsType::data($current->withCustomThemes(['table-clowns' => 'Clowns', 'table-deep-water' => 'Deep water']));

        self::assertSame(SafetyTool::cases(), $data['tools']);
        self::assertFalse($data['named_signals']);
        self::assertSame(Audience::Table, $data['signals_seen_by']);
        self::assertSame("Clowns\nDeep water", $data['custom_themes']);
        self::assertSame(TableSettingsType::KEEP, $data['template']);
        self::assertSame('Old?', $data['questions']);

        $form = $this->forms()->create(TableSettingsType::class, $data);
        $form->submit(['tools' => ['x-card', 'check-in'], 'named_signals' => '1', 'signals_seen_by' => 'game-master', 'custom_themes' => "Clowns\nÉléphants roses\n!!!", 'template' => 'keep', 'questions' => "Snacks?\nMusic?"]);
        self::assertTrue($form->isValid());
        $submitted = $form->getData();
        self::assertIsArray($submitted);
        $settings = TableSettingsType::settings($current, $submitted);

        self::assertSame([SafetyTool::XCard, SafetyTool::CheckIn], $settings->tools);
        self::assertTrue($settings->namedSignals);
        self::assertSame(Audience::GameMaster, $settings->signalsSeenBy);
        self::assertSame(['table-clowns' => 'Clowns', 'table-elephants-roses' => 'Éléphants roses', 'table-'.hash('xxh32', '!!!') => '!!!'], $settings->customThemes);
        self::assertSame(['experience', 'tone', 'player-conflict', 'comfort', 'own-1', 'own-2'], array_map(static fn (Question $question): string => $question->key, $settings->sessionZero->questions()));
        self::assertSame('Music?', $settings->sessionZero->question('own-2')?->prompt);
        self::assertSame('Snacks?', $settings->sessionZero->question('own-1')?->prompt);
    }

    public function testABuiltInTemplateReplacesTheQuestions(): void
    {
        $settings = TableSettingsType::settings(TableSettings::defaults(), ['template' => 'one-shot', 'tools' => 'none', 'signals_seen_by' => 'table', 'named_signals' => 'yes', 'custom_themes' => str_repeat('a', 80)]);

        self::assertSame('one-shot', $settings->sessionZero->key);
        self::assertSame([], $settings->tools);
        self::assertFalse($settings->namedSignals);
        self::assertSame(Audience::Table, $settings->signalsSeenBy);
        self::assertSame(['table-'.str_repeat('a', 50)], array_keys($settings->customThemes));
        self::assertSame('campaign', TableSettingsType::settings(TableSettings::defaults(), ['template' => 'solo'])->sessionZero->key);
        self::assertSame('', TableSettingsType::data(TableSettings::defaults())['questions']);
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypes([new ThemeAnswersType(), new SessionZeroType(), new StarsAndWishesType(), new SafetyToolsType(), new TableSettingsType()])
            ->getFormFactory();
    }
}
