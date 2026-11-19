<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Twig;

/**
 * The names under which the recipes of the UX kit are installed in the application: its components directory holds
 * them in the subdirectory of its `ux_toolkit` configuration, so that with `ui`, `Card:Title` is `ui:Card:Title`.
 */
final readonly class KitComponents
{
    /** The recipes the overlay draws with, as `ux:install` names them. */
    public const array RECIPES = ['card'];

    private string $prefix;

    public function __construct(string $subdirectory = '')
    {
        $path = trim($subdirectory, '/');
        $this->prefix = '' === $path ? '' : str_replace('/', ':', $path).':';
    }

    /**
     * The name of a component of the kit, `Card` or `Card:Title`, as the application renders it.
     */
    public function name(string $component): string
    {
        return $this->prefix.$component;
    }
}
