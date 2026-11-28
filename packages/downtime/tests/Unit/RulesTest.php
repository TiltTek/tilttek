<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Band;
use Tilttek\Downtime\Cost;
use Tilttek\Downtime\CostBasis;
use Tilttek\Downtime\Effect;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;
use Tilttek\Downtime\Internal\Text;
use Tilttek\Downtime\Outcome;
use Tilttek\Downtime\Resolution;
use Tilttek\Downtime\SystemRules;

#[CoversClass(Activity::class)]
#[CoversClass(Band::class)]
#[CoversClass(Cost::class)]
#[CoversClass(CostBasis::class)]
#[CoversClass(Effect::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(Resolution::class)]
#[CoversClass(SystemRules::class)]
#[CoversClass(InvalidDowntime::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
final class RulesTest extends TestCase
{
    public function testACostIsPaidOnceOrForEachDay(): void
    {
        $once = new Cost('gp', 50);
        $daily = Cost::perDay('gp', 3);

        self::assertSame('gp', $once->resource);
        self::assertSame(50, $once->amount);
        self::assertSame(CostBasis::Once, $once->basis);
        self::assertSame(50, $once->total(7));
        self::assertSame(CostBasis::PerDay, $daily->basis);
        self::assertSame(21, $daily->total(7));
        self::assertSame(1, new Cost('a', 1)->amount);
        self::assertSame(Cost::MAX_AMOUNT, new Cost('a', Cost::MAX_AMOUNT)->amount);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function amountsOutOfBounds(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above the most' => [Cost::MAX_AMOUNT + 1];
    }

    #[DataProvider('amountsOutOfBounds')]
    public function testACostIsBounded(int $amount): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The amount of a cost is 1 to 1000000.');

        new Cost('gp', $amount);
    }

    public function testACostNamesItsResourceByAKey(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The key of a resource is 1 to 64 letters, digits, "_", ".", ":" or "-", starting with a letter.');

        new Cost('1gp', 1);
    }

    public function testAnEffectGivesOrTakes(): void
    {
        self::assertSame(-Effect::MAX_DELTA, new Effect('gp', -Effect::MAX_DELTA)->delta);
        self::assertSame(Effect::MAX_DELTA, new Effect('gp', Effect::MAX_DELTA)->delta);
        self::assertSame('xp', new Effect('xp', 1)->resource);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function deltasOutOfBounds(): iterable
    {
        yield 'zero' => [0];
        yield 'below the least' => [-Effect::MAX_DELTA - 1];
        yield 'above the most' => [Effect::MAX_DELTA + 1];
    }

    #[DataProvider('deltasOutOfBounds')]
    public function testAnEffectIsBoundedAndNeverZero(int $delta): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The change of an effect is -1000000 to 1000000, never 0.');

        new Effect('gp', $delta);
    }

    public function testAnEffectNamesItsResourceByAKey(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The key of a resource');

        new Effect('', 1);
    }

    public function testAnOutcomeHoldsItsTrimmedTextsAndItsEffects(): void
    {
        $effects = [new Effect('gp', 25), new Effect('renown', -1)];
        $outcome = new Outcome('profit', '  A profit  ', $effects, "  You earn coin.\r\nAnd a name.\r ");

        self::assertSame('profit', $outcome->key);
        self::assertSame('A profit', $outcome->label);
        self::assertSame("You earn coin.\nAnd a name.", $outcome->text);
        self::assertSame($effects, $outcome->effects);
        self::assertSame('', new Outcome('o', 'O')->text);
        self::assertSame([], new Outcome('o', 'O')->effects);
        self::assertSame("a\n\tb", new Outcome('o', 'O', [], "a\n\tb")->text);
    }

    public function testAnOutcomeTakesUpToItsMostEffects(): void
    {
        $effects = array_map(static fn (int $i): Effect => new Effect('r'.$i, 1), range(1, Outcome::MAX_EFFECTS));
        self::assertCount(Outcome::MAX_EFFECTS, new Outcome('o', 'O', $effects)->effects);

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An outcome has at most 8 effects.');

        new Outcome('o', 'O', [...$effects, new Effect('more', 1)]);
    }

