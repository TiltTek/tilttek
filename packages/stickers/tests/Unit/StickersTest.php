<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Stickers\Event\PlacementHidden;
use Tilttek\Stickers\Event\PlacementRemoved;
use Tilttek\Stickers\Event\PlacementRestored;
use Tilttek\Stickers\Event\StickerGranted;
use Tilttek\Stickers\Event\StickerPlaced;
use Tilttek\Stickers\Event\StickerRevoked;
use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Exception\StickerRefused;
use Tilttek\Stickers\Exception\UnknownPlacement;
use Tilttek\Stickers\Limits;
use Tilttek\Stickers\Placement;
use Tilttek\Stickers\PlacementRequest;
use Tilttek\Stickers\PlacementView;
use Tilttek\Stickers\Policy\InMemorySurfacePolicy;
use Tilttek\Stickers\Sticker;
use Tilttek\Stickers\Stickers;
use Tilttek\Stickers\Store\InMemoryOwnershipStore;
use Tilttek\Stickers\Store\InMemoryPlacementStore;
use Tilttek\Stickers\Store\InMemoryStickerCatalog;
use Tilttek\Stickers\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Stickers\Tests\Unit\Fixture\HidingModerator;
use Tilttek\Stickers\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Stickers\Transform;
use Tilttek\Stickers\ViewerPreferences;

#[CoversClass(Stickers::class)]
#[CoversClass(StickerGranted::class)]
#[CoversClass(StickerRevoked::class)]
#[CoversClass(StickerPlaced::class)]
#[CoversClass(PlacementHidden::class)]
#[CoversClass(PlacementRestored::class)]
#[CoversClass(PlacementRemoved::class)]
#[CoversClass(UnknownPlacement::class)]
final class StickersTest extends TestCase
{
    private InMemoryOwnershipStore $ownerships;

    private InMemoryPlacementStore $placements;

    private InMemorySurfacePolicy $surfaces;

    private RecordingDispatcher $events;

    private ResourceReference $holder;

    private ResourceReference $profile;

    #[Override]
    protected function setUp(): void
    {
        $this->ownerships = new InMemoryOwnershipStore();
        $this->placements = new InMemoryPlacementStore();
        $this->surfaces = new InMemorySurfacePolicy();
        $this->events = new RecordingDispatcher();
        $this->holder = ResourceReference::of('member', '1042');
        $this->profile = ResourceReference::of('profile', '1042');
        $this->surfaces->assign($this->profile, $this->holder);
    }

    private function stickers(Limits $limits = new Limits(), ?HidingModerator $moderator = null): Stickers
    {
        $catalog = new InMemoryStickerCatalog([
            new Sticker('dragon', 'stickers/dragon.webp', 'A red dragon', 'CC-BY-4.0', 'Art by A. Painter'),
            new Sticker('d20', 'stickers/d20.webp', 'A twenty-sided die'),
            new Sticker('old', 'stickers/old.webp', 'An old banner', retired: true),
        ]);

        return new Stickers($catalog, $this->ownerships, $this->placements, $this->surfaces, $limits, new FrozenClock(), $this->events, $moderator);
    }

    private function request(string $sticker = 'dragon', ?ResourceReference $surface = null, int $x = 500): PlacementRequest
    {
        return new PlacementRequest($sticker, $surface ?? $this->profile, new Transform($x, 100));
    }

    public function testAStickerIsGrantedOnceAndTold(): void
    {
        $stickers = $this->stickers();

        self::assertTrue($stickers->grant($this->holder, 'dragon'));
        self::assertFalse($stickers->grant($this->holder, 'dragon'));
        self::assertTrue($stickers->grant($this->holder, 'old'));
        self::assertSame(['dragon', 'old'], array_map(static fn (Sticker $sticker): string => $sticker->code, $stickers->owned($this->holder)));
        self::assertCount(2, $this->events->events);
        self::assertInstanceOf(StickerGranted::class, $this->events->events[0]);
        self::assertSame('dragon', $this->events->events[0]->sticker);
        self::assertSame($this->holder, $this->events->events[0]->holder);
    }

    public function testAStickerOutsideTheCatalogueIsNeitherGrantedNorPlaced(): void
    {
        $stickers = $this->stickers();

        try {
            $stickers->grant($this->holder, 'unicorn');
            self::fail('No exception.');
        } catch (StickerRefused $stickerRefused) {
            self::assertSame('The sticker "unicorn" is not in the catalogue.', $stickerRefused->getMessage());
        }

        $this->expectException(StickerRefused::class);

        $stickers->place($this->holder, $this->request('unicorn'));
    }

