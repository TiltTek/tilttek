<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Answer\OrderAnswer;
use Tilttek\Quiz\Answer\TextAnswer;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Identifier;
use Tilttek\Quiz\Question\Choice;
use Tilttek\Quiz\Question\MultipleChoiceQuestion;
use Tilttek\Quiz\Question\NumericQuestion;
use Tilttek\Quiz\Question\OrderingQuestion;
use Tilttek\Quiz\Question\Rules;
use Tilttek\Quiz\Question\SingleChoiceQuestion;
use Tilttek\Quiz\Question\TypedAnswerQuestion;

#[CoversClass(SingleChoiceQuestion::class)]
#[CoversClass(MultipleChoiceQuestion::class)]
#[CoversClass(OrderingQuestion::class)]
#[CoversClass(TypedAnswerQuestion::class)]
#[CoversClass(NumericQuestion::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Rules::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(ChoiceAnswer::class)]
#[CoversClass(OrderAnswer::class)]
#[CoversClass(TextAnswer::class)]
#[CoversClass(NumberAnswer::class)]
final class QuestionsTest extends TestCase
{
    public function testASingleChoiceIsRightWithTheRightChoiceAlone(): void
    {
        $question = new SingleChoiceQuestion('q1', 'Pick B', self::choices('a', 'b', 'c'), 'b', 50, 30);

        self::assertSame('q1', $question->id());
        self::assertSame('Pick B', $question->prompt());
        self::assertSame(50, $question->points());
        self::assertSame(30, $question->timeLimit());
        self::assertSame(['a', 'b', 'c'], array_map(strval(...), array_keys($question->choices)));
        self::assertTrue($question->grade(ChoiceAnswer::of('b'))->isRight());
        self::assertTrue($question->grade(ChoiceAnswer::of('b', 'b'))->isRight());
        self::assertTrue($question->grade(ChoiceAnswer::of('a'))->isWrong());
        self::assertTrue($question->grade(ChoiceAnswer::of('b', 'a'))->isWrong());
        self::assertTrue($question->grade(ChoiceAnswer::of())->isWrong());
    }

    public function testDefaultsAreAHundredPointsAndNoTimeLimit(): void
    {
        $question = new SingleChoiceQuestion('q1', 'Pick', self::choices('a', 'b'), 'a');

        self::assertSame(100, $question->points());
        self::assertNull($question->timeLimit());
    }

    public function testTrueOrFalseHasTwoChoices(): void
    {
        $question = SingleChoiceQuestion::trueOrFalse('q2', 'A d6 has six faces.', true, trueLabel: 'Vrai', falseLabel: 'Faux');

        self::assertSame('true', $question->correct);
        self::assertSame('Vrai', $question->choices['true']->label);
        self::assertSame('Faux', $question->choices['false']->label);
        self::assertTrue($question->grade(ChoiceAnswer::of('true'))->isRight());
        self::assertSame('false', SingleChoiceQuestion::trueOrFalse('q3', 'A d6 has eight faces.', false)->correct);
        self::assertSame('True', SingleChoiceQuestion::trueOrFalse('q3', 'Q', false, 10, 15)->choices['true']->label);
        self::assertSame(15, SingleChoiceQuestion::trueOrFalse('q3', 'Q', false, 10, 15)->timeLimit());
        self::assertSame(10, SingleChoiceQuestion::trueOrFalse('q3', 'Q', false, 10, 15)->points());
    }

    public function testTheRightChoiceIsOneOfTheChoices(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The right choice of the question q1, z, is not one of its choices.');
        new SingleChoiceQuestion('q1', 'Pick', self::choices('a', 'b'), 'z');
    }

    public function testAMultipleChoiceWithoutPartialCreditWantsTheExactSet(): void
    {
        $question = new MultipleChoiceQuestion('m', 'Pick the odd ones', self::choices('1', '2', '3', '4'), ['1', '3', '3']);

        self::assertSame(['1', '3'], $question->correct);
        self::assertFalse($question->partialCredit);
        self::assertTrue($question->grade(ChoiceAnswer::of('3', '1'))->isRight());
        self::assertTrue($question->grade(ChoiceAnswer::of('1'))->isWrong());
        self::assertTrue($question->grade(ChoiceAnswer::of('1', '3', '2'))->isWrong());
    }

