<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Stringable;

/**
 * One thing wrong with a catalog, and the template where it is.
 */
final readonly class Problem implements Stringable
{
    public function __construct(public string $location, public string $message)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->location.': '.$this->message;
    }
}
