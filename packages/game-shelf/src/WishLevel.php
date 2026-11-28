<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * How much a member wants a game, from "must have" to "don't buy this": the five levels collection files carry as a
 * priority from 1 to 5. The wishlist shows the wishes by level, then in the member's own order.
 */
enum WishLevel: int
{
    case MustHave = 1;
    case LoveToHave = 2;
    case LikeToHave = 3;
    case ThinkingAboutIt = 4;
    case DontBuy = 5;
}
