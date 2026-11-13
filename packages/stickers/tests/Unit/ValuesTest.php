<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Exception\StickerRefused;
use Tilttek\Stickers\Guard;
use Tilttek\Stickers\Limits;
use Tilttek\Stickers\Placement;
use Tilttek\Stickers\PlacementView;
use Tilttek\Stickers\Sticker;
use Tilttek\Stickers\Transform;
use Tilttek\Stickers\Verdict;
use Tilttek\Stickers\ViewerPreferences;

#[CoversClass(Sticker::class)]
#[CoversClass(Guard::class)]
#[CoversClass(Transform::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Placement::class)]
#[CoversClass(PlacementView::class)]
#[CoversClass(Verdict::class)]
#[CoversClass(ViewerPreferences::class)]
#[CoversClass(InvalidSticker::class)]
#[CoversClass(StickerRefused::class)]
final class ValuesTest extends TestCase
{
    public function testAStickerKeepsItsTextsTrimmed(): void
    {
        $sticker = new Sticker('dragon', 'stickers/dragon.webp', '  A red dragon, sleeping  ', ' CC-BY-4.0 ', ' Art by A. Painter ');

        self::assertSame('dragon', $sticker->code);
        self::assertSame('stickers/dragon.webp', $sticker->image);
        self::assertSame('A red dragon, sleeping', $sticker->alt);
        self::assertSame('CC-BY-4.0', $sticker->licence);
        self::assertSame('Art by A. Painter', $sticker->attribution);
        self::assertFalse($sticker->retired);

        $retired = $sticker->retire();
        self::assertTrue($retired->retired);
        self::assertSame('A red dragon, sleeping', $retired->alt);
        self::assertSame('CC-BY-4.0', $retired->licence);
        self::assertSame('Art by A. Painter', $retired->attribution);
        self::assertSame('stickers/dragon.webp', $retired->image);
        self::assertSame('dragon', $retired->code);

        $bare = new Sticker('d', 'https://cdn.example.org/d.png', 'Dé');
        self::assertNull($bare->licence);
        self::assertNull($bare->attribution);
    }

    public function testTheBoundsOfAStickerAreAccepted(): void
    {
        $sticker = new Sticker('s', str_repeat('i', Sticker::MAX_IMAGE_LENGTH), str_repeat('é', Sticker::MAX_ALT_LENGTH), str_repeat('l', Sticker::MAX_LICENCE_LENGTH), str_repeat('a', Sticker::MAX_ATTRIBUTION_LENGTH));

        self::assertSame(250, mb_strlen($sticker->alt));
        self::assertSame(100, \strlen((string) $sticker->licence));
        self::assertSame(500, \strlen((string) $sticker->attribution));
        self::assertSame('datum/a.png', new Sticker('s', 'datum/a.png', 'a')->image);
        self::assertSame('img/javascript:x', new Sticker('s', 'img/javascript:x', 'a')->image);
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, ?string, string}>
     */
    public static function refusedStickers(): iterable
    {
        $image = 'The image of the sticker "s" is a reference of 1 to 2048 bytes without whitespace or control characters.';
        $scheme = 'The image of the sticker "s" is a reference to an image, never a script, inline data or a local file.';
        $alt = 'The text alternative of a sticker is 1 to 250 characters of UTF-8, without control characters.';

        yield 'code' => ['S', 'i.png', 'a', null, null, 'The code of a sticker is'];
        yield 'no image' => ['s', '', 'a', null, null, $image];
        yield 'long image' => ['s', str_repeat('i', 2049), 'a', null, null, $image];
        yield 'image with space' => ['s', 'a b.png', 'a', null, null, $image];
        yield 'image with control' => ['s', "a\x7F.png", 'a', null, null, $image];
        yield 'javascript' => ['s', 'JavaScript:alert(1)', 'a', null, null, $scheme];
        yield 'vbscript' => ['s', 'vbscript:x', 'a', null, null, $scheme];
        yield 'data' => ['s', 'data:image/png;base64,AAAA', 'a', null, null, $scheme];
        yield 'file' => ['s', 'file:///etc/passwd', 'a', null, null, $scheme];
        yield 'no alt' => ['s', 'i.png', '   ', null, null, $alt];
        yield 'long alt' => ['s', 'i.png', str_repeat('é', 251), null, null, $alt];
        yield 'alt not UTF-8' => ['s', 'i.png', "\xC3", null, null, $alt];
        yield 'alt with newline' => ['s', 'i.png', "a\nb", null, null, $alt];
        yield 'alt with delete' => ['s', 'i.png', "a\x7Fb", null, null, $alt];
        yield 'empty licence' => ['s', 'i.png', 'a', '', null, 'The licence of a sticker is 1 to 100 characters'];
        yield 'long licence' => ['s', 'i.png', 'a', str_repeat('l', 101), null, 'The licence of a sticker is 1 to 100 characters'];
        yield 'long attribution' => ['s', 'i.png', 'a', null, str_repeat('a', 501), 'The attribution of a sticker is 1 to 500 characters'];
    }