    public function testAnOutcomeNamesEachResourceOnce(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The resource gp comes twice among the effects of the outcome o.');

        new Outcome('o', 'O', [new Effect('gp', 1), new Effect('xp', 1), new Effect('gp', 2)]);
    }

    public function testAnOutcomeIsKeyed(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The key of an outcome is 1 to 64 letters');

        new Outcome(str_repeat('a', Identifier::MAX_KEY_LENGTH + 1), 'O');
    }

    public function testAKeyIsBoundedInLength(): void
    {
        self::assertSame(str_repeat('a', Identifier::MAX_KEY_LENGTH), new Outcome(str_repeat('a', Identifier::MAX_KEY_LENGTH), 'O')->key);
        self::assertSame('A.b:c_d-9', new Outcome('A.b:c_d-9', 'O')->key);
    }

    public function testTextsAreBoundedInCharacters(): void
    {
        $outcome = new Outcome('o', str_repeat('é', Outcome::MAX_LABEL), [], str_repeat('é', Outcome::MAX_TEXT));
        self::assertSame(Outcome::MAX_LABEL, mb_strlen($outcome->label));
        self::assertSame(Outcome::MAX_TEXT, mb_strlen($outcome->text));

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The text of an outcome is 0 to 2000 characters.');

        new Outcome('o', 'O', [], str_repeat('a', Outcome::MAX_TEXT + 1));
    }

    public function testALabelIsBounded(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The label of an outcome is 1 to 120 characters.');

        new Outcome('o', str_repeat('a', Outcome::MAX_LABEL + 1));
    }

    public function testALabelIsNeverEmpty(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The label of an outcome is 1 to 120 characters.');

        new Outcome('o', '  ');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function forbiddenTexts(): iterable
    {
        yield 'a line break in a label' => ["A\nB", 'text', 'The label of an outcome holds a control character or a direction override.'];
        yield 'a tabulation in a label' => ["A\tB", 'text', 'The label of an outcome holds a control character or a direction override.'];
        yield 'a direction override in a label' => ["A\u{202E}B", 'text', 'The label of an outcome holds a control character or a direction override.'];
        yield 'an isolate in a label' => ["A\u{2066}B", 'text', 'The label of an outcome holds a control character or a direction override.'];
        yield 'a control character in a text' => ['A', "a\x07b", 'The text of an outcome holds a control character or a direction override.'];
        yield 'a direction override in a text' => ['A', "a\u{202A}b", 'The text of an outcome holds a control character or a direction override.'];
        yield 'an isolate in a text' => ['A', "a\u{2069}b", 'The text of an outcome holds a control character or a direction override.'];
        yield 'a label not in UTF-8' => ["\xFF", 'text', 'The label of an outcome is not valid UTF-8.'];
        yield 'a text not in UTF-8' => ['A', "\xFF", 'The text of an outcome is not valid UTF-8.'];
    }

    #[DataProvider('forbiddenTexts')]
    public function testTextsRefuseForbiddenCharacters(string $label, string $text, string $message): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Outcome('o', $label, [], $text);
    }

    public function testABandCoversItsTotals(): void
    {
        $band = new Band(-Band::MAX_TOTAL, Band::MAX_TOTAL, 'any');
        self::assertSame(-Band::MAX_TOTAL, $band->from);
        self::assertSame(Band::MAX_TOTAL, $band->to);
        self::assertSame('any', $band->outcome);
        self::assertSame(3, new Band(3, 3, 'one')->to);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function bandsOutOfBounds(): iterable
    {
        yield 'below the least' => [-Band::MAX_TOTAL - 1, 0];
        yield 'above the most' => [0, Band::MAX_TOTAL + 1];
        yield 'empty' => [5, 4];
    }

    #[DataProvider('bandsOutOfBounds')]
    public function testABandIsBounded(int $from, int $to): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('A band covers totals from -10000 to 10000, its lowest no higher than its highest.');

        new Band($from, $to, 'o');
    }

    public function testABandNamesItsOutcomeByAKey(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The key of an outcome');

        new Band(1, 2, 'a b');
    }

