<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\RandomCharacter\Exception\InvalidEdit;
use Tilttek\RandomCharacter\RandomCharacter;

#[CoversClass(RandomCharacter::class)]
#[CoversClass(InvalidEdit::class)]
final class RandomCharacterTest extends TestCase
{
    private function character(): RandomCharacter
    {
        return new RandomCharacter('hero', 'test', 'seed', ['str' => 12, 'dex' => 9], ['name' => 'Aerin', 'traits' => ['brave', 'wry'], 'blank' => null], [new Attribution('Kin book', 'owned')]);
    }

    public function testACharacterGivesItsValues(): void
    {
        $character = $this->character();

        self::assertSame(12, $character->stat('str'));
        self::assertSame('Aerin', $character->field('name'));
        self::assertNull($character->field('blank'));
        self::assertFalse($character->isEdited());
        self::assertSame([
            'rules' => 'hero',
            'system' => 'test',
            'seed' => 'seed',
            'stats' => ['str' => 12, 'dex' => 9],
            'fields' => ['name' => 'Aerin', 'traits' => ['brave', 'wry'], 'blank' => null],
            'attributions' => [['source' => 'Kin book', 'licence' => 'owned']],
            'edited' => [],
        ], $character->toArray());
    }

    public function testAnUnknownStatIsRefused(): void
    {
        $this->expectExceptionObject(InvalidEdit::because('The character has no attribute "con".'));

        $this->character()->stat('con');
    }

    public function testAnUnknownFieldIsRefused(): void
    {
        $this->expectExceptionObject(InvalidEdit::because('The character has no field "age".'));

        $this->character()->field('age');
    }

    public function testAnEditGivesANewCharacterMarkedOnce(): void
    {
        $character = $this->character();
        $edited = $character->withStat('str', 1_000_000)->withStat('str', -1_000_000)->withField('name', 'Lithil')->withField('traits', ['bold', 3])->withField('blank', 4);

        self::assertSame(12, $character->stat('str'));
        self::assertSame(-1_000_000, $edited->stat('str'));
        self::assertSame(9, $edited->stat('dex'));
        self::assertSame('Lithil', $edited->field('name'));
        self::assertSame(['bold', 3], $edited->field('traits'));
        self::assertSame(4, $edited->field('blank'));
        self::assertSame(['stats.str', 'fields.name', 'fields.traits', 'fields.blank'], $edited->edited);
        self::assertTrue($edited->isEdited());
        self::assertSame('seed', $edited->seed);
        self::assertSame('hero', $edited->rules);
        self::assertSame('test', $edited->system);
        self::assertEquals($character->attributions, $edited->attributions);
        self::assertSame(['stats.str'], $character->withStat('str', 3)->edited);
        self::assertSame(['fields.name'], $character->withField('name', str_repeat('é', RandomCharacter::TEXT_LENGTH))->edited);
        self::assertSame(['name' => 'Aerin', 'traits' => [], 'blank' => null], $character->withField('traits', [])->fields);
        $traits = $character->withField('traits', array_fill(0, 16, 'x'))->field('traits');
        self::assertIsArray($traits);
        self::assertCount(16, $traits);
    }

    /**
     * @return iterable<string, array{callable(RandomCharacter): RandomCharacter, string}>
     */
    public static function refused(): iterable
    {
        yield 'an unknown stat' => [static fn (RandomCharacter $c): RandomCharacter => $c->withStat('con', 3), 'The character has no attribute "con".'];
        yield 'a stat too high' => [static fn (RandomCharacter $c): RandomCharacter => $c->withStat('str', 1_000_001), 'An attribute lies from -1000000 to 1000000.'];
        yield 'a stat too low' => [static fn (RandomCharacter $c): RandomCharacter => $c->withStat('str', -1_000_001), 'An attribute lies from -1000000 to 1000000.'];
        yield 'an unknown field' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('age', 3), 'The character has no field "age".'];
        yield 'a text too long' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('name', str_repeat('a', 4097)), 'A field holds numbers, or texts in UTF-8 of at most 4096 characters.'];
        yield 'a text not UTF-8' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('name', "\xFF"), 'A field holds numbers, or texts in UTF-8 of at most 4096 characters.'];
        yield 'a list too long' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('traits', array_fill(0, 17, 'x')), 'A field holds a list of at most 16 values.'];
        yield 'a map' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('traits', ['a' => 'x']), 'A field holds a list of at most 16 values.'];
        yield 'a list of lists' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('traits', [['x']]), 'A field holds numbers, or texts in UTF-8 of at most 4096 characters.'];
        yield 'a list with a long text' => [static fn (RandomCharacter $c): RandomCharacter => $c->withField('traits', ['ok', str_repeat('a', 4097)]), 'A field holds numbers, or texts in UTF-8 of at most 4096 characters.'];
    }

    /**
     * @param callable(RandomCharacter): RandomCharacter $edit
     */
    #[DataProvider('refused')]
    public function testRefusesWrongEdits(callable $edit, string $message): void
    {
        $this->expectExceptionObject(InvalidEdit::because($message));

        $edit($this->character());
    }
}