    public function testAHolderReferencedByAnEmailIsRefused(): void
    {
        $stickers = $this->stickers();

        try {
            $stickers->grant(ResourceReference::of('member', 'a@b'), 'dragon');
            self::fail('No exception.');
        } catch (InvalidSticker) {
        }

        self::assertSame([], $this->events->events);

        $this->expectException(InvalidSticker::class);

        $stickers->place(ResourceReference::of('member', 'a@b'), $this->request());
    }

    public function testAStickerOwnedIsPlacedOnTheHoldersSurfaceAndShown(): void
    {
        $stickers = $this->stickers();
        $stickers->grant($this->holder, 'dragon');
        $stickers->grant($this->holder, 'd20');

        $first = $stickers->place($this->holder, $this->request());
        $second = $stickers->place($this->holder, $this->request('d20', x: 900));

        self::assertSame(32, \strlen($first->id));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first->id);
        self::assertNotSame($first->id, $second->id);
        self::assertSame('2026-11-13T14:30:00+00:00', $first->placedAt->format(\DATE_ATOM));
        self::assertFalse($first->isHidden());

        $views = $stickers->onSurface($this->profile);
        self::assertSame([$first->id, $second->id], array_map(static fn (PlacementView $view): string => $view->placement->id, $views));
        self::assertSame('A red dragon', $views[0]->alt());
        self::assertSame('Art by A. Painter — CC-BY-4.0', $views[0]->credit());
        self::assertSame([], $stickers->onSurface($this->profile, ViewerPreferences::stickersOff()));
        self::assertSame([], $stickers->onSurface(ResourceReference::of('profile', '7')));

