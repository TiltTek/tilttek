<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * An item of the checklist was checked, or unchecked when `$checked` is false.
 */
final readonly class ItemMarked implements PreparationEvent
{
    public function __construct(public string $preparation, public string $item, public bool $checked)
    {
    }
}
