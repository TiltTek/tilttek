<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * A pack of items drawn at random: its code, its translated name, how many items it holds, the sets it draws from —
 * every item when none is named —, and the weight of each rarity when it differs from the catalog's. Each item is drawn
 * on its own: a rarity by weight, then an item of that rarity, every item of it as likely as another.
 */
final readonly class Pack
{
    public const int MAX_SIZE = 50;

    /**
     * @param list<string>       $sets
     * @param array<string, int> $weights
     */
    public function __construct(
        public string $code,
        public Texts $name,
        public int $size = 1,
        public array $sets = [],
        public array $weights = [],
    ) {
        Names::check($code, 'pack');

        if ($size < 1 || $size > self::MAX_SIZE) {
            throw InvalidCollection::because(\sprintf('A pack holds 1 to %d items; "%s" holds %d.', self::MAX_SIZE, $code, $size));
        }

        foreach ($sets as $set) {
            Names::check($set, 'set');
        }

        foreach ($weights as $rarity => $weight) {
            new Rarity($rarity, $weight);
        }
    }
}
