<?php

declare(strict_types=1);

/*
 * The fuzz target of the recipe books read from plain values, run by `phil-dev fuzz`: any JSON is either refused with
 * the library's own exceptions or gives a book that writes itself back to the same values, whose every recipe a
 * crafter previews for an empty inventory without crafting it.
 */

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\Exception\CraftingException;
use Tilttek\Crafting\RecipeBook;
use Tilttek\Crafting\Store\InMemoryInventory;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 8, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $book = RecipeBook::fromArray($data);
    } catch (CraftingException) {
        // The refusals the book promises for values it cannot read.
        return;
    }

    $written = $book->toArray();
    if (RecipeBook::fromArray($written)->toArray() !== $written) {
        throw new LogicException('A recipe book does not read back to itself.');
    }

    $crafter = new Crafter($book, new InMemoryInventory(), new MockClock());
    $holder = ResourceReference::of('member', '1');

    foreach ($book->recipes() as $recipe) {
        if ($crafter->preview($holder, $recipe->code)->isCraftable()) {
            throw new LogicException(sprintf('The recipe "%s" is craftable from nothing.', $recipe->code));
        }
    }
});
$config->setMaxLen(2048);
