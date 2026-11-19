<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Functional\App;

use ReflectionClass;

/**
 * A listener of the application that keeps the short names of the tokens' events it hears.
 */
final class HeardEvents
{
    /**
     * @var list<string>
     */
    public array $heard = [];

    public function heard(object $event): void
    {
        $this->heard[] = new ReflectionClass($event)->getShortName();
    }
}
