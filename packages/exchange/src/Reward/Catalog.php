<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reward;

use Tilttek\Exchange\Exception\UnknownReward;

/**
 * The rewards a site offers for points.
 */
interface Catalog
{
    /**
     * @throws UnknownReward
     */
    public function get(string $code): Reward;

    /**
     * Every reward, sorted by code.
     *
     * @return list<Reward>
     */
    public function all(): array;
}
