<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\SessionZero;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\SessionZero\Question;
use Tilttek\TableSafety\SessionZero\QuestionKind;
use Tilttek\TableSafety\SessionZero\Template;
use Tilttek\TableSafety\SessionZero\Templates;

#[CoversClass(Template::class)]
#[CoversClass(Templates::class)]
#[CoversClass(Answers::class)]
final class TemplateTest extends TestCase
{
    public function testTheBuiltInTemplates(): void
    {
        self::assertSame(['one-shot', 'campaign', 'convention', 'online'], Templates::keys());
        self::assertSame(Templates::CAMPAIGN, Templates::DEFAULT);
        self::assertTrue(Templates::has('online'));
        self::assertFalse(Templates::has('solo'));
        self::assertSame(Templates::keys(), array_keys(Templates::all()));

        $keys = static fn (Template $template): array => array_map(static fn (Question $question): string => $question->key, $template->questions());

        self::assertSame(['tone', 'pillars', 'themes-welcome', 'player-conflict', 'character-death', 'comfort'], $keys(Templates::get('one-shot')));
        self::assertSame(['tone', 'pillars', 'expectations', 'themes-welcome', 'player-conflict', 'character-death', 'frequency', 'schedule', 'table-rules', 'comfort'], $keys(Templates::get('campaign')));
        self::assertSame(['experience', 'tone', 'player-conflict', 'comfort'], $keys(Templates::get('convention')));
        self::assertSame(['tone', 'pillars', 'themes-welcome', 'camera', 'recording', 'table-rules', 'comfort'], $keys(Templates::get('online')));

        foreach (Templates::all() as $key => $template) {
            self::assertSame($key, $template->key);

            foreach ($template->questions() as $question) {
                self::assertFalse($question->isWritten());
            }
        }
    }

    public function testTheBuiltInQuestions(): void
    {
        $online = Templates::get('online');
        $campaign = Templates::get('campaign');
        $convention = Templates::get('convention');

        self::assertSame(['light' => null, 'mixed' => null, 'serious' => null, 'dark' => null], $this->question($online, 'tone')->options);
        self::assertTrue($this->question($online, 'tone')->required);
        self::assertSame(QuestionKind::Choices, $this->question($online, 'pillars')->kind);
        self::assertSame(['combat', 'exploration', 'social', 'mystery', 'drama'], array_keys($this->question($online, 'pillars')->options));
        self::assertFalse($this->question($online, 'pillars')->required);
        self::assertSame(['video', 'voice', 'text'], array_keys($this->question($online, 'camera')->options));
        self::assertSame(['never', 'ask-first', 'allowed'], array_keys($this->question($online, 'recording')->options));
        self::assertTrue($this->question($online, 'recording')->required);
        self::assertSame(['never', 'with-consent', 'welcome'], array_keys($this->question($campaign, 'player-conflict')->options));
        self::assertSame(['never', 'rare', 'possible'], array_keys($this->question($campaign, 'character-death')->options));
        self::assertSame(['weekly', 'fortnightly', 'monthly'], array_keys($this->question($campaign, 'frequency')->options));
        self::assertSame(['new', 'some', 'seasoned'], array_keys($this->question($convention, 'experience')->options));
        self::assertSame(QuestionKind::Choice, $this->question($convention, 'experience')->kind);
        self::assertSame(QuestionKind::Text, $this->question($campaign, 'schedule')->kind);
        self::assertFalse($this->question($campaign, 'schedule')->required);
        self::assertNull($campaign->question('camera'));
    }

    public function testAnUnknownBuiltInTemplate(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('There is no built-in template "solo".');

        Templates::get('solo');
    }

    public function testAGameMasterChangesTheQuestions(): void
    {
        $snacks = new Question('snacks', QuestionKind::Text, 'Snacks?');
        $template = Templates::get('convention')->with($snacks);

        self::assertSame($snacks, $template->questions()[4]);
        self::assertCount(5, $template->questions());

        $tone = new Question('tone', QuestionKind::Text, 'Tone, in your words');
        $replaced = $template->with($tone);
        self::assertSame($tone, $replaced->questions()[1]);
        self::assertCount(5, $replaced->questions());

        $without = $replaced->without('experience');
        self::assertSame(['tone', 'player-conflict', 'comfort', 'snacks'], array_map(static fn (Question $question): string => $question->key, $without->questions()));
        self::assertSame('convention', $without->key);
        self::assertCount(5, $replaced->without('nothing')->questions());
        self::assertCount(4, Templates::get('convention')->questions());
    }