    #[DataProvider('refusedStickers')]
    public function testAStickerOutOfBoundsIsRefused(string $code, string $image, string $alt, ?string $licence, ?string $attribution, string $message): void
    {
        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Sticker($code, $image, $alt, $licence, $attribution);
    }

    public function testTheCodeShownInARefusalIsCutAt64Characters(): void
    {
        try {
            new Sticker('A'.str_repeat('a', 99), 'd.png', 'A dragon');
            self::fail('No exception.');
        } catch (InvalidSticker $invalidSticker) {
            self::assertStringContainsString('"A'.str_repeat('a', 63).'" is not.', $invalidSticker->getMessage());
        }
    }

    public function testATransformWithinItsBoundsIsKept(): void
    {
        self::assertSame(['x' => 0, 'y' => 1000, 'scale' => 100, 'rotation' => 0], new Transform(0, 1000)->toArray());
        self::assertSame(['x' => 1000, 'y' => 0, 'scale' => 10, 'rotation' => -180], new Transform(1000, 0, 10, -180)->toArray());
        self::assertSame(400, new Transform(1, 1, 400, 180)->scale);
        self::assertSame(180, new Transform(1, 1, 400, 180)->rotation);
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function refusedTransforms(): iterable
    {
        yield 'x below' => [-1, 0, 100, 0, 'A sticker sits at 0 to 1000 thousandths of its layer; -1, 0 is outside.'];
        yield 'x above' => [1001, 0, 100, 0, 'A sticker sits at 0 to 1000 thousandths of its layer; 1001, 0 is outside.'];
        yield 'y below' => [0, -1, 100, 0, 'A sticker sits at 0 to 1000 thousandths of its layer; 0, -1 is outside.'];
        yield 'y above' => [0, 1001, 100, 0, 'A sticker sits at 0 to 1000 thousandths of its layer; 0, 1001 is outside.'];
        yield 'scale below' => [0, 0, 9, 0, 'A sticker is scaled from 10 to 400 percent; 9 is outside.'];
        yield 'scale above' => [0, 0, 401, 0, 'A sticker is scaled from 10 to 400 percent; 401 is outside.'];
        yield 'rotation below' => [0, 0, 100, -181, 'A sticker turns from -180 to 180 degrees; -181 is outside.'];
        yield 'rotation above' => [0, 0, 100, 181, 'A sticker turns from -180 to 180 degrees; 181 is outside.'];
    }

    #[DataProvider('refusedTransforms')]
    public function testATransformOutOfBoundsIsRefused(int $x, int $y, int $scale, int $rotation, string $message): void
    {
        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Transform($x, $y, $scale, $rotation);
    }

    public function testTheDefaultLimitsAndTheirBounds(): void
    {
        $limits = new Limits();

        self::assertSame([12, 500, 50, 200, 45], [$limits->perSurface, $limits->perHolder, $limits->minScale, $limits->maxScale, $limits->maxRotation]);

        $widest = new Limits(Limits::MAX_PER_SURFACE, Limits::MAX_PER_HOLDER, Transform::MIN_SCALE, Transform::MAX_SCALE, Transform::MAX_ROTATION);
        self::assertSame(100, $widest->perSurface);
        self::assertSame(10_000, $widest->perHolder);

        $narrowest = new Limits(1, 1, 100, 100, 0);
        self::assertSame(1, $narrowest->perSurface);
        self::assertSame(1, $narrowest->perHolder);
        self::assertSame(0, $narrowest->maxRotation);
    }

    /**
     * @return iterable<string, array{int, int, int, int, int, string}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'no sticker per surface' => [0, 1, 50, 200, 45, 'A surface holds 1 to 100 stickers; 0 is outside.'];
        yield 'too many per surface' => [101, 1, 50, 200, 45, 'A surface holds 1 to 100 stickers; 101 is outside.'];
        yield 'no sticker per holder' => [1, 0, 50, 200, 45, 'A holder places 1 to 10000 stickers; 0 is outside.'];
        yield 'too many per holder' => [1, 10_001, 50, 200, 45, 'A holder places 1 to 10000 stickers; 10001 is outside.'];
        yield 'scale too small' => [1, 1, 9, 200, 45, 'The scales of a site are from 10 to 400 percent, the smallest first; 9 to 200 is not.'];
        yield 'scale too large' => [1, 1, 50, 401, 45, 'The scales of a site are from 10 to 400 percent, the smallest first; 50 to 401 is not.'];
        yield 'scales reversed' => [1, 1, 101, 100, 45, 'The scales of a site are from 10 to 400 percent, the smallest first; 101 to 100 is not.'];
        yield 'rotation negative' => [1, 1, 50, 200, -1, 'The rotation of a site is 0 to 180 degrees; -1 is outside.'];
        yield 'rotation too large' => [1, 1, 50, 200, 181, 'The rotation of a site is 0 to 180 degrees; 181 is outside.'];
    }

    #[DataProvider('refusedLimits')]
    public function testLimitsOutOfBoundsAreRefused(int $perSurface, int $perHolder, int $minScale, int $maxScale, int $maxRotation, string $message): void
    {
        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Limits($perSurface, $perHolder, $minScale, $maxScale, $maxRotation);
    }

    public function testTheLimitsCheckTheScaleAndTheRotation(): void
    {
        $limits = new Limits(minScale: 50, maxScale: 200, maxRotation: 45);
        $within = new Transform(1, 1, 50, -45);

        self::assertSame($within, $limits->check($within));
        self::assertSame(200, $limits->check(new Transform(1, 1, 200, 45))->scale);

        foreach ([[49, 0, 'A sticker is scaled from 50 to 200 percent on this site; 49 is outside.'], [201, 0, 'A sticker is scaled from 50 to 200 percent on this site; 201 is outside.'], [100, 46, 'A sticker turns at most 45 degrees either way on this site; 46 is outside.'], [100, -46, 'A sticker turns at most 45 degrees either way on this site; -46 is outside.']] as [$scale, $rotation, $message]) {
            try {
                $limits->check(new Transform(1, 1, $scale, $rotation));
                self::fail('No exception for '.$scale.', '.$rotation.'.');
            } catch (StickerRefused $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testAPlacementIsInUtcAndHiddenOnlyByAReason(): void
    {
        $placement = new Placement('p1', ResourceReference::of('member', '1'), 'dragon', ResourceReference::of('profile', '1'), new Transform(10, 20), new DateTimeImmutable('2026-11-13 09:30:00-05:00'));

        self::assertSame('2026-11-13T14:30:00+00:00', $placement->placedAt->format(\DATE_ATOM));
        self::assertSame('UTC', $placement->placedAt->getTimezone()->getName());
        self::assertFalse($placement->isHidden());

        $hidden = $placement->hiddenFor('reported');
        self::assertTrue($hidden->isHidden());
        self::assertSame('reported', $hidden->hiddenFor);

        $moved = $hidden->movedTo(new Transform(30, 40, 120, 10));
        self::assertSame('reported', $moved->hiddenFor);
        self::assertSame(['x' => 30, 'y' => 40, 'scale' => 120, 'rotation' => 10], $moved->transform->toArray());
        self::assertSame('p1', $moved->id);
        self::assertEquals($placement->placedAt, $moved->placedAt);
        self::assertSame('dragon', $moved->sticker);

        $restored = $moved->restored();
        self::assertNull($restored->hiddenFor);
        self::assertSame(30, $restored->transform->x);
        self::assertSame('profile:1', $restored->surface->toString());
        self::assertSame('member:1', $restored->holder->toString());
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, string}>
     */
    public static function refusedPlacements(): iterable
    {
        yield 'holder email' => ['a@b', '1', 'dragon', null, 'The holder "member:…" has an identifier with "@": it is referenced by a stable identifier, never by an e-mail address.'];
        yield 'holder encoded email' => ['a%40b', '1', 'dragon', null, 'The holder "member:…"'];
        yield 'holder upper encoded' => ['%40B', '1', 'dragon', null, 'The holder "member:…"'];
        yield 'surface email' => ['1', 'a@b', 'dragon', null, 'The surface "profile:…" has an identifier with "@"'];
        yield 'sticker' => ['1', '1', 'Dragon', null, 'The code of a sticker is'];
        yield 'reason' => ['1', '1', 'dragon', 'Reported', 'The code of a moderation reason is'];
    }

    #[DataProvider('refusedPlacements')]
    public function testAPlacementOutOfShapeIsRefused(string $holder, string $surface, string $sticker, ?string $reason, string $message): void
    {
        $this->expectException(InvalidSticker::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Placement('p', ResourceReference::of('member', $holder), $sticker, ResourceReference::of('profile', $surface), new Transform(0, 0), new DateTimeImmutable(), $reason);
    }

    public function testAViewGivesTheTextAlternativeAndTheCredit(): void
    {
        $placement = new Placement('p', ResourceReference::of('member', '1'), 'dragon', ResourceReference::of('profile', '1'), new Transform(0, 0), new DateTimeImmutable());

        $full = new PlacementView($placement, new Sticker('dragon', 'd.png', 'A red dragon', 'CC-BY-4.0', 'Art by A. Painter'));
        self::assertSame('A red dragon', $full->alt());
        self::assertSame('Art by A. Painter — CC-BY-4.0', $full->credit());
        self::assertSame($placement, $full->placement);

        self::assertSame('CC0-1.0', new PlacementView($placement, new Sticker('dragon', 'd.png', 'a', 'CC0-1.0'))->credit());
        self::assertSame('Art by B', new PlacementView($placement, new Sticker('dragon', 'd.png', 'a', null, 'Art by B'))->credit());
        self::assertNull(new PlacementView($placement, new Sticker('dragon', 'd.png', 'a'))->credit());
    }

    public function testAVerdictShowsOrHidesForAReason(): void
    {
        self::assertNull(Verdict::show()->hideFor);
        self::assertSame('offensive', Verdict::hide('offensive')->hideFor);

        $this->expectException(InvalidSticker::class);

        Verdict::hide('not a code');
    }

    public function testAViewerSeesStickersUnlessTheyTurnThemOff(): void
    {
        self::assertTrue(new ViewerPreferences()->showStickers);
        self::assertFalse(ViewerPreferences::stickersOff()->showStickers);
    }
}
