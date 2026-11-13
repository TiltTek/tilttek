<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Sets\UtcClock;
use Tilttek\Stickers\Contract\OwnershipStore;
use Tilttek\Stickers\Contract\PlacementModerator;
use Tilttek\Stickers\Contract\PlacementStore;
use Tilttek\Stickers\Contract\StickerCatalog;
use Tilttek\Stickers\Contract\SurfacePolicy;
use Tilttek\Stickers\Event\PlacementHidden;
use Tilttek\Stickers\Event\PlacementRemoved;
use Tilttek\Stickers\Event\PlacementRestored;
use Tilttek\Stickers\Event\StickerGranted;
use Tilttek\Stickers\Event\StickerPlaced;
use Tilttek\Stickers\Event\StickerRevoked;
use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Exception\StickerRefused;
use Tilttek\Stickers\Exception\UnknownPlacement;

/**
 * The stickers of a site: given to holders and taken back, placed on the surfaces the policy lets each holder use,
 * within the site's limits, moved and removed by their holder, hidden and restored by moderation, and shown to a
 * viewer who did not turn them off.
 *
 * The service keeps no state between two calls.
 */
final readonly class Stickers
{
    private ClockInterface $clock;

    public function __construct(
        private StickerCatalog $catalog,
        private OwnershipStore $ownerships,
        private PlacementStore $placements,
        private SurfacePolicy $surfaces,
        private Limits $limits = new Limits(),
        ?ClockInterface $clock = null,
        private ?EventDispatcherInterface $events = null,
        private ?PlacementModerator $moderator = null,
    ) {
        $this->clock = $clock ?? new UtcClock();
    }

    /**
     * Gives a sticker of the catalogue to a holder; returns false when they own it already.
     *
     * @throws StickerRefused when the sticker is not in the catalogue
     * @throws InvalidSticker when the holder is referenced by an e-mail address
     */
    public function grant(ResourceReference $holder, string $sticker): bool
    {
        Guard::reference($holder, 'holder');
        $this->sticker($sticker);

        if (!$this->ownerships->grant($holder, $sticker)) {
            return false;
        }

        $this->events?->dispatch(new StickerGranted($holder, $sticker));

        return true;
    }

    /**
     * Takes a sticker back from a holder, with every placement of it they made; returns false when they did not own
     * it.
     */
    public function revoke(ResourceReference $holder, string $sticker): bool
    {
        if (!$this->ownerships->revoke($holder, $sticker)) {
            return false;
        }

        $removed = 0;

        foreach ($this->placements->ofHolder($holder) as $placement) {
            if ($placement->sticker === $sticker && $this->placements->remove($placement->id)) {
                ++$removed;
            }
        }

        $this->events?->dispatch(new StickerRevoked($holder, $sticker, $removed));

        return true;
    }

    /**
     * The stickers a holder owns, in the order they were given; a sticker no longer in the catalogue is left out.
     *
     * @return list<Sticker>
     */
    public function owned(ResourceReference $holder): array
    {
        $owned = [];

        foreach ($this->ownerships->owned($holder) as $code) {
            $sticker = $this->catalog->get($code);

            if ($sticker instanceof Sticker) {
                $owned[] = $sticker;
            }
        }

        return $owned;
    }

    /**
     * Places a sticker the holder owns on a surface the policy lets them use, within the limits of the site; the
     * moderator, when there is one, may hide it at once.
     *
     * @throws StickerRefused when the sticker is unknown, retired or not owned, the surface not the holder's, the
     *                        transform outside the site's limits, or a limit reached
     * @throws InvalidSticker when the holder is referenced by an e-mail address
     */
    public function place(ResourceReference $holder, PlacementRequest $request): Placement
    {
        Guard::reference($holder, 'holder');
        $sticker = $this->sticker($request->sticker);

        if ($sticker->retired) {
            throw StickerRefused::because(\sprintf('The sticker "%s" is retired: it stays where it is placed, but is placed no more.', $sticker->code));
        }

        if (!$this->ownerships->owns($holder, $sticker->code)) {
            throw StickerRefused::because(\sprintf('The holder does not own the sticker "%s".', $sticker->code));
        }

        if (!$this->surfaces->allows($holder, $request->surface)) {
            throw StickerRefused::because(\sprintf('The holder may not place stickers on "%s".', $request->surface->toString()));
        }

        $this->limits->check($request->transform);

        if (\count($this->placements->onSurface($request->surface)) >= $this->limits->perSurface) {
            throw StickerRefused::because(\sprintf('A surface holds at most %d stickers on this site.', $this->limits->perSurface));
        }

        if (\count($this->placements->ofHolder($holder)) >= $this->limits->perHolder) {
            throw StickerRefused::because(\sprintf('A holder places at most %d stickers on this site.', $this->limits->perHolder));
        }

        $placement = new Placement(sodium_bin2hex(random_bytes(16)), $holder, $sticker->code, $request->surface, $request->transform, $this->clock->now());
        $reason = $this->moderator?->review($placement)->hideFor;

        if (null !== $reason) {
            $placement = $placement->hiddenFor($reason);
        }

        $this->placements->add($placement);
        $this->events?->dispatch(new StickerPlaced($placement->id, $holder, $sticker->code, $request->surface, $placement->isHidden()));

        return $placement;
    }

    /**
     * Moves one of the holder's placements, within the limits of the site.
     *
     * @throws UnknownPlacement when there is no such placement
     * @throws StickerRefused   when the placement is not the holder's, or the transform outside the site's limits
     */
    public function move(ResourceReference $holder, string $placement, Transform $transform): Placement
    {
        $moved = $this->ownPlacement($holder, $placement)->movedTo($this->limits->check($transform));
        $this->placements->replace($moved);

        return $moved;
    }

    /**
     * Removes one of the holder's placements.
     *
     * @throws UnknownPlacement when there is no such placement
     * @throws StickerRefused   when the placement is not the holder's
     */
    public function remove(ResourceReference $holder, string $placement): void
    {
        $this->ownPlacement($holder, $placement);
        $this->placements->remove($placement);
        $this->events?->dispatch(new PlacementRemoved($placement));
    }

    /**
     * Hides a placement for the reason moderation names; the application checks who may moderate.
     *
     * @throws UnknownPlacement when there is no such placement
     * @throws InvalidSticker   when the reason is not a code
     */
    public function hide(string $placement, string $reason): Placement
    {
        $hidden = $this->placement($placement)->hiddenFor($reason);
        $this->placements->replace($hidden);
        $this->events?->dispatch(new PlacementHidden($placement, $reason));

        return $hidden;
    }

    /**
     * Shows a hidden placement again; the application checks who may moderate.
     *
     * @throws UnknownPlacement when there is no such placement
     */
    public function restore(string $placement): Placement
    {
        $restored = $this->placement($placement)->restored();
        $this->placements->replace($restored);
        $this->events?->dispatch(new PlacementRestored($placement));

        return $restored;
    }

    /**
     * The placements a viewer sees on a surface, in the order they were placed: none when the viewer turned stickers
     * off, never a hidden one, and never one whose sticker left the catalogue.
     *
     * @return list<PlacementView>
     */
    public function onSurface(ResourceReference $surface, ViewerPreferences $viewer = new ViewerPreferences()): array
    {
        if (!$viewer->showStickers) {
            return [];
        }

        $views = [];

        foreach ($this->placements->onSurface($surface) as $placement) {
            $sticker = $this->catalog->get($placement->sticker);

            if ($sticker instanceof Sticker && !$placement->isHidden()) {
                $views[] = new PlacementView($placement, $sticker);
            }
        }

        return $views;
    }

    /**
     * Removes every placement of a surface, for a profile or a message deleted; returns how many.
     */
    public function eraseSurface(ResourceReference $surface): int
    {
        return $this->placements->eraseSurface($surface);
    }

    /**
     * Removes everything of a holder, for the erasure of a member: their placements and the stickers they own; returns
     * how many of each.
     *
     * @return array{placements: int, stickers: int}
     */
    public function eraseHolder(ResourceReference $holder): array
    {
        return ['placements' => $this->placements->eraseHolder($holder), 'stickers' => $this->ownerships->eraseHolder($holder)];
    }

    private function sticker(string $code): Sticker
    {
        return $this->catalog->get($code) ?? throw StickerRefused::because(\sprintf('The sticker "%s" is not in the catalogue.', substr($code, 0, 64)));
    }

    private function placement(string $id): Placement
    {
        return $this->placements->get($id) ?? throw UnknownPlacement::withId($id);
    }

    private function ownPlacement(ResourceReference $holder, string $id): Placement
    {
        $placement = $this->placement($id);

        if (!$placement->holder->equals($holder)) {
            throw StickerRefused::because("The placement is not the holder's.");
        }

        return $placement;
    }
}