    public function testAMultipleChoiceWithPartialCreditTakesAShareBackForEachWrongChoice(): void
    {
        $question = new MultipleChoiceQuestion('m', 'Pick the odd ones', self::choices('1', '2', '3', '4', '5'), ['1', '3', '5'], partialCredit: true, points: 30, timeLimit: 20);

        self::assertSame(30, $question->points());
        self::assertSame(20, $question->timeLimit());
        self::assertSame('m', $question->id());
        self::assertSame('Pick the odd ones', $question->prompt());
        self::assertSame(1000, $question->grade(ChoiceAnswer::of('1', '3', '5'))->permille);
        self::assertSame(666, $question->grade(ChoiceAnswer::of('1', '3'))->permille);
        self::assertSame(333, $question->grade(ChoiceAnswer::of('1', '3', '2'))->permille);
        self::assertSame(333, $question->grade(ChoiceAnswer::of('1', '2', '3', '4', '5'))->permille);
        self::assertSame(0, $question->grade(ChoiceAnswer::of('1', '2', '4'))->permille);
        self::assertSame(0, $question->grade(ChoiceAnswer::of('2'))->permille);
    }

    public function testAMultipleChoiceHasARightChoiceAmongItsChoices(): void
    {
        try {
            new MultipleChoiceQuestion('m', 'Pick', self::choices('a', 'b'), []);
            self::fail('No right choice was accepted.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertSame('The question m has at least one right choice.', $invalidQuiz->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The right choice z of the question m is not one of its choices.');
        new MultipleChoiceQuestion('m', 'Pick', self::choices('a', 'b'), ['a', 'z']);
    }

    public function testAnOrderingIsRightWithEveryItemInItsPlace(): void
    {
        $question = new OrderingQuestion('o', 'Order the editions', self::choices('first', 'second', 'third'), points: 60, timeLimit: 40);

        self::assertSame('o', $question->id());
        self::assertSame('Order the editions', $question->prompt());
        self::assertSame(60, $question->points());
        self::assertSame(40, $question->timeLimit());
        self::assertFalse($question->partialCredit);
        self::assertTrue($question->grade(OrderAnswer::of('first', 'second', 'third'))->isRight());
        self::assertTrue($question->grade(OrderAnswer::of('first', 'third', 'second'))->isWrong());
        self::assertTrue($question->grade(OrderAnswer::of('first', 'second'))->isWrong());
    }

    public function testAnOrderingWithPartialCreditCountsTheItemsInPlace(): void
    {
        $question = new OrderingQuestion('o', 'Order', self::choices('1', '2', '3', '4'), partialCredit: true);

        self::assertSame(500, $question->grade(OrderAnswer::of('1', '2', '4', '3'))->permille);
        self::assertSame(1000, $question->grade(OrderAnswer::of('1', '2', '3', '4'))->permille);
        self::assertSame(0, $question->grade(OrderAnswer::of('4', '3', '2', '1'))->permille);
        self::assertSame(250, $question->grade(OrderAnswer::of('1'))->permille);
    }

    public function testATypedAnswerIsComparedFolded(): void
    {
        $question = new TypedAnswerQuestion('t', 'Who wrote it?', ['Gary Gygax', 'Gygax'], points: 10, timeLimit: 60);

        self::assertSame('t', $question->id());
        self::assertSame('Who wrote it?', $question->prompt());
        self::assertSame(10, $question->points());
        self::assertSame(60, $question->timeLimit());
        self::assertSame(['Gary Gygax', 'Gygax'], $question->accepted);
        self::assertTrue($question->grade(new TextAnswer('  gary    GYGAX '))->isRight());
        self::assertTrue($question->grade(new TextAnswer('gygax'))->isRight());
        self::assertTrue($question->grade(new TextAnswer('Arneson'))->isWrong());
        self::assertSame('ecole elementaire', $question->fold(" École\tÉlémentaire "));
    }

    public function testATypedAnswerMayKeepItsAccents(): void
    {
        $question = new TypedAnswerQuestion('t', 'Le mot ?', ['Élève'], keepAccents: true);

        self::assertTrue($question->keepAccents);
        self::assertTrue($question->grade(new TextAnswer('élève'))->isRight());
        self::assertTrue($question->grade(new TextAnswer('eleve'))->isWrong());
        self::assertSame('élève', $question->fold("E\u{0301}le\u{0300}ve"));
        self::assertTrue(new TypedAnswerQuestion('t', 'Le mot ?', ['Élève'])->grade(new TextAnswer('eleve'))->isRight());
    }

    public function testATypedAnswerAcceptsOneToTwentyAnswers(): void
    {
        try {
            new TypedAnswerQuestion('t', 'Q', []);
            self::fail('No accepted answer was taken.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertSame('The question t accepts 1 to 20 answers; it accepts 0.', $invalidQuiz->getMessage());
        }

        self::assertCount(20, new TypedAnswerQuestion('t', 'Q', array_map(strval(...), range(1, 20)))->accepted);

        try {
            new TypedAnswerQuestion('t', 'Q', array_map(strval(...), range(1, 21)));
            self::fail('Twenty-one accepted answers were taken.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertSame('The question t accepts 1 to 20 answers; it accepts 21.', $invalidQuiz->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('An accepted answer of the question t is empty.');
        new TypedAnswerQuestion('t', 'Q', ['ok', '   ']);
    }

    public function testANumericAnswerIsRightWithinItsTolerance(): void
    {
        $question = new NumericQuestion('n', 'Year?', 1974, 1, 20, 15);

        self::assertSame('n', $question->id());
        self::assertSame('Year?', $question->prompt());
        self::assertSame(20, $question->points());
        self::assertSame(15, $question->timeLimit());
        self::assertTrue($question->grade(new NumberAnswer(1973))->isRight());
        self::assertTrue($question->grade(new NumberAnswer(1975))->isRight());
        self::assertTrue($question->grade(new NumberAnswer(1972))->isWrong());
        self::assertTrue($question->grade(new NumberAnswer(1976))->isWrong());
        self::assertTrue(new NumericQuestion('n', 'Year?', 1974)->grade(new NumberAnswer(1975))->isWrong());
    }

    public function testANumericToleranceAndAnswerAreBounded(): void
    {
        self::assertSame(NumericQuestion::MAX_TOLERANCE, new NumericQuestion('n', 'Q', 0, NumericQuestion::MAX_TOLERANCE)->tolerance);
        self::assertSame(\PHP_INT_MAX - NumericQuestion::MAX_TOLERANCE, new NumericQuestion('n', 'Q', \PHP_INT_MAX - NumericQuestion::MAX_TOLERANCE)->answer);

        foreach ([[0, -1], [0, NumericQuestion::MAX_TOLERANCE + 1], [\PHP_INT_MAX - NumericQuestion::MAX_TOLERANCE + 1, 0], [\PHP_INT_MIN, 0]] as [$answer, $tolerance]) {
            try {
                new NumericQuestion('n', 'Q', $answer, $tolerance);
                self::fail('An unbounded numeric question was accepted.');
            } catch (InvalidQuiz) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAQuestionRefusesAnAnswerOfAnotherKind(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The question n takes a NumberAnswer, not a TextAnswer.');
        new NumericQuestion('n', 'Q', 1)->grade(new TextAnswer('1'));
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function refusedQuestions(): iterable
    {
        yield 'empty identifier' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('', 'Q', self::choices('a', 'b'), 'a'), 'The identifier of a question is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.'];
        yield 'identifier with a space' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q 1', 'Q', self::choices('a', 'b'), 'a'), 'The identifier of a question'];
        yield 'identifier too long' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion(str_repeat('q', 129), 'Q', self::choices('a', 'b'), 'a'), 'The identifier of a question'];
        yield 'blank prompt' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', '  ', self::choices('a', 'b'), 'a'), 'The prompt of the question q is 1 to 1000 bytes of text.'];
        yield 'long prompt' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', str_repeat('x', 1001), self::choices('a', 'b'), 'a'), 'The prompt of the question q is 1 to 1000 bytes of text.'];
        yield 'negative points' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices('a', 'b'), 'a', -1), 'The question q is worth 0 to 10000 points; -1 is not allowed.'];
        yield 'too many points' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices('a', 'b'), 'a', 10_001), 'The question q is worth 0 to 10000 points; 10001 is not allowed.'];
        yield 'too short' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices('a', 'b'), 'a', 1, 4), 'The time limit of the question q is 5 to 600 seconds; 4 is not allowed.'];
        yield 'too long' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices('a', 'b'), 'a', 1, 601), 'The time limit of the question q is 5 to 600 seconds; 601 is not allowed.'];
        yield 'one choice' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices('a'), 'a'), 'The question q offers 2 to 12 choices; it offers 1.'];
        yield 'thirteen choices' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices(...array_map(strval(...), range(1, 13))), '1'), 'The question q offers 2 to 12 choices; it offers 13.'];
        yield 'twice the same choice' => [static fn (): SingleChoiceQuestion => new SingleChoiceQuestion('q', 'Q', self::choices('a', 'a'), 'a'), 'Two choices of the question q are a.'];
        yield 'blank label' => [static fn (): Choice => new Choice('a', ' '), 'The label of the choice a is 1 to 200 bytes of text.'];
        yield 'long label' => [static fn (): Choice => new Choice('a', str_repeat('x', 201)), 'The label of the choice a is 1 to 200 bytes of text.'];
        yield 'too many picks' => [static fn (): ChoiceAnswer => new ChoiceAnswer(array_map(strval(...), range(1, 33))), 'An answer picks at most 32 choices; this one picks 33.'];
        yield 'bad pick' => [static fn (): ChoiceAnswer => ChoiceAnswer::of('a b'), 'The identifier of a choice'];
        yield 'too many items' => [static fn (): OrderAnswer => new OrderAnswer(array_map(strval(...), range(1, 33))), 'An answer orders at most 32 items; this one holds 33.'];
        yield 'bad item' => [static fn (): OrderAnswer => OrderAnswer::of('a b'), 'The identifier of a item'];
        yield 'item twice' => [static fn (): OrderAnswer => OrderAnswer::of('a', 'a'), 'An answer puts each item in one place only.'];
        yield 'long text' => [static fn (): TextAnswer => new TextAnswer(str_repeat('x', 201)), 'A typed answer is at most 200 bytes; this one is 201.'];
        yield 'not text' => [static fn (): TextAnswer => new TextAnswer("\xff"), 'A typed answer is text in UTF-8.'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('refusedQuestions')]
    public function testAnOutOfBoundsValueIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains($message);
        $build();
    }

    public function testTheBoundsThemselvesAreAccepted(): void
    {
        self::assertSame(10_000, new SingleChoiceQuestion('q', str_repeat('x', 1000), self::choices('a', 'b'), 'a', 10_000, 600)->points());
        self::assertSame(5, new SingleChoiceQuestion(str_repeat('q', 128), 'Q', self::choices(...array_map(strval(...), range(1, 12))), '1', 0, 5)->timeLimit());
        self::assertSame(str_repeat('x', 200), new Choice('a', str_repeat('x', 200))->label);
        self::assertCount(32, new ChoiceAnswer(array_map(strval(...), range(1, 32)))->choices);
        self::assertCount(32, new OrderAnswer(array_map(strval(...), range(1, 32)))->items);
        self::assertSame(200, \strlen(new TextAnswer(str_repeat('x', 200))->text));
        self::assertSame('a1:b_c.d@e-f', ChoiceAnswer::of('a1:b_c.d@e-f')->choices[0]);
        self::assertSame(-3, new NumberAnswer(-3)->number);
    }

    /**
     * @return list<Choice>
     */
    private static function choices(string ...$ids): array
    {
        return array_map(static fn (string $id): Choice => new Choice($id, 'Choice '.$id), array_values($ids));
    }

    public function testEveryKindIsWorthAHundredPointsByDefaultAndChecksItsRules(): void
    {
        $choices = self::choices('a', 'b');

        self::assertSame(100, new MultipleChoiceQuestion('m', 'Pick', $choices, ['a'])->points());
        self::assertSame(100, new OrderingQuestion('o', 'Order', $choices)->points());
        self::assertSame(100, new TypedAnswerQuestion('t', 'Type', ['x'])->points());
        self::assertSame(100, new NumericQuestion('n', 'Count', 3)->points());
        self::assertSame(100, SingleChoiceQuestion::trueOrFalse('tf', 'True?', true)->points());

        foreach ([
            static fn (): MultipleChoiceQuestion => new MultipleChoiceQuestion('q', ' ', $choices, ['a']),
            static fn (): OrderingQuestion => new OrderingQuestion('q', ' ', $choices),
            static fn (): TypedAnswerQuestion => new TypedAnswerQuestion('q', ' ', ['x']),
            static fn (): NumericQuestion => new NumericQuestion('q', ' ', 3),
        ] as $build) {
            try {
                $build();
                self::fail('A blank prompt was accepted.');
            } catch (InvalidQuiz $invalidQuiz) {
                self::assertSame('The prompt of the question q is 1 to 1000 bytes of text.', $invalidQuiz->getMessage());
            }
        }
    }

    public function testAChoiceHasAnIdentifier(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a choice is 1 to 128 letters');
        new Choice('a b', 'A choice');
    }

    public function testAnswersKeepTheirPicksAndItemsInOrder(): void
    {
        self::assertSame(['a', 'b'], ChoiceAnswer::of('a', 'a', 'b')->choices);
        self::assertSame(['a', 'b'], ChoiceAnswer::of(...['first' => 'a', 'second' => 'b'])->choices);
        self::assertSame(['b', 'a'], OrderAnswer::of(...['first' => 'b', 'second' => 'a'])->items);
    }
}
