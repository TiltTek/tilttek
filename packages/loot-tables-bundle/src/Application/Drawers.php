<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\Exception\UnknownLoot;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootCatalog;
use Tilttek\LootTables\LootTable;

/**
 * The drawers an option of a commerce catalogue names to be drawn here, and the box each draws:
 *
 * - `loot-box:<key>`, on a draw option: the box of that key in the catalogue — the product declares its tables
 *   through it;
 * - `loot-text`, on a text option drawn among its entries: a box of one table, the entries of the option.
 */
final readonly class Drawers
{
    public const string BOX_PREFIX = 'loot-box:';

    public const string TEXT = 'loot-text';

    public function __construct(
        private LootCatalog $catalog,
    ) {
    }

    /**
     * The drawer an option names to draw the box of this key.
     */
    public static function box(string $key): string
    {
        return self::BOX_PREFIX.$key;
    }

    /**
     * Whether the drawer is a text's, or a box's the catalogue holds.
     */
    public function supports(string $drawer): bool
    {
        return self::TEXT === $drawer || (str_starts_with($drawer, self::BOX_PREFIX) && $this->catalog->hasBox(substr($drawer, \strlen(self::BOX_PREFIX))));
    }

    public function isText(string $drawer): bool
    {
        return self::TEXT === $drawer;
    }

    /**
     * The box a drawer draws: the catalogue's, or the list of a text.
     *
     * @param list<string>|null $texts the list of a text
     *
     * @throws UnknownLoot when the catalogue holds no such box
     * @throws InvalidLoot when the list of a text is not one a die draws from
     */
    public function boxOf(string $drawer, ?array $texts): LootBox
    {
        if (self::TEXT === $drawer) {
            return LootBox::single(LootTable::ofTexts('text', 'Text', $texts ?? []));
        }

        if (!str_starts_with($drawer, self::BOX_PREFIX)) {
            throw UnknownLoot::box($drawer);
        }

        return $this->catalog->box(substr($drawer, \strlen(self::BOX_PREFIX)));
    }
}
