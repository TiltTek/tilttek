<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\GuessOutcome;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\Scenario;
use Tilttek\PuzzleBuilder\Unlock;

#[CoversClass(Scenario::class)]
#[CoversClass(InvalidScenario::class)]
#[CoversClass(PuzzleRefused::class)]
final class ScenarioTest extends TestCase
{
    public function testAScenarioHoldsItsPuzzlesAndTheirOrder(): void
    {
        $scenario = new Scenario('manor', [
            self::puzzle('vault', Unlock::after(['clock', 'painting'])),
            self::puzzle('painting', Unlock::after(['clock'])),
            self::puzzle('clock'),
            self::puzzle('letter', Unlock::manual()),
        ], 'vault');

        self::assertSame('manor', $scenario->id);
        self::assertSame('vault', $scenario->finale);
        self::assertSame(['vault', 'painting', 'clock', 'letter'], array_map(static fn (Puzzle $puzzle): string => $puzzle->id, $scenario->puzzles()));
        self::assertSame(['clock', 'letter', 'painting', 'vault'], $scenario->order());
        self::assertTrue($scenario->has('clock'));
        self::assertFalse($scenario->has('door'));
        self::assertSame('clock', $scenario->puzzle('clock')->id);
    }

    public function testAnUnknownPuzzleIsRefused(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The scenario holds no puzzle "door".');

        new Scenario('manor', [self::puzzle('clock')])->puzzle('door');
    }

    public function testAScenarioEndsWithItsFinale(): void
    {
        $scenario = new Scenario('manor', [self::puzzle('clock'), self::puzzle('vault', Unlock::after(['clock']))], 'vault');

        self::assertFalse($scenario->completedBy(['clock' => true]));
        self::assertTrue($scenario->completedBy(['vault' => true]));
    }

    public function testWithoutFinaleAScenarioEndsWithEveryPuzzle(): void
    {
        $scenario = new Scenario('manor', [self::puzzle('clock'), self::puzzle('vault')]);

        self::assertNull($scenario->finale);
        self::assertFalse($scenario->completedBy(['clock' => true, 'other' => true]));
        self::assertTrue($scenario->completedBy(['vault' => true, 'clock' => true]));
    }

    public function testTwoHundredPuzzlesAreTaken(): void
    {
        $puzzles = array_map(static fn (int $index): Puzzle => self::puzzle('p'.$index), range(1, 200));

        self::assertCount(200, new Scenario('big', $puzzles)->order());
    }

    public function testTwoHundredAndOnePuzzlesAreRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A scenario holds 1 to 200 puzzles.');

