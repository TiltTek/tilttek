<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Contract;

/**
 * One line of an offer's wish list: an offer it would be traded for, or a group of offers it would take any one of.
 * The position of the line in the list is its priority: the first line is wished most.
 */
interface Wish
{
    /**
     * The offers this line names, in the order they were given.
     *
     * @return non-empty-list<string>
     */
    public function offers(): array;
}