        $placed = $this->events->events[2];
        self::assertInstanceOf(StickerPlaced::class, $placed);
        self::assertSame($first->id, $placed->placement);
        self::assertSame('dragon', $placed->sticker);
        self::assertSame($this->holder, $placed->holder);
        self::assertSame($this->profile, $placed->surface);
        self::assertFalse($placed->hidden);
    }

    public function testAPlacementIsRefusedWhenTheRulesSayNo(): void
    {
        $stickers = $this->stickers();
        $stickers->grant($this->holder, 'old');

        $cases = [
            'The sticker "old" is retired: it stays where it is placed, but is placed no more.' => $this->request('old'),
            'The holder does not own the sticker "dragon".' => $this->request(),
        ];

        foreach ($cases as $message => $request) {
            try {
                $stickers->place($this->holder, $request);
                self::fail('No exception for '.$message);
            } catch (StickerRefused $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $stickers->grant($this->holder, 'dragon');

        try {
            $stickers->place($this->holder, $this->request(surface: ResourceReference::of('profile', '7')));
            self::fail('No exception.');
        } catch (StickerRefused $stickerRefused) {
            self::assertSame('The holder may not place stickers on "profile:7".', $stickerRefused->getMessage());
        }

        try {
            $stickers->place($this->holder, new PlacementRequest('dragon', $this->profile, new Transform(1, 1, 300)));
            self::fail('No exception.');
        } catch (StickerRefused $stickerRefused) {
            self::assertStringStartsWith('A sticker is scaled from 50 to 200 percent on this site', $stickerRefused->getMessage());
        }

        self::assertSame([], $this->placements->ofHolder($this->holder));
    }

    public function testTheLimitsOfASurfaceAndOfAHolderAreKept(): void
    {
        $message = ResourceReference::of('message', '9');
        $this->surfaces->assign($message, $this->holder);
        $stickers = $this->stickers(new Limits(perSurface: 2, perHolder: 3));
        $stickers->grant($this->holder, 'dragon');

        $stickers->place($this->holder, $this->request());

        $hidden = $stickers->place($this->holder, $this->request());
        $stickers->hide($hidden->id, 'reported');

        try {
            $stickers->place($this->holder, $this->request());
            self::fail('No exception.');
        } catch (StickerRefused $stickerRefused) {
            self::assertSame('A surface holds at most 2 stickers on this site.', $stickerRefused->getMessage());
        }

        $stickers->place($this->holder, $this->request(surface: $message));

        $this->expectException(StickerRefused::class);
        $this->expectExceptionMessageIsOrContains('A holder places at most 3 stickers on this site.');

        $stickers->place($this->holder, $this->request(surface: $message));
    }

    public function testTheModeratorMayHideANewPlacement(): void
    {
        $moderator = new HidingModerator(['d20']);
        $stickers = $this->stickers(moderator: $moderator);
        $stickers->grant($this->holder, 'dragon');
        $stickers->grant($this->holder, 'd20');

        $shown = $stickers->place($this->holder, $this->request());
        $hidden = $stickers->place($this->holder, $this->request('d20'));

        self::assertFalse($shown->isHidden());
        self::assertSame('review', $hidden->hiddenFor);
        self::assertSame('review', $this->placements->get($hidden->id)?->hiddenFor);
        self::assertCount(2, $moderator->reviewed);
        self::assertSame([$shown->id], array_map(static fn (PlacementView $view): string => $view->placement->id, $stickers->onSurface($this->profile)));

        $placed = $this->events->events[3];
        self::assertInstanceOf(StickerPlaced::class, $placed);
        self::assertTrue($placed->hidden);

        $restored = $stickers->restore($hidden->id);
        self::assertFalse($restored->isHidden());
        self::assertCount(2, $stickers->onSurface($this->profile));
        self::assertInstanceOf(PlacementRestored::class, $this->events->events[4]);
        self::assertSame($hidden->id, $this->events->events[4]->placement);
    }

    public function testModerationHidesAndRestores(): void
    {
        $stickers = $this->stickers();
        $stickers->grant($this->holder, 'dragon');

        $placement = $stickers->place($this->holder, $this->request());

        $hidden = $stickers->hide($placement->id, 'offensive');
        self::assertSame('offensive', $hidden->hiddenFor);
        self::assertTrue($this->placements->get($placement->id)?->isHidden());
        self::assertSame([], $stickers->onSurface($this->profile));
        $event = $this->events->events[2];
        self::assertInstanceOf(PlacementHidden::class, $event);
        self::assertSame($placement->id, $event->placement);
        self::assertSame('offensive', $event->reason);

        self::assertFalse($stickers->restore($placement->id)->isHidden());
        self::assertFalse($this->placements->get($placement->id)?->isHidden());

        try {
            $stickers->hide($placement->id, 'Not A Code');
            self::fail('No exception.');
        } catch (InvalidSticker) {
        }

        $this->expectException(UnknownPlacement::class);
        $this->expectExceptionMessageIsOrContains('There is no placement "missing".');

        $stickers->hide('missing', 'offensive');
    }

    public function testAnUnknownPlacementIsSaidWhenRestored(): void
    {
        $this->expectException(UnknownPlacement::class);
        $this->expectExceptionMessageIsOrContains('There is no placement "'.str_repeat('x', 64).'".');

        $this->stickers()->restore(str_repeat('x', 100));
    }

    public function testOnlyItsHolderMovesOrRemovesAPlacement(): void
    {
        $stickers = $this->stickers();
        $stickers->grant($this->holder, 'dragon');

        $placement = $stickers->place($this->holder, $this->request());
        $other = ResourceReference::of('member', '7');

        $moved = $stickers->move($this->holder, $placement->id, new Transform(10, 20, 150, -30));
        self::assertSame(['x' => 10, 'y' => 20, 'scale' => 150, 'rotation' => -30], $moved->transform->toArray());
        self::assertSame(10, $this->placements->get($placement->id)?->transform->x);

        foreach ([static fn (Stickers $stickers): Placement => $stickers->move($other, $placement->id, new Transform(1, 1)), static fn (Stickers $stickers) => $stickers->remove($other, $placement->id)] as $attempt) {
            try {
                $attempt($stickers);
                self::fail('No exception.');
            } catch (StickerRefused $exception) {
                self::assertSame("The placement is not the holder's.", $exception->getMessage());
            }
        }

        try {
            $stickers->move($this->holder, $placement->id, new Transform(1, 1, 100, 90));
            self::fail('No exception.');
        } catch (StickerRefused) {
        }

        self::assertSame(['x' => 10, 'y' => 20, 'scale' => 150, 'rotation' => -30], $stickers->onSurface($this->profile)[0]->placement->transform->toArray());

        $stickers->remove($this->holder, $placement->id);
        self::assertNull($this->placements->get($placement->id));
        $removed = $this->events->events[\count($this->events->events) - 1];
        self::assertInstanceOf(PlacementRemoved::class, $removed);
        self::assertSame($placement->id, $removed->placement);

        $this->expectException(UnknownPlacement::class);

        $stickers->remove($this->holder, $placement->id);
    }

    public function testRevokingAStickerRemovesItsPlacements(): void
    {
        $stickers = $this->stickers();
        $stickers->grant($this->holder, 'dragon');
        $stickers->grant($this->holder, 'd20');
        $stickers->place($this->holder, $this->request());
        $stickers->place($this->holder, $this->request());

        $kept = $stickers->place($this->holder, $this->request('d20'));

        self::assertTrue($stickers->revoke($this->holder, 'dragon'));
        self::assertFalse($stickers->revoke($this->holder, 'dragon'));
        self::assertSame([$kept->id], array_map(static fn (PlacementView $view): string => $view->placement->id, $stickers->onSurface($this->profile)));

        $revoked = $this->events->events[\count($this->events->events) - 1];
        self::assertInstanceOf(StickerRevoked::class, $revoked);
        self::assertSame(2, $revoked->placementsRemoved);
        self::assertSame('dragon', $revoked->sticker);
        self::assertSame($this->holder, $revoked->holder);
    }

    public function testAStickerThatLeftTheCatalogueIsNeitherOwnedNorShown(): void
    {
        $this->ownerships->grant($this->holder, 'gone');
        $this->ownerships->grant($this->holder, 'dragon');

        $this->placements->add(new Placement('p', $this->holder, 'gone', $this->profile, new Transform(0, 0), new DateTimeImmutable()));
        $stickers = $this->stickers();

        self::assertSame(['dragon'], array_map(static fn (Sticker $sticker): string => $sticker->code, $stickers->owned($this->holder)));
        self::assertSame([], $stickers->onSurface($this->profile));
    }

    public function testErasingAHolderOrASurface(): void
    {
        $message = ResourceReference::of('message', '9');
        $this->surfaces->assign($message, $this->holder);
        $stickers = $this->stickers();
        $stickers->grant($this->holder, 'dragon');
        $stickers->grant($this->holder, 'd20');
        $stickers->place($this->holder, $this->request());
        $stickers->place($this->holder, $this->request(surface: $message));
        $stickers->place($this->holder, $this->request(surface: $message));

        self::assertSame(2, $stickers->eraseSurface($message));
        self::assertSame(0, $stickers->eraseSurface($message));
        self::assertSame(['placements' => 1, 'stickers' => 2], $stickers->eraseHolder($this->holder));
        self::assertSame(['placements' => 0, 'stickers' => 0], $stickers->eraseHolder($this->holder));
    }

    public function testTheDefaultsAreTheSiteLimitsTheUtcClockAndNoListener(): void
    {
        $stickers = new Stickers(new InMemoryStickerCatalog([new Sticker('dragon', 'd.png', 'A dragon')]), $this->ownerships, $this->placements, $this->surfaces);
        $stickers->grant($this->holder, 'dragon');

        $placement = $stickers->place($this->holder, $this->request());
        self::assertSame('UTC', $placement->placedAt->getTimezone()->getName());

        $this->expectException(StickerRefused::class);

        $stickers->place($this->holder, new PlacementRequest('dragon', $this->profile, new Transform(1, 1, 100, 46)));
    }

    public function testEveryChangeWorksWithoutAListener(): void
    {
        $stickers = new Stickers(new InMemoryStickerCatalog([new Sticker('dragon', 'd.png', 'A dragon')]), $this->ownerships, $this->placements, $this->surfaces);
        $stickers->grant($this->holder, 'dragon');

        $first = $stickers->place($this->holder, $this->request());
        $second = $stickers->place($this->holder, $this->request());

        self::assertTrue($stickers->hide($first->id, 'offensive')->isHidden());
        self::assertFalse($stickers->restore($first->id)->isHidden());

        $stickers->remove($this->holder, $second->id);
        self::assertTrue($stickers->revoke($this->holder, 'dragon'));
    }

    public function testTheCodeShownInARefusalIsCutAt64Characters(): void
    {
        $stickers = $this->stickers();

        try {
            $stickers->grant($this->holder, str_repeat('x', 100));
            self::fail('No exception.');
        } catch (StickerRefused $stickerRefused) {
            self::assertSame(\sprintf('The sticker "%s" is not in the catalogue.', str_repeat('x', 64)), $stickerRefused->getMessage());
        }
    }
}
