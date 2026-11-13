<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Contract;

use Tilttek\DeckBuilder\DeckVersion;

/**
 * Where the versions of the decks are kept, never rewritten.
 */
interface DeckStore
{
    /**
     * Keeps a version; false, writing nothing, when the store holds that version of that deck.
     *
     * @phpstan-impure
     */
    public function add(DeckVersion $version): bool;

    /**
     * The version of that number, or null.
     */
    public function version(string $deck, int $version): ?DeckVersion;

    /**
     * The highest version of the deck, or null when it has none.
     */
    public function latest(string $deck): ?DeckVersion;

    /**
     * Every version of the deck, from the first to the latest.
     *
     * @return list<DeckVersion>
     */
    public function versions(string $deck): array;
}
