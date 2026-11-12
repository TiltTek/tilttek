<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A map of cells — the buildings of a village, the terrain of a hex map — each filled by the same field, which sees
 * its coordinates.
 */
final readonly class GridBody implements Body
{
    public function __construct(public MapLayout $layout, public int $width, public int $height, public Field $cell)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidDefinition('A map is at least one cell wide and one cell high.');
        }
    }
}