    public function testAFixedResolutionAlwaysEndsWithItsOutcome(): void
    {
        $resolution = Resolution::fixed('done');

        self::assertFalse($resolution->isRolled());
        self::assertNull($resolution->dice);
        self::assertSame([], $resolution->bands);
        self::assertSame('done', $resolution->outcome);
        self::assertSame('done', $resolution->outcomeFor(-50));
        self::assertSame(['done'], $resolution->outcomes());
    }

    public function testAFixedResolutionIsKeyed(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The key of an outcome');

        Resolution::fixed('');
    }

    public function testARolledResolutionReadsItsTotalAgainstItsBandsInOrder(): void
    {
        $resolution = Resolution::rolled('1d20+5', [new Band(11, 20, 'success'), new Band(21, 30, 'triumph'), new Band(1, 10, 'failure')]);

        self::assertTrue($resolution->isRolled());
        self::assertSame('1d20+5', $resolution->dice);
        self::assertNull($resolution->outcome);
        self::assertSame([1, 11, 21], array_map(static fn (Band $band): int => $band->from, $resolution->bands));
        self::assertSame('failure', $resolution->outcomeFor(-3));
        self::assertSame('failure', $resolution->outcomeFor(1));
        self::assertSame('failure', $resolution->outcomeFor(10));
        self::assertSame('success', $resolution->outcomeFor(11));
        self::assertSame('success', $resolution->outcomeFor(20));
        self::assertSame('triumph', $resolution->outcomeFor(21));
        self::assertSame('triumph', $resolution->outcomeFor(30));
        self::assertSame('triumph', $resolution->outcomeFor(99));
        self::assertSame(['failure', 'success', 'triumph'], $resolution->outcomes());
    }

    public function testTheOutcomesOfARollComeOnce(): void
    {
        $resolution = Resolution::rolled('2d6', [new Band(2, 6, 'miss'), new Band(7, 9, 'hit'), new Band(10, 12, 'miss')]);

        self::assertSame(['miss', 'hit'], $resolution->outcomes());
        self::assertSame('miss', $resolution->outcomeFor(12));
    }

