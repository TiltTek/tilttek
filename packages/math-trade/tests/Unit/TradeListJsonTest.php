<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Exception\LimitExceeded;
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\Tests\Unit\Fixture\Lists;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

#[CoversClass(TradeListJson::class)]
final class TradeListJsonTest extends TestCase
{
    private const string DOCUMENT = <<<'JSON'
        {
            "format": "tilttek/math-trade",
            "version": 1,
            "participants": [
                {
                    "id": "alice",
                    "offers": [
                        {
                            "id": "a1",
                            "game": "café/azul",
                            "wants": [
                                "b1",
                                [
                                    "c1",
                                    "c2"
                                ]
                            ]
                        }
                    ]
                },
                {
                    "id": "bob",
                    "offers": [
                        {
                            "id": "b1",
                            "wants": []
                        }
                    ]
                },
                {
                    "id": "carol",
                    "offers": [
                        {
                            "id": "c1",
                            "wants": [
                                "a1"
                            ]
                        },
                        {
                            "id": "c2",
                            "wants": []
                        }
                    ]
                }
            ]
        }
        JSON;

    public function testWritesAListOnTheDocumentedFormat(): void
    {
        $list = Lists::of([
            'alice' => ['a1@café/azul' => ['b1', ['c1', 'c2']]],
            'bob' => ['b1' => []],
            'carol' => ['c1' => ['a1'], 'c2' => []],
        ]);

        self::assertSame(self::DOCUMENT, new TradeListJson()->encode($list));
    }

    public function testReadsTheListItWrites(): void
    {
        $json = new TradeListJson();
        $list = $json->decode(self::DOCUMENT);

        self::assertSame(self::DOCUMENT, $json->encode($list));
        self::assertSame('café/azul', $list->offer('a1')->game);
        self::assertNull($list->offer('b1')->game);
        self::assertEquals([new Want('b1'), new WantGroup(['c1', 'c2'])], $list->offer('a1')->wants);
        self::assertSame('carol', $list->ownerOf('c2'));
    }

    public function testReadsAnExplicitNullGame(): void
    {
        $list = new TradeListJson()->decode('{"format":"tilttek/math-trade","version":1,"participants":[{"id":"p","offers":[{"id":"o","game":null,"wants":[]}]}]}');

        self::assertNull($list->offer('o')->game);
    }

    public function testReadsAnEmptyList(): void
    {
        self::assertSame([], new TradeListJson()->decode('{"format":"tilttek/math-trade","version":1,"participants":[]}')->participants);
    }

    public function testReadsWithTheGivenLimits(): void
    {
        $limits = new Limits(participants: 3);

        self::assertSame($limits, new TradeListJson()->decode(self::DOCUMENT, $limits)->limits);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The trade list holds more than 2 participants.');

        new TradeListJson()->decode(self::DOCUMENT, new Limits(participants: 2));
    }

    public function testRefusesADocumentLongerThanTheLimit(): void
    {
        $json = new TradeListJson();

        self::assertInstanceOf(TradeList::class, $json->decode(self::DOCUMENT, new Limits(jsonBytes: \strlen(self::DOCUMENT))));

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The JSON document is longer than %d bytes.', \strlen(self::DOCUMENT) - 1));

        $json->decode(self::DOCUMENT, new Limits(jsonBytes: \strlen(self::DOCUMENT) - 1));
    }

