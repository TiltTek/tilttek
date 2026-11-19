<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

/**
 * A token factory that writes the topics it grants as the token.
 */
final class RecordingTokenFactory implements TokenFactoryInterface
{
    /**
     * @param array<Grant>            $grants
     * @param array<array-key, mixed> $additionalClaims
     */
    #[Override]
    public function create(array $grants = [], array $additionalClaims = []): string
    {
        $topics = [];

        foreach ($grants as $grant) {
            foreach ($grant->topics as $topic) {
                $topics[] = \is_string($topic) ? $topic : '?';
            }
        }

        return implode(' ', $topics);
    }
}
