<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\PlacementRequest;
use Tilttek\Stickers\Transform;

#[CoversClass(PlacementRequest::class)]
final class PlacementRequestTest extends TestCase
{
    public function testARequestReadsWithItsDefaults(): void
    {
        $request = PlacementRequest::fromJson('{"sticker":"dragon","surface":"message:9f:2","x":500,"y":250}');

        self::assertSame('dragon', $request->sticker);
        self::assertSame('message', $request->surface->key);
        self::assertSame('9f:2', $request->surface->id);
        self::assertSame(['x' => 500, 'y' => 250, 'scale' => 100, 'rotation' => 0], $request->transform->toArray());

        $full = PlacementRequest::fromArray(['sticker' => 'd', 'surface' => 'profile:1', 'x' => 0, 'y' => 1000, 'scale' => 150, 'rotation' => -30]);
        self::assertSame(['x' => 0, 'y' => 1000, 'scale' => 150, 'rotation' => -30], $full->transform->toArray());
    }

    public function testARequestBuiltDirectlyChecksItsCodeAndSurface(): void
    {
        self::assertSame('d', new PlacementRequest('d', ResourceReference::of('profile', '1'), new Transform(0, 0))->sticker);

        try {
            new PlacementRequest('D', ResourceReference::of('profile', '1'), new Transform(0, 0));
            self::fail('No exception.');
        } catch (InvalidSticker) {
        }

        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains('The surface "profile:…" has an identifier with "@"');

        new PlacementRequest('d', ResourceReference::of('profile', 'a@b'), new Transform(0, 0));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        yield 'not JSON' => ['{', 'The JSON of a placement request does not read: Syntax error'];
        yield 'nested' => ['{"sticker":{"a":1}}', 'The JSON of a placement request does not read: Maximum stack depth exceeded'];
        yield 'a list' => ['["d"]', 'A placement request is an object of keys.'];
        yield 'a number' => ['3', 'A placement request is an object of keys.'];
        yield 'empty' => ['[]', 'A placement request names its sticker and its surface as strings.'];
        yield 'unknown key' => ['{"sticker":"d","surface":"p:1","x":1,"y":1,"z":1}', 'A placement request has no key "z".'];
        yield 'numeric key' => ['{"sticker":"d","surface":"p:1","x":1,"y":1,"0":1}', 'A placement request has no key "0".'];
        yield 'sticker not a string' => ['{"sticker":1,"surface":"p:1","x":1,"y":1}', 'A placement request names its sticker and its surface as strings.'];
        yield 'surface missing' => ['{"sticker":"d","x":1,"y":1}', 'A placement request names its sticker and its surface as strings.'];
        yield 'surface not a reference' => ['{"sticker":"d","surface":"profile","x":1,"y":1}', 'The surface of a placement request is a resource reference: "profile" is not a resource reference of the form "kind:identifier".'];
        yield 'surface kind' => ['{"sticker":"d","surface":"Profile:1","x":1,"y":1}', 'The surface of a placement request is a resource reference: A resource kind'];
        yield 'x missing' => ['{"sticker":"d","surface":"p:1","y":1}', 'The "x" of a placement request is an integer.'];
        yield 'y a string' => ['{"sticker":"d","surface":"p:1","x":1,"y":"1"}', 'The "y" of a placement request is an integer.'];
        yield 'scale null' => ['{"sticker":"d","surface":"p:1","x":1,"y":1,"scale":null}', 'The "scale" of a placement request is an integer.'];
        yield 'rotation float' => ['{"sticker":"d","surface":"p:1","x":1,"y":1,"rotation":1.5}', 'The "rotation" of a placement request is an integer.'];
        yield 'out of the layer' => ['{"sticker":"d","surface":"p:1","x":1001,"y":1}', 'A sticker sits at 0 to 1000 thousandths of its layer'];
        yield 'sticker code' => ['{"sticker":"D","surface":"p:1","x":1,"y":1}', 'The code of a sticker is'];
        yield 'long key' => ['{"'.str_repeat('k', 80).'":1}', 'A placement request has no key "'.str_repeat('k', 64).'".'];
    }

    #[DataProvider('refusedJson')]
    public function testJsonThatIsNotARequestIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains($message);

        PlacementRequest::fromJson($json);
    }

    public function testJsonLongerThanTheBoundIsRefused(): void
    {
        $head = '{"sticker":"d","surface":"p:1","x":1,"y":1';
        self::assertSame('d', PlacementRequest::fromJson($head.str_repeat(' ', PlacementRequest::MAX_JSON_LENGTH - \strlen($head) - 1).'}')->sticker);

        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains('The JSON of a placement request is at most 4096 bytes.');

        PlacementRequest::fromJson($head.str_repeat(' ', PlacementRequest::MAX_JSON_LENGTH - \strlen($head)).'}');
    }

    public function testAValueThatIsNotAnArrayIsRefused(): void
    {
        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains('A placement request is an object of keys.');

        PlacementRequest::fromArray('dragon');
    }
}
