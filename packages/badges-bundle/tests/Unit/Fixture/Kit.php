<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Fixture;

use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryGrantStore;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\InMemoryProgressStore;

/**
 * `Badges` in memory over a small catalog: a counted badge, a hidden one, one awarded by hand, a repeatable one.
 */
final class Kit
{
    private function __construct()
    {
    }

    public static function badges(Clock $clock = new Clock()): Badges
    {
        $progress = new InMemoryProgressStore();
        $catalog = Catalog::fromArray([
            'commenter' => ['name' => 'badges.commenter', 'description' => 'badges.commenter.description', 'image' => 'https://example.org/commenter.png', 'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 3]]],
            'secret' => ['name' => 'badges.secret', 'hidden' => true, 'criterion' => ['first' => ['verbs' => ['easter.egg']]]],
            'organiser' => ['name' => 'badges.organiser', 'image' => '/images/organiser.svg', 'criterion' => ['manual' => true]],
            'games' => ['name' => 'Games', 'repeatable' => true, 'criterion' => ['manual' => true]],
        ]);

        return new Badges(new Evaluator($catalog, $progress), $progress, new InMemoryAwardStore(), new InMemoryProfileStore(), new InMemoryGrantStore(), $clock);
    }
}
