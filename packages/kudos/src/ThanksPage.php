<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

/**
 * A page of thanks, oldest first, withdrawn thanks left out, and the position to read the next page after; null when
 * the journal holds no more.
 */
final readonly class ThanksPage
{
    /**
     * @param list<Thanks> $thanks
     */
    public function __construct(
        public array $thanks,
        public ?int $next,
    ) {
    }
}
