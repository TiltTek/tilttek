<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

/**
 * What a recipe does: combine items into new ones, or evolve one item into its next form — a hatched egg, a tempered
 * blade —, which an inventory may keep as the same object under its new item.
 */
enum RecipeKind: string
{
    case Combine = 'combine';
    case Evolve = 'evolve';
}
