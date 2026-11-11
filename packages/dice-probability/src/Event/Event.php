<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

/**
 * Something the faces of a set of dice show or not: a double, at least one 1, all above 3. Events compose with
 * `AnyOf`, `AllOf` and `Not`.
 */
interface Event
{
    public function matches(Faces $faces): bool;
}
