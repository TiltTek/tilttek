<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\SeasonalEvents\ContentKind;
use Tilttek\SeasonalEvents\Multiplier;

/**
 * What the seasons bring at a moment, for the packages that offer it: the quests a quest package opens, the items a
 * shop or a collection offers, the decorations a theme shows, the multiplier a ledger applies. A package reads this
 * port and never the seasons themselves.
 */
interface ActiveContent
{
    /**
     * The references of one kind on at a moment, each once.
     *
     * @return list<ResourceReference>
     */
    public function active(ContentKind $kind, ?DateTimeImmutable $moment = null): array;

    /**
     * Whether one reference is on at a moment.
     */
    public function isActive(ContentKind $kind, ResourceReference $reference, ?DateTimeImmutable $moment = null): bool;

    /**
     * The multiplier of a ledger at a moment, or null.
     */
    public function multiplier(string $ledger, ?DateTimeImmutable $moment = null): ?Multiplier;
}
