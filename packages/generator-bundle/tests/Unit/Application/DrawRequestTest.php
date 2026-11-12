<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\GeneratorBundle\Application\DrawRequest;
use Tilttek\GeneratorBundle\Application\Exception\InvalidDrawRequest;

#[CoversClass(DrawRequest::class)]
#[CoversClass(InvalidDrawRequest::class)]
final class DrawRequestTest extends TestCase
{
    public function testItReadsTheCountTheUniquenessAndTheSeed(): void
    {
        self::assertEquals(new Draw(), DrawRequest::from([], 20));
        self::assertEquals(new Draw(20, true, 'x'), DrawRequest::from(['count' => '20', 'unique' => '1', 'seed' => 'x'], 20));
        self::assertEquals(new Draw(1, true), DrawRequest::from(['unique' => 'true'], 1));
        self::assertEquals(new Draw(3, false), DrawRequest::from(['count' => '3', 'unique' => '0'], 5));
        self::assertEquals(new Draw(1, false, str_repeat('é', 128)), DrawRequest::from(['seed' => str_repeat('é', 128)], 5));
        self::assertEquals(new Draw(2, state: ['day' => '3', 'weather' => 'rain']), DrawRequest::from(['count' => '2', 'state' => ['day' => '3', 'weather' => 'rain']], 5));
        self::assertEquals(new Draw(state: []), DrawRequest::from(['state' => []], 5));
    }

    public function testTheNameOfTheGeneratorIsUtf8(): void
    {
        self::assertSame('nom.épée', DrawRequest::generator('nom.épée'));

        $this->expectException(InvalidDrawRequest::class);
        $this->expectExceptionMessageIsOrContains('The name of the generator is UTF-8.');

        DrawRequest::generator("name\xFF");
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'count of zero' => [['count' => '0'], 'The count is a whole number from 1 to 5.'];
        yield 'count above the maximum' => [['count' => '6'], 'The count is a whole number from 1 to 5.'];
        yield 'count not a number' => [['count' => '2x'], 'The count is a whole number from 1 to 5.'];
        yield 'count negative' => [['count' => '-1'], 'The count is a whole number from 1 to 5.'];
        yield 'count as a list' => [['count' => ['2']], 'The count is a whole number from 1 to 5.'];
        yield 'unique unreadable' => [['unique' => 'yes'], 'Unique is 1, true, 0 or false.'];
        yield 'empty seed' => [['seed' => ''], 'The seed is a text of 1 to 128 characters, in UTF-8.'];
        yield 'long seed' => [['seed' => str_repeat('x', 129)], 'The seed is a text of 1 to 128 characters, in UTF-8.'];
        yield 'seed as a list' => [['seed' => ['x']], 'The seed is a text of 1 to 128 characters, in UTF-8.'];
        yield 'seed not in UTF-8' => [['seed' => "\xC3\x28"], 'The seed is a text of 1 to 128 characters, in UTF-8.'];
        yield 'state not in UTF-8' => [['state' => ['weather' => "rain\xFF"]], 'The state is a map of at most 32 names to texts of at most 1024 characters, in UTF-8.'];
        yield 'state as a text' => [['state' => 'rain'], 'The state is a map of names to texts, such as state[weather]=rain.'];
        yield 'state as a list' => [['state' => ['rain']], 'The state is a map of at most 32 names to texts of at most 1024 characters, in UTF-8.'];
        yield 'state with a nested map' => [['state' => ['a' => ['b' => 'c']]], 'The state is a map of at most 32 names to texts of at most 1024 characters, in UTF-8.'];
        yield 'state too long' => [['state' => ['a' => str_repeat('x', 1025)]], 'The state is a map of at most 32 names to texts of at most 1024 characters, in UTF-8.'];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('invalid')]
    public function testItRefusesWhatItCannotRead(array $parameters, string $message): void
    {
        $this->expectExceptionObject(InvalidDrawRequest::because($message));

        DrawRequest::from($parameters, 5);
    }

    public function testAStateItCannotReadKeepsTheReason(): void
    {
        try {
            DrawRequest::from(['state' => ['rain']], 5);
            self::fail('The state is read.');
        } catch (InvalidDrawRequest $invalidDrawRequest) {
            self::assertInstanceOf(InvalidDefinition::class, $invalidDrawRequest->getPrevious());
            self::assertSame(0, $invalidDrawRequest->getCode());
        }

        self::assertNull(InvalidDrawRequest::because('x')->getPrevious());
    }
}
