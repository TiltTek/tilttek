<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Conditions\ActiveCondition;
use Tilttek\Conditions\Anchor;
use Tilttek\Conditions\Boundary;
use Tilttek\Conditions\CharacterConditions;
use Tilttek\Conditions\ConditionCatalogue;
use Tilttek\Conditions\ConditionDefinition;
use Tilttek\Conditions\Decay;
use Tilttek\Conditions\Duration;
use Tilttek\Conditions\DurationKind;
use Tilttek\Conditions\Exception\ConditionConflict;
use Tilttek\Conditions\Exception\ConditionNotFound;
use Tilttek\Conditions\Exception\ConditionRefused;
use Tilttek\Conditions\Exception\ConditionsException;
use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;
use Tilttek\Conditions\Internal\Millis;
use Tilttek\Conditions\Modifier;
use Tilttek\Conditions\Operation;
use Tilttek\Conditions\Stacking;
use Tilttek\Conditions\Store\InMemoryConditionStore;

#[CoversClass(Duration::class)]
#[CoversClass(Decay::class)]
#[CoversClass(Modifier::class)]
#[CoversClass(ConditionDefinition::class)]
#[CoversClass(ConditionCatalogue::class)]
#[CoversClass(ActiveCondition::class)]
#[CoversClass(CharacterConditions::class)]
#[CoversClass(InMemoryConditionStore::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Millis::class)]
#[CoversClass(InvalidCondition::class)]
#[CoversClass(ConditionConflict::class)]
#[CoversClass(ConditionNotFound::class)]
#[CoversClass(ConditionRefused::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{DurationKind, int, int}>
     */
    public static function durationBounds(): iterable
    {
        yield 'rounds' => [DurationKind::Rounds, 1, Duration::MAX_ROUNDS];
        yield 'game time' => [DurationKind::GameTime, 1, Duration::MAX_GAME_SECONDS];
        yield 'real time' => [DurationKind::RealTime, Duration::MIN_REAL_MILLISECONDS, Duration::MAX_REAL_MILLISECONDS];
        yield 'until removed' => [DurationKind::UntilRemoved, 0, 0];
    }

    #[DataProvider('durationBounds')]
    public function testADurationKeepsItsAmountWithinTheBoundsOfItsKind(DurationKind $kind, int $min, int $max): void
    {
        self::assertSame($min, new Duration($kind, $min)->amount);
        self::assertSame($max, new Duration($kind, $max)->amount);

        foreach ([$min - 1, $max + 1] as $amount) {
            try {
                new Duration($kind, $amount);
                self::fail(\sprintf('The amount %d was accepted.', $amount));
            } catch (InvalidCondition $invalid) {
                self::assertSame(\sprintf('A duration of kind %s has an amount of %d to %d.', $kind->value, $min, $max), $invalid->getMessage());
            }
        }
    }

    public function testTheNamedDurations(): void
    {
        $rounds = Duration::rounds(3, Boundary::Start, Anchor::Source);
        self::assertSame(['kind' => 'rounds', 'amount' => 3, 'boundary' => 'start', 'anchor' => 'source'], $rounds->toArray());
        self::assertSame(['kind' => 'rounds', 'amount' => 2, 'boundary' => 'end', 'anchor' => 'target'], Duration::rounds(2)->toArray());
        self::assertSame(['kind' => 'game-time', 'amount' => 60, 'boundary' => 'end', 'anchor' => 'target'], Duration::gameTime(60)->toArray());
        self::assertSame(['kind' => 'real-time', 'amount' => 5_000, 'boundary' => 'end', 'anchor' => 'target'], Duration::realTime(5_000)->toArray());
        self::assertSame(['kind' => 'until-removed', 'amount' => 0, 'boundary' => 'end', 'anchor' => 'target'], Duration::untilRemoved()->toArray());
        self::assertSame(['kind' => 'rounds', 'amount' => 1, 'boundary' => 'start', 'anchor' => 'source'], $rounds->withAmount(1)->toArray());
    }

    public function testADurationCountsAtTheBoundaryOfItsAnchor(): void
    {
        $target = Duration::rounds(2);
        self::assertTrue($target->countsAt(Boundary::End, 'ada', 'ada', 'bo'));
        self::assertFalse($target->countsAt(Boundary::Start, 'ada', 'ada', 'bo'));
        self::assertFalse($target->countsAt(Boundary::End, 'bo', 'ada', 'bo'));
        self::assertFalse($target->countsAt(Boundary::End, null, 'ada', 'bo'));

        $source = Duration::rounds(2, Boundary::End, Anchor::Source);
        self::assertTrue($source->countsAt(Boundary::End, 'bo', 'ada', 'bo'));
        self::assertFalse($source->countsAt(Boundary::End, 'ada', 'ada', 'bo'));
        self::assertFalse($source->countsAt(Boundary::End, null, 'ada', null));

        $round = Duration::rounds(2, Boundary::Start, Anchor::Round);
        self::assertTrue($round->countsAt(Boundary::Start, null, 'ada', null));
        self::assertFalse($round->countsAt(Boundary::Start, 'ada', 'ada', null));
        self::assertFalse($round->countsAt(Boundary::End, null, 'ada', null));

        self::assertFalse(Duration::gameTime(6)->countsAt(Boundary::End, 'ada', 'ada', null));
        self::assertFalse(Duration::untilRemoved()->countsAt(Boundary::End, 'ada', 'ada', null));
    }

    public function testADecayTakesWithinItsBounds(): void
    {
        self::assertSame(1, new Decay()->amount);
        self::assertSame(Boundary::End, new Decay()->boundary);
        self::assertSame(ConditionDefinition::MAX_VALUE, new Decay(ConditionDefinition::MAX_VALUE, Boundary::Start)->amount);

        foreach ([0, ConditionDefinition::MAX_VALUE + 1] as $amount) {
            try {
                new Decay($amount);
                self::fail('The decay was accepted.');
            } catch (InvalidCondition $invalid) {
                self::assertSame('A decay takes 1 to 100 from the value.', $invalid->getMessage());
            }
        }
    }

    public function testTheNamedModifiers(): void
    {
        $add = Modifier::add('attack', -2, true, 'status');
        self::assertSame(['attack', Operation::Add, -2, true, 'status'], [$add->stat, $add->operation, $add->amount, $add->perValue, $add->type]);
        self::assertSame(-6, $add->amountFor(3));
        self::assertSame(-2, $add->amountFor(null));
        self::assertSame(5, Modifier::add('ac', 5)->amountFor(3));
        self::assertFalse(Modifier::add('ac', 5)->perValue);
        self::assertNull(Modifier::add('ac', 5)->type);
        self::assertSame(Operation::Multiply, Modifier::multiply('speed', 50)->operation);
        self::assertSame(Operation::Upgrade, Modifier::upgrade('ac', 15)->operation);
        self::assertSame(Operation::Downgrade, Modifier::downgrade('speed', 0)->operation);
        self::assertSame(Operation::Override, Modifier::override('str', 19)->operation);
        self::assertSame(Modifier::MAX_AMOUNT, Modifier::add('hp', Modifier::MAX_AMOUNT)->amount);
        self::assertSame(-Modifier::MAX_AMOUNT, Modifier::add('hp', -Modifier::MAX_AMOUNT)->amount);
        self::assertSame(0, Modifier::multiply('hp', 0)->amount);
        self::assertSame(Modifier::MAX_PERCENT, Modifier::multiply('hp', Modifier::MAX_PERCENT)->amount);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidModifiers(): iterable
    {
        yield 'statistic' => [static fn (): Modifier => Modifier::add('hit points', 1), 'The identifier of a statistic is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'type' => [static fn (): Modifier => Modifier::add('hp', 1, type: '1st'), 'The identifier of a modifier type is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'percent below' => [static fn (): Modifier => Modifier::multiply('hp', -1), 'A multiplication is by 0 to 1000 percent.'];
        yield 'percent above' => [static fn (): Modifier => Modifier::multiply('hp', Modifier::MAX_PERCENT + 1), 'A multiplication is by 0 to 1000 percent.'];
        yield 'per value' => [static fn (): Modifier => new Modifier('hp', Operation::Multiply, 50, true), 'A multiplication is never by the value of the condition.'];
        yield 'amount above' => [static fn (): Modifier => Modifier::override('hp', Modifier::MAX_AMOUNT + 1), 'The amount of a modifier is 1000000 at most either way.'];
        yield 'amount below' => [static fn (): Modifier => Modifier::upgrade('hp', -Modifier::MAX_AMOUNT - 1), 'The amount of a modifier is 1000000 at most either way.'];
        yield 'definition id' => [static fn (): ConditionDefinition => new ConditionDefinition('a b'), 'The identifier of a condition is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'max value zero' => [static fn (): ConditionDefinition => new ConditionDefinition('frightened', maxValue: 0), 'The highest value of a condition is 1 to 100.'];
        yield 'max value above' => [static fn (): ConditionDefinition => new ConditionDefinition('frightened', maxValue: 101), 'The highest value of a condition is 1 to 100.'];
        yield 'too many modifiers' => [static fn (): ConditionDefinition => new ConditionDefinition('weak', modifiers: array_fill(0, 33, Modifier::add('str', -1))), 'A condition has 32 modifiers at most.'];
        yield 'too many implied' => [static fn (): ConditionDefinition => new ConditionDefinition('dying', implies: array_map(static fn (int $i): string => 'c'.$i, range(1, 17))), 'A condition brings 16 others at most.'];
        yield 'implied id' => [static fn (): ConditionDefinition => new ConditionDefinition('dying', implies: ['not one']), 'The identifier of an implied condition is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'implies itself' => [static fn (): ConditionDefinition => new ConditionDefinition('dying', implies: ['dying']), 'The condition dying brings dying twice or brings itself.'];
        yield 'implies twice' => [static fn (): ConditionDefinition => new ConditionDefinition('dying', implies: ['prone', 'prone']), 'The condition dying brings prone twice or brings itself.'];
        yield 'sum without value' => [static fn (): ConditionDefinition => new ConditionDefinition('bleeding', Stacking::Sum), 'The condition bleeding sums or decays, and has no value.'];
        yield 'decay without value' => [static fn (): ConditionDefinition => new ConditionDefinition('bleeding', decay: new Decay()), 'The condition bleeding sums or decays, and has no value.'];
        yield 'per value without value' => [static fn (): ConditionDefinition => new ConditionDefinition('weak', modifiers: [Modifier::add('ac', 1), Modifier::add('str', -1, true)]), 'The condition weak modifies by its value, and has none.'];
        yield 'separate with one' => [static fn (): ConditionDefinition => new ConditionDefinition('poisoned', Stacking::Separate), 'A condition that stacks separately has 2 to 16 instances; any other has 1.'];
        yield 'separate with too many' => [static fn (): ConditionDefinition => new ConditionDefinition('poisoned', Stacking::Separate, maxInstances: 17), 'A condition that stacks separately has 2 to 16 instances; any other has 1.'];
        yield 'replace with two' => [static fn (): ConditionDefinition => new ConditionDefinition('poisoned', maxInstances: 2), 'A condition that stacks separately has 2 to 16 instances; any other has 1.'];
        yield 'replace with zero' => [static fn (): ConditionDefinition => new ConditionDefinition('poisoned', maxInstances: 0), 'A condition that stacks separately has 2 to 16 instances; any other has 1.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalidModifiers')]
    public function testAnInvalidModifierOrDefinitionIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains($message);
        $build();
    }

    public function testADefinitionWithinItsBounds(): void
    {
        $duration = Duration::rounds(1);
        $decay = new Decay();
        $modifiers = array_fill(0, ConditionDefinition::MAX_MODIFIERS, Modifier::add('str', -1, true));
        $implies = array_map(static fn (int $i): string => 'c'.$i, range(1, ConditionDefinition::MAX_IMPLIES));
        $definition = new ConditionDefinition('frightened', Stacking::Sum, ConditionDefinition::MAX_VALUE, $modifiers, $implies, $duration, $decay);

        self::assertSame('frightened', $definition->id);
        self::assertSame(Stacking::Sum, $definition->stacking);
        self::assertSame(100, $definition->maxValue);
        self::assertSame($modifiers, $definition->modifiers);
        self::assertSame($implies, $definition->implies);
        self::assertSame($duration, $definition->duration);
        self::assertSame($decay, $definition->decay);
        self::assertSame(1, $definition->maxInstances);
        self::assertTrue($definition->hasValue());
        self::assertSame(1, new ConditionDefinition('dazed', maxValue: 1)->maxValue);

        $plain = new ConditionDefinition('prone');
        self::assertSame(Stacking::Replace, $plain->stacking);
        self::assertNull($plain->maxValue);
        self::assertFalse($plain->hasValue());
        self::assertSame([], $plain->modifiers);
        self::assertSame([], $plain->implies);
        self::assertNull($plain->duration);
        self::assertNull($plain->decay);

        self::assertSame(2, new ConditionDefinition('poisoned', Stacking::Separate, maxInstances: 2)->maxInstances);
        self::assertSame(16, new ConditionDefinition('poisoned', Stacking::Separate, maxInstances: 16)->maxInstances);
    }

    public function testTheConditionsAppliedAreListedInOrderWithoutGaps(): void
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00Z');
        $active = array_map(static fn (string $id): ActiveCondition => new ActiveCondition('i'.$id, 'p' === $id[0] ? 'poisoned' : 'prone', null, null, Duration::untilRemoved(), $at), ['p1', 'r2', 'p3', 'r4']);
        $active[] = new ActiveCondition('i5', 'blinded', null, null, Duration::untilRemoved(), $at);

        self::assertSame(['poisoned', 'prone', 'blinded'], new CharacterConditions('ada', $active)->conditionIds());
    }

    public function testACatalogueTellsWhatIsBroughtTwiceOnlyOnce(): void
    {
        $catalogue = new ConditionCatalogue([
            new ConditionDefinition('a', implies: ['b', 'c']),
            new ConditionDefinition('b', implies: ['c', 'd']),
            new ConditionDefinition('c'),
            new ConditionDefinition('d'),
        ]);

        self::assertSame(['b', 'c', 'd'], $catalogue->implied(['a']));
    }

    public function testACatalogueFindsItsConditionsAndWhatTheyBring(): void
    {
        $catalogue = new ConditionCatalogue([
            new ConditionDefinition('dying', maxValue: 4, implies: ['unconscious']),
            new ConditionDefinition('unconscious', implies: ['blinded', 'prone', 'off-guard']),
            new ConditionDefinition('grabbed', implies: ['off-guard', 'immobilized']),
            new ConditionDefinition('blinded'),
            new ConditionDefinition('prone', implies: ['off-guard']),
            new ConditionDefinition('off-guard'),
            new ConditionDefinition('immobilized'),
        ]);

        self::assertSame(['dying', 'unconscious', 'grabbed', 'blinded', 'prone', 'off-guard', 'immobilized'], $catalogue->ids());
        self::assertSame('dying', $catalogue->get('dying')->id);
        self::assertSame('prone', $catalogue->find('prone')?->id);
        self::assertNull($catalogue->find('missing'));
        self::assertSame(['unconscious', 'blinded', 'prone', 'off-guard'], $catalogue->implied(['dying']));
        self::assertSame(['off-guard', 'immobilized', 'blinded', 'prone'], $catalogue->implied(['grabbed', 'unconscious']));
        self::assertSame([], $catalogue->implied(['blinded', 'missing']));

        $this->expectException(ConditionNotFound::class);
        $this->expectExceptionMessageIsOrContains('The catalogue has no condition missing.');
        $catalogue->get('missing');
    }

    /**
     * @return iterable<string, array{list<ConditionDefinition>, string}>
     */
    public static function invalidCatalogues(): iterable
    {
        yield 'twice' => [[new ConditionDefinition('prone'), new ConditionDefinition('prone')], 'The condition prone comes twice in the catalogue.'];
        yield 'missing' => [[new ConditionDefinition('dying', implies: ['unconscious'])], 'The condition dying brings unconscious, which the catalogue lacks.'];
        yield 'valued' => [[new ConditionDefinition('dying', implies: ['wounded']), new ConditionDefinition('wounded', maxValue: 3)], 'The condition dying brings wounded, which has a value.'];
        yield 'loop' => [[new ConditionDefinition('a', implies: ['b']), new ConditionDefinition('b', implies: ['c']), new ConditionDefinition('c', implies: ['a'])], 'The condition a brings itself back through others.'];
    }

    /**
     * @param list<ConditionDefinition> $definitions
     */
    #[DataProvider('invalidCatalogues')]
    public function testAnInvalidCatalogueIsRefused(array $definitions, string $message): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains($message);
        new ConditionCatalogue($definitions);
    }

    public function testACatalogueHasItsMostConditions(): void
    {
        $definitions = array_map(static fn (int $i): ConditionDefinition => new ConditionDefinition('c'.$i), range(1, ConditionCatalogue::MAX_DEFINITIONS));
        self::assertCount(ConditionCatalogue::MAX_DEFINITIONS, new ConditionCatalogue($definitions)->ids());

        $definitions[] = new ConditionDefinition('extra');
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('A catalogue has 1000 conditions at most.');
        new ConditionCatalogue($definitions);
    }

    public function testAnActiveConditionKeepsItsFields(): void
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00Z');
        $until = new DateTimeImmutable('2027-01-01T12:01:00Z');
        $one = new ActiveCondition('i1', 'frightened', 2, 'dragon', Duration::realTime(60_000), $at, $until);

        self::assertSame(['i1', 'frightened', 2, 'dragon', $at, $until], [$one->id, $one->condition, $one->value, $one->source, $one->appliedAt, $one->expiresAt]);
        self::assertSame(1, $one->withValue(1)->value);
        self::assertSame('dragon', $one->withValue(1)->source);
        self::assertSame($until, $one->withValue(1)->expiresAt);
        $rounds = new ActiveCondition('i2', 'prone', null, null, Duration::rounds(3), $at);
        self::assertSame(2, $rounds->withDuration(Duration::rounds(2))->duration->amount);
        self::assertSame('i2', $rounds->withDuration(Duration::rounds(2))->id);
        self::assertSame(1, new ActiveCondition('i3', 'f', 1, null, Duration::untilRemoved(), $at)->value);
        self::assertSame(100, new ActiveCondition('i3', 'f', 100, null, Duration::untilRemoved(), $at)->value);
    }

    /**
     * @return iterable<string, array{callable(): ActiveCondition, string}>
     */
    public static function invalidActive(): iterable
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00Z');
        yield 'id' => [static fn (): ActiveCondition => new ActiveCondition('1', 'prone', null, null, Duration::untilRemoved(), $at), 'The identifier of a condition instance is'];
        yield 'condition' => [static fn (): ActiveCondition => new ActiveCondition('i1', '', null, null, Duration::untilRemoved(), $at), 'The identifier of a condition is'];
        yield 'source' => [static fn (): ActiveCondition => new ActiveCondition('i1', 'prone', null, 'a@b', Duration::untilRemoved(), $at), 'The identifier of a source is'];
        yield 'value zero' => [static fn (): ActiveCondition => new ActiveCondition('i1', 'prone', 0, null, Duration::untilRemoved(), $at), 'The value of a condition is 1 to 100.'];
        yield 'value above' => [static fn (): ActiveCondition => new ActiveCondition('i1', 'prone', 101, null, Duration::untilRemoved(), $at), 'The value of a condition is 1 to 100.'];
        yield 'end missing' => [static fn (): ActiveCondition => new ActiveCondition('i1', 'prone', null, null, Duration::realTime(1_000), $at), 'A condition has the moment of its end when, and only when, it lasts in real time.'];
        yield 'end given' => [static fn (): ActiveCondition => new ActiveCondition('i1', 'prone', null, null, Duration::rounds(1), $at, $at), 'A condition has the moment of its end when, and only when, it lasts in real time.'];
    }

    /**
     * @param callable(): ActiveCondition $build
     */
    #[DataProvider('invalidActive')]
    public function testAnInvalidActiveConditionIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains($message);
        $build();
    }

    public function testCharacterConditionsFindTheirInstances(): void
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00Z');
        $one = new ActiveCondition('i1', 'poisoned', null, null, Duration::untilRemoved(), $at);
        $two = new ActiveCondition('i2', 'prone', null, null, Duration::untilRemoved(), $at);
        $three = new ActiveCondition('i3', 'poisoned', null, null, Duration::untilRemoved(), $at);
        $conditions = new CharacterConditions('ada', [$one, $two, $three], 3, 'tick-1', 4);

        self::assertSame(['ada', 3, 'tick-1', 4], [$conditions->character, $conditions->issued, $conditions->lastTick, $conditions->version]);
        self::assertSame($two, $conditions->instance('i2'));
        self::assertNull($conditions->instance('i9'));
        self::assertSame([$one, $three], $conditions->instancesOf('poisoned'));
        self::assertSame([], $conditions->instancesOf('blinded'));
        self::assertSame(['poisoned', 'prone'], $conditions->conditionIds());

        $changed = $conditions->with([$two], 5, 'tick-2');
        self::assertSame([[$two], 5, 'tick-2', 4], [$changed->active, $changed->issued, $changed->lastTick, $changed->version]);
        $kept = $conditions->withVersion(5);
        self::assertSame([[$one, $two, $three], 3, 'tick-1', 5], [$kept->active, $kept->issued, $kept->lastTick, $kept->version]);

        $empty = new CharacterConditions('bo');
        self::assertSame([[], 0, null, 0], [$empty->active, $empty->issued, $empty->lastTick, $empty->version]);
    }

    public function testCharacterConditionsHaveTheirMost(): void
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00Z');
        $active = array_map(static fn (int $i): ActiveCondition => new ActiveCondition('i'.$i, 'c', null, null, Duration::untilRemoved(), $at), range(1, CharacterConditions::MAX_ACTIVE));
        self::assertCount(64, new CharacterConditions('ada', $active)->active);

        $active[] = new ActiveCondition('i65', 'c', null, null, Duration::untilRemoved(), $at);
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('A character has 64 conditions at most.');
        new CharacterConditions('ada', $active);
    }

    /**
     * @return iterable<string, array{callable(): CharacterConditions, string}>
     */
    public static function invalidCharacters(): iterable
    {
        $at = new DateTimeImmutable('2027-01-01T12:00:00Z');
        $one = new ActiveCondition('i1', 'c', null, null, Duration::untilRemoved(), $at);
        yield 'character' => [static fn (): CharacterConditions => new CharacterConditions('ada lovelace'), 'The identifier of a character is'];
        yield 'tick' => [static fn (): CharacterConditions => new CharacterConditions('ada', lastTick: '-'), 'The identifier of a tick is'];
        yield 'twice' => [static fn (): CharacterConditions => new CharacterConditions('ada', [$one, $one]), 'The condition instance i1 comes twice.'];
        yield 'issued' => [static fn (): CharacterConditions => new CharacterConditions('ada', issued: -1), 'The number of instances and the version are 0 or more.'];
        yield 'version' => [static fn (): CharacterConditions => new CharacterConditions('ada', version: -1), 'The number of instances and the version are 0 or more.'];
    }

    /**
     * @param callable(): CharacterConditions $build
     */
    #[DataProvider('invalidCharacters')]
    public function testInvalidCharacterConditionsAreRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains($message);
        $build();
    }

    public function testTheStoreKeepsAtTheNextVersion(): void
    {
        $store = new InMemoryConditionStore();
        self::assertNull($store->conditions('ada'));

        $kept = $store->save(new CharacterConditions('ada'));
        self::assertSame(1, $kept->version);
        self::assertSame($kept, $store->conditions('ada'));
        self::assertSame(2, $store->save($kept)->version);

        try {
            $store->save($kept);
            self::fail('A stale save was kept.');
        } catch (ConditionConflict $conditionConflict) {
            self::assertSame('The conditions of ada changed since they were read.', $conditionConflict->getMessage());
        }

        $this->expectException(ConditionConflict::class);
        $store->save(new CharacterConditions('ada'));
    }

    public function testIdentifiersHaveTheirShape(): void
    {
        self::assertSame('a', Identifier::check('a', 'x'));
        self::assertSame('A.b_c:d-9', Identifier::check('A.b_c:d-9', 'x'));
        self::assertSame(str_repeat('a', 128), Identifier::check(str_repeat('a', 128), 'x'));

        foreach (['', '9a', str_repeat('a', 129), "a\n", 'a b', 'a@b'] as $bad) {
            try {
                Identifier::check($bad, 'a thing');
                self::fail(\sprintf('"%s" was accepted.', $bad));
            } catch (InvalidCondition $invalid) {
                self::assertSame('The identifier of a thing is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.', $invalid->getMessage());
            }
        }
    }

    public function testMillisecondsReadAndWriteInstants(): void
    {
        $moment = new DateTimeImmutable('2027-01-01T12:00:00.250Z');
        self::assertSame(1_798_804_800_250, Millis::of($moment));
        self::assertSame('2027-01-01T12:00:00.250+00:00', Millis::at(1_798_804_800_250)->format('Y-m-d\TH:i:s.vP'));
    }

    public function testTheExceptionsShareTheirInterface(): void
    {
        self::assertInstanceOf(ConditionsException::class, InvalidCondition::because('x'));
        self::assertInstanceOf(ConditionsException::class, ConditionRefused::because('x'));
        self::assertSame('x', ConditionRefused::because('x')->getMessage());
        self::assertInstanceOf(ConditionsException::class, ConditionNotFound::definition('x'));
        self::assertSame('The character ada has no condition instance i1.', ConditionNotFound::instance('ada', 'i1')->getMessage());
        self::assertInstanceOf(ConditionsException::class, ConditionConflict::changed('ada'));
    }
}
