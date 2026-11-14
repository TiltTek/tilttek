<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Functional\App;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\DiceBundle\Application\Event\RollRecorded;

/**
 * The rolls the test kernel heard of.
 */
#[AsEventListener]
final class HeardRolls
{
    /**
     * @var list<RollRecorded>
     */
    public array $events = [];

    public function __invoke(RollRecorded $event): void
    {
        $this->events[] = $event;
    }
}
