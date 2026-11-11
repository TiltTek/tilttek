<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * Told what the collections decide: a copy given to a member, a set completed for the first time.
 */
interface CollectionObserver
{
    public function granted(Copy $copy): void;

    public function setCompleted(SetCompleted $completed): void;
}
