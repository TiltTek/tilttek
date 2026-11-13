<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use Phil\Resource\ResourceReference;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Exception\UnknownSet;
use Tilttek\Sets\Holdings;
use Tilttek\Sets\Progress;
use Tilttek\Sets\Sets;
use Tilttek\Stickers\Contract\OwnershipStore;

/**
 * The sticker sheets and albums: sets of `tilttek/sets` whose members are sticker codes, measured against the stickers
 * a holder owns. Recording a sheet completes it once, and `tilttek/sets` tells its completion and its tiers with the
 * rewards they declare.
 */
final readonly class StickerSheets
{
    public function __construct(
        private Sets $sets,
        private OwnershipStore $ownerships,
    ) {
    }

    /**
     * Where a holder stands in a sheet, recording nothing.
     *
     * @throws UnknownSet when the sheet was never defined
     * @throws InvalidSet when the holder is referenced by an e-mail address
     */
    public function progress(ResourceReference $holder, string $sheet): Progress
    {
        return $this->sets->progress($sheet, $holder, Holdings::of($this->ownerships->owned($holder)));
    }

    /**
     * Where a holder stands in a sheet, its completion and its tiers recorded the first time they are reached — after a
     * sticker is granted, for instance.
     *
     * @throws UnknownSet when the sheet was never defined
     * @throws InvalidSet when the holder is referenced by an e-mail address
     */
    public function record(ResourceReference $holder, string $sheet): Progress
    {
        return $this->sets->record($sheet, $holder, Holdings::of($this->ownerships->owned($holder)));
    }
}