    public function testTheLastQuestionStays(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A session zero has 1 to 50 questions.');

        new Template('mine', [new Question('a', QuestionKind::Text)])->without('a');
    }

    public function testTheQuestionsAreBounded(): void
    {
        $questions = array_map(static fn (int $i): Question => new Question('q'.$i, QuestionKind::Text), range(1, 50));

        self::assertCount(50, new Template('mine', $questions)->questions());

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A session zero has 1 to 50 questions.');

        new Template('mine', [...$questions, new Question('q51', QuestionKind::Text)]);
    }

    public function testAQuestionIsGivenOnce(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "a" is given twice.');

        new Template('mine', [new Question('a', QuestionKind::Text), new Question('a', QuestionKind::Text)]);
    }

    public function testATemplateHasAKey(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"My table" is not a template key.');

        new Template('My table', [new Question('a', QuestionKind::Text)]);
    }

    public function testItAcceptsAnswers(): void
    {
        $answers = Templates::get('online')->answer(['tone' => 'dark', 'pillars' => ['social', 'combat'], 'themes-welcome' => '  ', 'recording' => 'never', 'comfort' => 'Captions']);

        self::assertSame(['tone' => 'dark', 'pillars' => ['combat', 'social'], 'recording' => 'never', 'comfort' => 'Captions'], $answers->toArray());
        self::assertSame('dark', $answers->of('tone'));
        self::assertNull($answers->of('camera'));
        self::assertFalse($answers->isEmpty());
    }

    public function testItRefusesAnUnknownQuestion(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The session zero has no question "snacks".');

        Templates::get('online')->answer(['tone' => 'dark', 'recording' => 'never', 'snacks' => 'Chips']);
    }

    public function testItRefusesAMissingRequiredAnswer(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "recording" needs an answer.');

        Templates::get('online')->answer(['tone' => 'dark']);
    }

    public function testItIsStoredAsAnArray(): void
    {
        $template = Templates::get('convention')->with(new Question('snacks', QuestionKind::Choice, 'Snacks?', ['chips' => 'Chips', 'fruit' => null]));
        $stored = $template->toArray();

        self::assertSame('convention', $stored['key']);
        self::assertCount(5, $stored['questions']);
        self::assertSame('snacks', $stored['questions'][4]['key']);
        self::assertEquals($template, Template::fromArray($stored));
    }

    public function testAStoredTemplateIsChecked(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A session zero has 1 to 50 questions.');

        Template::fromArray(['key' => 'mine', 'questions' => 'none']);
    }

    public function testAStoredTemplateHasAKey(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"" is not a template key.');

        Template::fromArray(['questions' => [['key' => 'a', 'kind' => 'text']]]);
    }

    public function testAStoredQuestionIsAnArray(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A stored question has no kind.');

        Template::fromArray(['key' => 'mine', 'questions' => ['a']]);
    }

    public function testAnswersAreReadBack(): void
    {
        $answers = Answers::fromArray(['tone' => 'dark', 'pillars' => ['combat', 'social'], 'empty' => []]);

        self::assertSame(['tone' => 'dark', 'pillars' => ['combat', 'social'], 'empty' => []], $answers->toArray());
        self::assertTrue(Answers::none()->isEmpty());
        self::assertTrue(Answers::fromArray([])->isEmpty());
    }

    public function testAStoredAnswerIsATextOrAListOfTexts(): void
    {
        foreach ([1, ['a' => 'b'], ['a', 2], null] as $answer) {
            try {
                Answers::fromArray(['tone' => $answer]);
                self::fail('The answer is refused.');
            } catch (InvalidAnswer $invalidAnswer) {
                self::assertSame('The stored answer to "tone" is neither a text nor a list of options.', $invalidAnswer->getMessage());
            }
        }
    }

    private function question(Template $template, string $key): Question
    {
        $question = $template->question($key);
        self::assertInstanceOf(Question::class, $question);

        return $question;
    }
}