    public function testTheOutcomesOfARollAreAListWhateverTheRepeats(): void
    {
        $resolution = Resolution::rolled('2d6', [new Band(2, 6, 'miss'), new Band(7, 9, 'miss'), new Band(10, 12, 'hit')]);

        self::assertSame(['miss', 'hit'], $resolution->outcomes());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function diceExpressions(): iterable
    {
        yield 'a die with a modifier' => ['1d20+5'];
        yield 'a pool kept high' => ['4d6kh3'];
        yield 'one character' => ['7'];
        yield 'spaces within' => ['2d10 + 1d4 - 1'];
        yield 'signs' => ['(1d6!)*2/1>=3,<.'];
        yield 'the longest' => [str_repeat('1', Resolution::MAX_DICE)];
    }

    #[DataProvider('diceExpressions')]
    public function testADiceExpressionIsKeptAsWritten(string $dice): void
    {
        self::assertSame($dice, Resolution::rolled($dice, [new Band(1, 1, 'o')])->dice);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badDiceExpressions(): iterable
    {
        yield 'empty' => [''];
        yield 'a space before' => [' 1d6'];
        yield 'a space after' => ['1d6 '];
        yield 'too long' => [str_repeat('1', Resolution::MAX_DICE + 1)];
        yield 'a line break' => ["1d6\n"];
        yield 'a quote' => ['1d6"'];
        yield 'a non-ASCII letter' => ['1dé'];
    }

    #[DataProvider('badDiceExpressions')]
    public function testADiceExpressionIsBoundedInShape(string $dice): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('A dice expression is 1 to 64 letters, digits, spaces or signs, without a space at either end.');

        Resolution::rolled($dice, [new Band(1, 1, 'o')]);
    }

    public function testARollHasBands(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('A roll has 1 to 16 bands.');

        Resolution::rolled('1d6', []);
    }

    public function testARollHasAtMostItsMostBands(): void
    {
        $bands = array_map(static fn (int $i): Band => new Band($i, $i, 'o'), range(1, Resolution::MAX_BANDS));
        self::assertCount(Resolution::MAX_BANDS, Resolution::rolled('1d20', $bands)->bands);

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('A roll has 1 to 16 bands.');

        Resolution::rolled('1d20', [...$bands, new Band(17, 17, 'o')]);
    }

    public function testBandsLeaveNoGap(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The bands of a roll follow one another without overlap nor gap: 12 follows 10.');

        Resolution::rolled('1d20', [new Band(1, 10, 'a'), new Band(12, 20, 'b')]);
    }

    public function testBandsDoNotOverlap(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The bands of a roll follow one another without overlap nor gap: 10 follows 10.');

        Resolution::rolled('1d20', [new Band(1, 10, 'a'), new Band(10, 20, 'b')]);
    }

    /**
     * @param list<Cost> $costs
     */
    private function crafting(int $days = 5, array $costs = []): Activity
    {
        return new Activity('crafting', '  Crafting  ', $days, [new Outcome('made', 'Made'), new Outcome('spoiled', 'Spoiled')], Resolution::rolled('1d20', [new Band(1, 9, 'spoiled'), new Band(10, 20, 'made')]), $costs, "  Make an item.\r\n ");
    }

    public function testAnActivityHoldsItsRules(): void
    {
        $costs = [Cost::perDay('gp', 5), new Cost('supplies', 2)];
        $activity = $this->crafting(4, $costs);

        self::assertSame('crafting', $activity->key);
        self::assertSame('Crafting', $activity->name);
        self::assertSame('Make an item.', $activity->description);
        self::assertSame(4, $activity->days);
        self::assertSame(['made', 'spoiled'], array_map(static fn (Outcome $outcome): string => $outcome->key, $activity->outcomes));
        self::assertTrue($activity->resolution->isRolled());
        self::assertSame($costs, $activity->costs);
        self::assertSame(['gp' => 20, 'supplies' => 2], $activity->totals());
        self::assertSame('Spoiled', $activity->outcome('spoiled')?->label);
        self::assertNull($activity->outcome('lost'));
        self::assertSame([], $this->crafting()->totals());
    }

    public function testAnActivityTakesItsDaysWithinBounds(): void
    {
        self::assertSame(1, $this->crafting(1)->days);
        self::assertSame(Activity::MAX_DAYS, $this->crafting(Activity::MAX_DAYS)->days);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function daysOutOfBounds(): iterable
    {
        yield 'none' => [0];
        yield 'above the most' => [Activity::MAX_DAYS + 1];
    }

    #[DataProvider('daysOutOfBounds')]
    public function testAnActivityIsBoundedInDays(int $days): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An activity takes 1 to 365 days.');

        $this->crafting($days);
    }

    public function testAnActivityHasOutcomes(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An activity has 1 to 16 outcomes.');

        new Activity('a', 'A', 1, [], Resolution::fixed('o'));
    }

    public function testAnActivityHasAtMostItsMostOutcomes(): void
    {
        $outcomes = array_map(static fn (int $i): Outcome => new Outcome('o'.$i, 'O'), range(1, Activity::MAX_OUTCOMES));
        self::assertCount(Activity::MAX_OUTCOMES, new Activity('a', 'A', 1, $outcomes, Resolution::fixed('o1'))->outcomes);

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An activity has 1 to 16 outcomes.');

        new Activity('a', 'A', 1, [...$outcomes, new Outcome('more', 'O')], Resolution::fixed('o1'));
    }

    public function testAnActivityDeclaresEachOutcomeOnce(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The outcome o comes twice in the activity a.');

        new Activity('a', 'A', 1, [new Outcome('o', 'O'), new Outcome('p', 'P'), new Outcome('o', 'Again')], Resolution::fixed('o'));
    }

    public function testAnActivityDeclaresEveryOutcomeItsResolutionNames(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The resolution of the activity a names the outcome lost, which it does not declare.');

        new Activity('a', 'A', 1, [new Outcome('made', 'Made')], Resolution::rolled('1d6', [new Band(1, 3, 'made'), new Band(4, 6, 'lost')]));
    }

    public function testAnActivityHasAtMostItsMostCosts(): void
    {
        $costs = array_map(static fn (int $i): Cost => new Cost('r'.$i, 1), range(1, Activity::MAX_COSTS));
        self::assertCount(Activity::MAX_COSTS, $this->crafting(1, $costs)->costs);

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An activity has at most 8 costs.');

        $this->crafting(1, [...$costs, new Cost('more', 1)]);
    }

    public function testAnActivityCostsEachResourceOnce(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The resource gp comes twice among the costs of the activity crafting.');

        $this->crafting(1, [new Cost('gp', 1), Cost::perDay('gp', 1)]);
    }

    public function testAnActivityIsKeyedAndNamed(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The key of an activity is 1 to 64 letters');

        new Activity('-a', 'A', 1, [new Outcome('o', 'O')], Resolution::fixed('o'));
    }

    public function testANameIsBounded(): void
    {
        self::assertSame(Activity::MAX_NAME, mb_strlen(new Activity('a', str_repeat('é', Activity::MAX_NAME), 1, [new Outcome('o', 'O')], Resolution::fixed('o'))->name));

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The name of an activity is 1 to 120 characters.');

        new Activity('a', str_repeat('a', Activity::MAX_NAME + 1), 1, [new Outcome('o', 'O')], Resolution::fixed('o'));
    }

    public function testADescriptionIsBounded(): void
    {
        self::assertSame(Activity::MAX_DESCRIPTION, mb_strlen(new Activity('a', 'A', 1, [new Outcome('o', 'O')], Resolution::fixed('o'), [], str_repeat('é', Activity::MAX_DESCRIPTION))->description));

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The description of an activity is 0 to 4000 characters.');

        new Activity('a', 'A', 1, [new Outcome('o', 'O')], Resolution::fixed('o'), [], str_repeat('a', Activity::MAX_DESCRIPTION + 1));
    }

    public function testTheRulesOfASystemFindTheirActivitiesByKey(): void
    {
        $training = new Activity('training', 'Training', 10, [new Outcome('learned', 'Learned')], Resolution::fixed('learned'));
        $rules = new SystemRules('fantasy-5e', [$this->crafting(), $training]);

        self::assertSame('fantasy-5e', $rules->system);
        self::assertSame($training, $rules->activity('training'));
        self::assertNull($rules->activity('carousing'));
        self::assertSame(['crafting', 'training'], array_map(static fn (Activity $activity): string => $activity->key, $rules->activities()));
    }

    public function testTheRulesOfASystemDeclareActivities(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The rules of a system declare 1 to 64 activities.');

        new SystemRules('s', []);
    }

    public function testTheRulesOfASystemDeclareAtMostTheirMostActivities(): void
    {
        $activities = array_map(static fn (int $i): Activity => new Activity('a'.$i, 'A', 1, [new Outcome('o', 'O')], Resolution::fixed('o')), range(1, SystemRules::MAX_ACTIVITIES));
        self::assertCount(SystemRules::MAX_ACTIVITIES, new SystemRules('s', $activities)->activities());

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The rules of a system declare 1 to 64 activities.');

        new SystemRules('s', [...$activities, new Activity('more', 'A', 1, [new Outcome('o', 'O')], Resolution::fixed('o'))]);
    }

    public function testTheRulesOfASystemDeclareEachActivityOnce(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The activity crafting comes twice in the rules of the system s.');

        new SystemRules('s', [$this->crafting(), $this->crafting(2)]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'starting with a digit' => ['5e'];
        yield 'an address' => ['gm@example.com'];
        yield 'too long' => [str_repeat('a', Identifier::MAX_LENGTH + 1)];
        yield 'a line break after' => ["s\n"];
    }

    #[DataProvider('badIdentifiers')]
    public function testTheSystemIsAnIdentifier(string $system): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a system is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');

        new SystemRules($system, [$this->crafting()]);
    }

    public function testAnIdentifierIsBoundedInLength(): void
    {
        self::assertSame(str_repeat('a', Identifier::MAX_LENGTH), new SystemRules(str_repeat('a', Identifier::MAX_LENGTH), [$this->crafting()])->system);
    }
}
