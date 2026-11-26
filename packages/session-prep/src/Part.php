<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

/**
 * An element of a preparation — a scene, a secret, a clue, a non-player character, a place, a note or an item of the
 * checklist — found by its key, unique among the elements of its kind.
 */
interface Part
{
    /** The key, 1 to 64 letters, digits, "_", ".", ":" or "-", starting with a letter. */
    public string $key { get; }
}