        new Scenario('big', array_map(static fn (int $index): Puzzle => self::puzzle('p'.$index), range(1, 201)));
    }

    public function testAScenarioWithoutPuzzleIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A scenario holds 1 to 200 puzzles.');

        new Scenario('empty', []);
    }

    public function testABadIdentifierIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a scenario holds 1 to 64 characters among a-z, 0-9, and inner - and _, from a letter; "Manor" does not.');

        new Scenario('Manor', [self::puzzle('clock')]);
    }

    public function testAPuzzleGivenTwiceIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "clock" is given twice.');

        new Scenario('manor', [self::puzzle('clock'), self::puzzle('clock')]);
    }

    public function testAnUnknownRequiredPuzzleIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "vault" requires "door", which the scenario does not hold.');

        new Scenario('manor', [self::puzzle('clock'), self::puzzle('vault', Unlock::after(['clock', 'door']))]);
    }

    public function testAnUnknownFinaleIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The finale "door" is not a puzzle of the scenario.');

        new Scenario('manor', [self::puzzle('clock')], 'door');
    }

    public function testACycleIsRefusedWithItsPath(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles b → c → d → b require one another.');

        new Scenario('loop', [
            self::puzzle('a'),
            self::puzzle('b', Unlock::after(['a', 'c'], 1)),
            self::puzzle('c', Unlock::after(['d'])),
            self::puzzle('d', Unlock::after(['b'])),
        ]);
    }

    public function testACycleIsShownFromWhereItCloses(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles b → c → b require one another.');

        new Scenario('loop', [
            self::puzzle('a', Unlock::after(['b'])),
            self::puzzle('b', Unlock::after(['c'])),
            self::puzzle('c', Unlock::after(['b'])),
        ]);
    }

    public function testALongIdentifierIsCut(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" does not.', str_repeat('a', 64)));

        new Scenario(str_repeat('a', 65), [self::puzzle('a')]);
    }

    public function testAMultibyteIdentifierIsCutByCharacters(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" does not.', str_repeat('é', 64)));

        new Scenario(str_repeat('é', 65), [self::puzzle('a')]);
    }

    public function testTwoHundredPuzzlesAreReadFromValues(): void
    {
        $puzzles = array_map(static fn (int $index): array => ['id' => 'p'.$index, 'title' => 'P', 'answers' => ['x']], range(1, 200));

        self::assertCount(200, Scenario::fromArray(['id' => 'big', 'puzzles' => $puzzles])->order());
    }

    public function testTenHintsAreReadFromValues(): void
    {
        $hints = array_fill(0, 10, ['text' => 't']);
        $scenario = Scenario::fromArray(['id' => 's', 'puzzles' => [['id' => 'a', 'title' => 'A', 'answers' => ['x'], 'hints' => $hints]]]);

        self::assertCount(10, $scenario->puzzle('a')->hints);
    }

    public function testAMultibyteUnknownFieldIsCutByCharacters(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The field "%s" is not one of the scenario.', str_repeat('é', 64)));

        Scenario::fromArray([str_repeat('é', 65) => 1]);
    }

    public function testASharedRequirementIsNoCycle(): void
    {
        $scenario = new Scenario('diamond', [
            self::puzzle('d', Unlock::after(['b', 'c'])),
            self::puzzle('b', Unlock::after(['a'])),
            self::puzzle('c', Unlock::after(['a'])),
            self::puzzle('a'),
        ]);

        self::assertSame(['a', 'b', 'c', 'd'], $scenario->order());
    }

    public function testAPuzzleWaitingForMoreSolvesThanTheScenarioAllowsIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('No game can reach the puzzles b, c.');

        new Scenario('stuck', [self::puzzle('a'), self::puzzle('b', Unlock::afterSolves(2)), self::puzzle('c', Unlock::after(['b']))]);
    }

    public function testPuzzlesWaitingOnSolvesOpenOneAfterTheOther(): void
    {
        $scenario = new Scenario('ladder', [self::puzzle('c', Unlock::afterSolves(2)), self::puzzle('b', Unlock::afterSolves(1)), self::puzzle('a')]);

        self::assertSame(['a', 'b', 'c'], $scenario->order());
    }

    public function testAScenarioIsReadFromJson(): void
    {
        $scenario = Scenario::fromJson(<<<'JSON'
            {"id": "manor", "finale": "vault", "puzzles": [
                {"id": "clock", "title": "The clock", "answers": ["Midnight", "Minuit"],
                 "partials": {"twelve": "Which twelve?"},
                 "hints": [{"text": "Look at the hands", "penalty": 60}, {"text": "Both up"}]},
                {"id": "letter", "title": "A letter", "answers": ["Le Chêne"],
                 "normalize": {"keep_accents": true, "keep_spaces": true, "keep_punctuation": false},
                 "unlock": {"manual": true}},
                {"id": "vault", "title": "The vault", "answers": ["4521"],
                 "unlock": {"requires": ["clock", "letter"], "minimum": 1, "after_solves": 1}}
            ]}
            JSON);

        self::assertSame('manor', $scenario->id);
        self::assertSame('vault', $scenario->finale);
        $clock = $scenario->puzzle('clock');
        self::assertSame('The clock', $clock->title);
        self::assertSame(GuessOutcome::Correct, $clock->answer->check('minuit')->outcome);
        self::assertSame('Which twelve?', $clock->answer->check('Twelve')->message);
        self::assertSame(60, $clock->hints[0]->penalty);
        self::assertSame(0, $clock->hints[1]->penalty);
        self::assertSame('Both up', $clock->hints[1]->text);
        self::assertTrue($scenario->puzzle('letter')->unlock->manual);
        self::assertSame(GuessOutcome::Wrong, $scenario->puzzle('letter')->answer->check('lechene')->outcome);
        self::assertSame(GuessOutcome::Correct, $scenario->puzzle('letter')->answer->check('le chêne')->outcome);
        $unlock = $scenario->puzzle('vault')->unlock;
        self::assertSame(['clock', 'letter'], $unlock->requires);
        self::assertSame(1, $unlock->minimum);
        self::assertSame(1, $unlock->afterSolves);
        self::assertFalse($unlock->manual);
    }

    public function testAMinimalScenarioTakesTheDefaults(): void
    {
        $scenario = Scenario::fromArray(['id' => 's', 'puzzles' => [['id' => 'a', 'title' => 'A', 'answers' => ['x'], 'partials' => []]], 'finale' => null]);

        self::assertNull($scenario->finale);
        self::assertSame([], $scenario->puzzle('a')->hints);
        self::assertSame(GuessOutcome::Correct, $scenario->puzzle('a')->answer->check('X!')->outcome);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badJson(): iterable
    {
        yield 'not JSON' => ['{', 'The scenario is not JSON of 8 levels at most: Syntax error'];
        yield 'too deep' => [str_repeat('[', 9).str_repeat(']', 9), 'The scenario is not JSON of 8 levels at most: Maximum stack depth exceeded'];
        yield 'not an object' => ['"manor"', 'A scenario is an object of fields.'];
        yield 'an unknown field' => ['{"id": "s", "puzzles": [], "theme": "x"}', 'The field "theme" is not one of the scenario.'];
        yield 'no puzzles' => ['{"id": "s"}', 'The puzzles of a scenario are a list of 200 at most.'];
        yield 'puzzles not a list' => ['{"id": "s", "puzzles": {"a": {}}}', 'The puzzles of a scenario are a list of 200 at most.'];
        yield 'no identifier' => ['{"puzzles": [{"id": "a", "title": "A", "answers": ["x"]}]}', 'The field "id" of the scenario is a text.'];
        yield 'a finale not a text' => ['{"id": "s", "finale": 3, "puzzles": [{"id": "a", "title": "A", "answers": ["x"]}]}', 'The field "finale" of the scenario is a text.'];
        yield 'a puzzle not an object' => ['{"id": "s", "puzzles": ["a"]}', 'A puzzle is an object of fields.'];
        yield 'an unknown field of a puzzle' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "answer": "x"}]}', 'The field "answer" is not one of a puzzle.'];
        yield 'a puzzle without identifier' => ['{"id": "s", "puzzles": [{"title": "A", "answers": ["x"]}]}', 'The field "id" of a puzzle is a text.'];
        yield 'a puzzle without title' => ['{"id": "s", "puzzles": [{"id": "a", "answers": ["x"]}]}', 'The field "title" of a puzzle is a text.'];
        yield 'no answers' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A"}]}', 'The answers of the puzzle "a" are a list of texts.'];
        yield 'answers not a list' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": {"x": "y"}}]}', 'The answers of the puzzle "a" are a list of texts.'];
        yield 'an answer not a text' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": [12]}]}', 'The answers of the puzzle "a" are a list of texts.'];
        yield 'partials a list' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "partials": ["y"]}]}', 'The partial answers of the puzzle "a" are an object of messages.'];
        yield 'partials not an array' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "partials": "y"}]}', 'The partial answers of the puzzle "a" are an object of messages.'];
        yield 'a message not a text' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "partials": {"y": 1}}]}', 'The partial answers of the puzzle "a" are an object of messages.'];
        yield 'hints not a list' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "hints": {"a": 1}}]}', 'The hints of the puzzle "a" are a list of 10 at most.'];
        yield 'eleven hints' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "hints": [{},{},{},{},{},{},{},{},{},{},{}]}]}', 'The hints of the puzzle "a" are a list of 10 at most.'];
        yield 'a hint not an object' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "hints": ["look"]}]}', 'A hint is an object of fields.'];
        yield 'an unknown field of a hint' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "hints": [{"text": "t", "cost": 1}]}]}', 'The field "cost" is not one of a hint.'];
        yield 'a penalty not a number' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "hints": [{"text": "t", "penalty": "1"}]}]}', 'The field "penalty" of a hint is a whole number.'];
        yield 'a normalization not an object' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "normalize": true}]}', 'The normalization of a puzzle is an object of fields.'];
        yield 'an unknown option' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "normalize": {"keep_case": true}}]}', 'The field "keep_case" is not one of a normalization.'];
        yield 'an option not a flag' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "normalize": {"keep_spaces": 1}}]}', 'The field "keep_spaces" is true or false.'];
        yield 'an unlock not an object' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": "open"}]}', 'The unlock of a puzzle is an object of fields.'];
        yield 'an unknown field of an unlock' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"after": []}}]}', 'The field "after" is not one of an unlock.'];
        yield 'requires not a list' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"requires": "b"}}]}', 'The puzzles an unlock requires are a list of identifiers.'];
        yield 'requires an object' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"requires": {"b": "b"}}}]}', 'The puzzles an unlock requires are a list of identifiers.'];
        yield 'a requirement not a text' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"requires": [1]}}]}', 'The puzzles an unlock requires are a list of identifiers.'];
        yield 'a minimum not a number' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"minimum": 1.5}}]}', 'The field "minimum" of an unlock is a whole number.'];
        yield 'solves not a number' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"after_solves": "1"}}]}', 'The field "after_solves" of an unlock is a whole number.'];
        yield 'manual not a flag' => ['{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"manual": "yes"}}]}', 'The field "manual" is true or false.'];
    }

    #[DataProvider('badJson')]
    public function testATextThatIsNotAScenarioIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains($message);

        Scenario::fromJson($json);
    }

    public function testTwoHundredAndOnePuzzlesAreRefusedBeforeTheyAreRead(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles of a scenario are a list of 200 at most.');

        Scenario::fromArray(['id' => 's', 'puzzles' => array_fill(0, 201, 'not read')]);
    }

    public function testATextOfTheLargestSizeIsRead(): void
    {
        $json = '{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"]}]}';
        $json .= str_repeat(' ', Scenario::MAX_JSON_BYTES - \strlen($json));

        self::assertSame('s', Scenario::fromJson($json)->id);
    }

    public function testALargerTextIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A scenario holds 262144 bytes at most.');

        Scenario::fromJson(str_repeat(' ', Scenario::MAX_JSON_BYTES + 1));
    }

    public function testEightLevelsAreRead(): void
    {
        $scenario = Scenario::fromJson('{"id": "s", "puzzles": [{"id": "a", "title": "A", "answers": ["x"], "unlock": {"requires": []}}]}');

        self::assertSame([], $scenario->puzzle('a')->unlock->requires);
    }

    public function testALongUnknownFieldIsCut(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The field "%s" is not one of the scenario.', str_repeat('x', 64)));

        Scenario::fromArray(['id' => 's', 'puzzles' => [], str_repeat('x', 100) => 1]);
    }

    private static function puzzle(string $id, Unlock $unlock = new Unlock()): Puzzle
    {
        return new Puzzle($id, ucfirst($id), new AnswerKey([$id]), [], $unlock);
    }
}
