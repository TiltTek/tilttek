<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Functional\App;

use ReflectionClass;
use Tilttek\Exchange\Event\ExchangeEvent;

/**
 * A listener of the application that keeps the short names of the exchanges' events it hears.
 */
final class HeardEvents
{
    /**
     * @var list<string>
     */
    public array $heard = [];

    public function heard(ExchangeEvent $event): void
    {
        $this->heard[] = new ReflectionClass($event)->getShortName();
    }
}
