<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\SessionZero;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\SessionZero\Question;
use Tilttek\TableSafety\SessionZero\QuestionKind;

#[CoversClass(Question::class)]
final class QuestionTest extends TestCase
{
    public function testATranslatedQuestion(): void
    {
        $question = new Question('tone', QuestionKind::Choice, options: ['light' => null, 'dark' => null]);

        self::assertFalse($question->isWritten());
        self::assertFalse($question->required);
        self::assertSame(['key' => 'tone', 'kind' => 'choice', 'prompt' => null, 'options' => ['light' => null, 'dark' => null], 'required' => false], $question->toArray());
    }

    public function testAWrittenQuestion(): void
    {
        $question = new Question('snacks', QuestionKind::Choices, 'What do we snack on?', ['chips' => 'Chips', 'fruit' => 'Fruit'], true);

        self::assertTrue($question->isWritten());
        self::assertEquals($question, Question::fromArray($question->toArray()));
    }

    public function testATextAnswerIsTrimmed(): void
    {
        $question = new Question('expectations', QuestionKind::Text);

        self::assertSame('Mysteries', $question->accept('  Mysteries '));
        self::assertNull($question->accept('   '));
        self::assertNull($question->accept(null));
        self::assertSame(str_repeat('é', 2000), $question->accept(str_repeat('é', 2000)));
    }

    public function testATextAnswerIsBounded(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The answer to the question "expectations" has at most 2000 characters.');

        new Question('expectations', QuestionKind::Text)->accept(str_repeat('é', 2001));
    }

    public function testATextQuestionTakesAText(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "expectations" takes a text.');

        new Question('expectations', QuestionKind::Text)->accept(['a']);
    }

    public function testAChoiceAnswer(): void
    {
        $question = new Question('tone', QuestionKind::Choice, options: ['light' => null, 'dark' => null]);

        self::assertSame('dark', $question->accept('dark'));
        self::assertNull($question->accept(''));
        self::assertNull($question->accept(null));
    }

    public function testAChoiceAnswerIsAnOption(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "tone" has no such option.');

        new Question('tone', QuestionKind::Choice, options: ['light' => null, 'dark' => null])->accept('grim');
    }

    public function testAChoiceAnswerIsAText(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "tone" has no such option.');

        new Question('tone', QuestionKind::Choice, options: ['light' => null, 'dark' => null])->accept(['dark']);
    }

    public function testChoicesAreKeptInTheOrderOfTheOptionsAndOnce(): void
    {
        $question = new Question('pillars', QuestionKind::Choices, options: ['combat' => null, 'social' => null, 'mystery' => null, '3' => 'Three']);

        self::assertSame(['combat', 'mystery'], $question->accept(['mystery', 'combat', 'mystery']));
        self::assertSame(['3'], $question->accept(['3']));
        self::assertNull($question->accept([]));
        self::assertNull($question->accept(null));
    }

    public function testChoicesAreAList(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "pillars" takes a list of options.');

        new Question('pillars', QuestionKind::Choices, options: ['combat' => null, 'social' => null])->accept('combat');
    }

    public function testChoicesAreOptions(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "pillars" has no such option.');

        new Question('pillars', QuestionKind::Choices, options: ['combat' => null, 'social' => null])->accept(['combat', 'naps']);
    }

    public function testChoicesAreTexts(): void
    {
        $this->expectException(InvalidAnswer::class);

        new Question('pillars', QuestionKind::Choices, options: ['combat' => null, 'social' => null])->accept([1]);
    }

    /**
     * @return iterable<string, array{Question}>
     */
    public static function requiredQuestions(): iterable
    {
        yield 'a text' => [new Question('expectations', QuestionKind::Text, required: true)];
        yield 'a choice' => [new Question('tone', QuestionKind::Choice, options: ['light' => null, 'dark' => null], required: true)];
        yield 'choices' => [new Question('pillars', QuestionKind::Choices, options: ['combat' => null, 'social' => null], required: true)];
    }

    #[DataProvider('requiredQuestions')]
    public function testARequiredQuestionNeedsAnAnswer(Question $question): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The question "%s" needs an answer.', $question->key));

        $question->accept(QuestionKind::Choices === $question->kind ? [] : '');
    }

    /**
     * @return iterable<string, array{string, QuestionKind, ?string, array<string, ?string>, string}>
     */
    public static function invalidQuestions(): iterable
    {
        yield 'a key' => ['Tone', QuestionKind::Text, null, [], '"Tone" is not a question key.'];
        yield 'an empty prompt' => ['tone', QuestionKind::Text, ' ', [], 'The prompt of the question "tone" has 1 to 300 characters.'];
        yield 'a long prompt' => ['tone', QuestionKind::Text, str_repeat('é', 301), [], 'The prompt of the question "tone" has 1 to 300 characters.'];
        yield 'a text with options' => ['tone', QuestionKind::Text, null, ['a' => null], 'The question "tone" has no option.'];
        yield 'a choice of one' => ['tone', QuestionKind::Choice, null, ['a' => null], 'The question "tone" has 2 to 20 options.'];
        yield 'choices of none' => ['tone', QuestionKind::Choices, null, [], 'The question "tone" has 2 to 20 options.'];
        yield 'too many options' => ['tone', QuestionKind::Choice, null, array_fill_keys(array_map(static fn (int $i): string => 'o'.$i, range(1, 21)), null), 'The question "tone" has 2 to 20 options.'];
        yield 'an option key' => ['tone', QuestionKind::Choice, null, ['a' => null, 'B' => null], '"B" is not an option key.'];
        yield 'an empty label' => ['tone', QuestionKind::Choice, null, ['a' => null, 'b' => ''], 'The label of the option "b" has 1 to 120 characters.'];
        yield 'a long label' => ['tone', QuestionKind::Choice, null, ['a' => null, 'b' => str_repeat('é', 121)], 'The label of the option "b" has 1 to 120 characters.'];
    }

    /**
     * @param array<string, ?string> $options
     */
    #[DataProvider('invalidQuestions')]
    public function testAQuestionIsChecked(string $key, QuestionKind $kind, ?string $prompt, array $options, string $message): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Question($key, $kind, $prompt, $options);
    }

    public function testTheLongestValuesAreTaken(): void
    {
        $options = array_fill_keys(array_map(static fn (int $i): string => 'o'.$i, range(1, 20)), str_repeat('é', 120));
        $question = new Question('tone', QuestionKind::Choice, str_repeat('é', 300), $options);

        self::assertCount(20, $question->options);
        self::assertCount(2, new Question('tone', QuestionKind::Choice, null, ['a' => null, 'b' => null])->options);
    }

    public function testAStoredQuestionIsReadBack(): void
    {
        $question = Question::fromArray(['key' => 'tone', 'kind' => 'choice', 'options' => ['a' => 'A', 'b' => 3], 'required' => 'yes', 'prompt' => 4]);

        self::assertSame(['a' => 'A', 'b' => null], $question->options);
        self::assertFalse($question->required);
        self::assertNull($question->prompt);
        self::assertSame([], Question::fromArray(['key' => 'notes', 'kind' => 'text', 'options' => 'none'])->options);
    }

    public function testAStoredQuestionHasAKind(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A stored question has no kind.');

        Question::fromArray(['key' => 'tone', 'kind' => 'scale']);
    }

    public function testAStoredQuestionHasAKey(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"" is not a question key.');

        Question::fromArray(['key' => 1, 'kind' => 'text']);
    }

    public function testAStoredKindIsAText(): void
    {
        $this->expectException(InvalidAnswer::class);

        Question::fromArray(['key' => 'tone', 'kind' => 1]);
    }
}
