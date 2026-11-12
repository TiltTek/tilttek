<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Catalog;

use Override;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Symfony\Component\Config\ResourceCheckerInterface;

/**
 * Asks each file and directory of the catalog whether it changed since the cache was written, every time: a
 * long-running process — a worker, a test, a command run twice — sees a file changed in the same second as the cache.
 * Symfony's own checker keeps its answer for the process, by resource and second.
 */
final readonly class FileChanges implements ResourceCheckerInterface
{
    #[Override]
    public function supports(ResourceInterface $metadata): bool
    {
        return $metadata instanceof SelfCheckingResourceInterface;
    }

    #[Override]
    public function isFresh(ResourceInterface $resource, int $timestamp): bool
    {
        return $resource instanceof SelfCheckingResourceInterface && $resource->isFresh($timestamp);
    }
}