    public function testReadsTheDeepestNestingOfTheFormat(): void
    {
        $list = new TradeListJson()->decode('{"format":"tilttek/math-trade","version":1,"participants":[{"id":"p","offers":[{"id":"o","wants":[["x","y"]]}]},{"id":"q","offers":[{"id":"x","wants":[]},{"id":"y","wants":[]}]}]}');

        self::assertEquals([new WantGroup(['x', 'y'])], $list->offer('o')->wants);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDocuments(): iterable
    {
        $participants = static fn (string $participants): string => '{"format":"tilttek/math-trade","version":1,"participants":'.$participants.'}';
        $offer = static fn (string $offer): string => $participants('[{"id":"p","offers":['.$offer.']}]');

        yield 'not JSON' => ['{', 'The trade list is not a JSON document of its format: Syntax error'];
        yield 'too deep' => [$offer('{"id":"o","wants":[[["x"]]]}'), 'The trade list is not a JSON document of its format: Maximum stack depth exceeded'];
        yield 'a list' => ['[1]', 'The document is a JSON object.'];
        yield 'a string' => ['"list"', 'The document is a JSON object.'];
        yield 'an empty object' => ['{}', 'The document lacks the key "format".'];
        yield 'an unknown key' => ['{"format":"tilttek/math-trade","version":1,"participants":[],"seed":"x"}', 'The document holds the unknown key "seed".'];
        yield 'a numeric key' => ['{"format":"tilttek/math-trade","version":1,"participants":[],"7":"x"}', 'The document holds the unknown key "7".'];
        yield 'no participants' => ['{"format":"tilttek/math-trade","version":1}', 'The document lacks the key "participants".'];
        yield 'another format' => ['{"format":"other","version":1,"participants":[]}', 'The document is not a trade list of the format "tilttek/math-trade", version 1.'];
        yield 'another version' => ['{"format":"tilttek/math-trade","version":2,"participants":[]}', 'The document is not a trade list of the format "tilttek/math-trade", version 1.'];
        yield 'a version as text' => ['{"format":"tilttek/math-trade","version":"1","participants":[]}', 'The document is not a trade list of the format "tilttek/math-trade", version 1.'];
        yield 'participants as an object' => [$participants('{"a":1}'), 'The list of participants is a JSON array.'];
        yield 'a participant as a list' => [$participants('[["p"]]'), 'A participant is a JSON object.'];
        yield 'a participant without offers' => [$participants('[{"id":"p"}]'), 'A participant lacks the key "offers".'];
        yield 'a participant identified by a number' => [$participants('[{"id":7,"offers":[]}]'), 'The identifier of a participant is a JSON string.'];
        yield 'offers as text' => [$participants('[{"id":"p","offers":"o"}]'), 'The list of offers of a participant is a JSON array.'];
        yield 'an offer without wishes' => [$offer('{"id":"o"}'), 'An offer lacks the key "wants".'];
        yield 'an offer without identifier' => [$offer('{"wants":[]}'), 'An offer lacks the key "id".'];
        yield 'an offer with an unknown key' => [$offer('{"id":"o","wants":[],"condition":"new"}'), 'An offer holds the unknown key "condition".'];
        yield 'an offer identified by a number' => [$offer('{"id":1,"wants":[]}'), 'The identifier of an offer is a JSON string.'];
        yield 'a game as a number' => [$offer('{"id":"o","game":3,"wants":[]}'), 'The game of an offer is a JSON string.'];
        yield 'wishes as an object' => [$offer('{"id":"o","wants":{"a":"x"}}'), 'The list of wishes of an offer is a JSON array.'];
        yield 'a wish as a number' => [$offer('{"id":"o","wants":[3]}'), 'A wish is the identifier of an offer, or a JSON array of identifiers for a group.'];
        yield 'a wish as an object' => [$offer('{"id":"o","wants":[{"a":"x"}]}'), 'A wish is the identifier of an offer, or a JSON array of identifiers for a group.'];
        yield 'a group holding a number' => [$offer('{"id":"o","wants":[["x",2]]}'), 'An offer of a group of wishes is a JSON string.'];
        yield 'a group of one' => [$offer('{"id":"o","wants":[["x"]]}'), 'A group of wishes names two offers or more; a single offer is a Want.'];
        yield 'a wish for nobody' => [$offer('{"id":"o","wants":["x"]}'), 'The offer "o" wishes for "x", which nobody offers.'];
    }

    #[DataProvider('invalidDocuments')]
    public function testRefusesADocument(string $json, string $message): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TradeListJson()->decode($json);
    }

    public function testASyntaxErrorEndsTheMessageWithAFullStop(): void
    {
        try {
            new TradeListJson()->decode('{');
            self::fail('A broken document was read.');
        } catch (InvalidTradeList $invalidTradeList) {
            self::assertStringStartsWith('The trade list is not a JSON document of its format: ', $invalidTradeList->getMessage());
            self::assertStringEndsWith('.', $invalidTradeList->getMessage());
            self::assertStringNotContainsString('..', $invalidTradeList->getMessage());
        }
    }
}
