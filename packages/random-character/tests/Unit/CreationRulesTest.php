<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Exception\UnknownRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\RulesCatalogue;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;

#[CoversClass(CreationRules::class)]
#[CoversClass(RulesCatalogue::class)]
#[CoversClass(InvalidRules::class)]
#[CoversClass(UnknownRules::class)]
final class CreationRulesTest extends TestCase
{
    public function testRulesKeepTheirParts(): void
    {
        $stats = new Stats(StatMethod::Each, ['str'], '3d6');
        $kin = Field::generated('kin', 'kin');
        $name = Field::generated('name', 'name.{kin}');
        $rules = new CreationRules('test.hero', 'test', $stats, [$kin, $name]);

        self::assertSame('test.hero', $rules->key);
        self::assertSame('test', $rules->system);
        self::assertSame($stats, $rules->stats);
        self::assertSame([$kin, $name], $rules->fields);
        self::assertTrue($rules->hasField('name'));
        self::assertFalse($rules->hasField('age'));
        self::assertSame($name, $rules->field('name'));

        $this->expectExceptionObject(InvalidRules::because('The creation rules "test.hero" have no field "age".'));
        $rules->field('age');
    }

    public function testRulesMayHaveStatsOrFieldsAlone(): void
    {
        self::assertSame([], new CreationRules('a', 'test', new Stats(StatMethod::Each, ['str'], '3d6'))->fields);
        self::assertNull(new CreationRules('a', 'test', fields: [Field::rolled('age', '1d6')])->stats);
        self::assertCount(CreationRules::MAX_FIELDS, new CreationRules('a', 'test', fields: array_map(static fn (int $i): Field => Field::rolled('f'.$i, '1d6'), range(1, CreationRules::MAX_FIELDS)))->fields);
    }

    /**
     * @return iterable<string, array{callable(): CreationRules, non-empty-string}>
     */
    public static function refused(): iterable
    {
        yield 'a wrong key' => [static fn (): CreationRules => new CreationRules('Hero', 'test', fields: [Field::rolled('age', '1d6')]), 'The key of creation rules "Hero" must be'];
        yield 'a wrong system' => [static fn (): CreationRules => new CreationRules('hero', 'Test', fields: [Field::rolled('age', '1d6')]), 'The key of a system "Test" must be'];
        yield 'nothing to roll' => [static fn (): CreationRules => new CreationRules('hero', 'test'), 'The creation rules "hero" have stats, fields, or both.'];
        yield 'too many fields' => [static fn (): CreationRules => new CreationRules('hero', 'test', fields: array_map(static fn (int $i): Field => Field::rolled('f'.$i, '1d6'), range(1, 65))), 'The creation rules "hero" have at most 64 fields.'];
        yield 'a field twice' => [static fn (): CreationRules => new CreationRules('hero', 'test', fields: [Field::rolled('age', '1d6'), Field::rolled('age', '1d4')]), 'The creation rules "hero" have two fields "age".'];
        yield 'a reference after' => [static fn (): CreationRules => new CreationRules('hero', 'test', fields: [Field::generated('name', 'name.{kin}'), Field::generated('kin', 'kin')]), 'The field "name" names its generator by the field "kin", which is not before it.'];
        yield 'a reference to itself' => [static fn (): CreationRules => new CreationRules('hero', 'test', fields: [Field::generated('name', 'name.{name}')]), 'The field "name" names its generator by the field "name", which is not before it.'];
        yield 'a reference to a list' => [static fn (): CreationRules => new CreationRules('hero', 'test', fields: [Field::generated('kin', 'kin', 2), Field::generated('name', 'name.{kin}')]), 'The field "name" names its generator by the field "kin", which gives a list.'];
    }

    /**
     * @param callable(): CreationRules $build
     * @param non-empty-string          $message
     */
    #[DataProvider('refused')]
    public function testRefusesWrongRules(callable $build, string $message): void
    {
        try {
            $build();
            self::fail('The rules are refused.');
        } catch (InvalidRules $invalidRules) {
            self::assertStringStartsWith($message, $invalidRules->getMessage());
        }
    }

    public function testACatalogueFindsRulesByKeyAndSystem(): void
    {
        $hero = new CreationRules('hero', 'test', fields: [Field::rolled('age', '1d6')]);
        $villain = new CreationRules('villain', 'other', fields: [Field::rolled('age', '1d6')]);
        $sidekick = new CreationRules('sidekick', 'test', fields: [Field::rolled('age', '1d6')]);
        $catalogue = new RulesCatalogue($hero, $villain, $sidekick);

        self::assertCount(3, $catalogue);
        self::assertTrue($catalogue->has('hero'));
        self::assertFalse($catalogue->has('nobody'));
        self::assertSame($villain, $catalogue->get('villain'));
        self::assertSame(['hero' => $hero, 'villain' => $villain, 'sidekick' => $sidekick], $catalogue->all());
        self::assertSame(['hero' => $hero, 'sidekick' => $sidekick], $catalogue->forSystem('test'));
        self::assertSame([], $catalogue->forSystem('none'));

        $this->expectExceptionObject(UnknownRules::key('nobody'));
        $catalogue->get('nobody');
    }

    public function testACatalogueRefusesTwoRulesWithOneKey(): void
    {
        $hero = new CreationRules('hero', 'test', fields: [Field::rolled('age', '1d6')]);

        $this->expectExceptionObject(InvalidRules::because('Two creation rules have the key "hero".'));

        new RulesCatalogue($hero, $hero);
    }

    public function testACatalogueHoldsAtMostItsLimit(): void
    {
        $rules = array_map(static fn (int $i): CreationRules => new CreationRules('r'.$i, 'test', fields: [Field::rolled('age', '1d6')]), range(1, RulesCatalogue::MAX_RULES + 1));

        self::assertCount(RulesCatalogue::MAX_RULES, new RulesCatalogue(...\array_slice($rules, 0, RulesCatalogue::MAX_RULES)));
        self::assertSame('No creation rules have the key "x".', UnknownRules::key('x')->getMessage());

        $this->expectExceptionObject(InvalidRules::because('A catalogue holds at most 1000 creation rules.'));

        new RulesCatalogue(...$rules);
    }
}
