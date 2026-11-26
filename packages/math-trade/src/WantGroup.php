<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Tilttek\MathTrade\Contract\Wish;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Internal\Identifier;

/**
 * A wish for any one of several offers, at one priority: "any of these three". The solver treats every offer of the
 * group as wished equally; the offer is traded for one of them at most.
 */
final readonly class WantGroup implements Wish
{
    /**
     * @var non-empty-list<string>
     */
    public array $offers;

    /**
     * @param list<string> $offers the identifiers of the wished offers, two or more, each once
     *
     * @throws InvalidTradeList when the group holds fewer than two offers, or one offer twice
     */
    public function __construct(array $offers)
    {
        if (\count($offers) < 2) {
            throw InvalidTradeList::because('A group of wishes names two offers or more; a single offer is a Want.');
        }

        $seen = [];

        foreach ($offers as $offer) {
            Identifier::check($offer, 'a wished offer');

            if (isset($seen[$offer])) {
                throw InvalidTradeList::because(\sprintf('The group of wishes names the offer "%s" twice.', $offer));
            }

            $seen[$offer] = true;
        }

        $this->offers = $offers;
    }

    public function offers(): array
    {
        return $this->offers;
    }
}
