<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Functional\App;

use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

/**
 * A Mercure hub that keeps what it is given.
 */
final class HubRecorder
{
    /** @var list<Update> */
    public array $updates = [];

    public function hub(): MockHub
    {
        return new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('jwt'), function (Update $update): string {
            $this->updates[] = $update;

            return 'id';
        });
    }
}
