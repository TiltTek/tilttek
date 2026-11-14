<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Fixture;

use Closure;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * A dispatcher of tests whose listener fails on the events it is told to, as many times as it is told; it keeps every
 * event it delivered, in order.
 */
final class FailingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $delivered = [];

    private int $failed = 0;

    /**
     * @param Closure(object): bool $fails which events the listener fails on
     */
    public function __construct(
        private readonly Closure $fails,
        public int $failures = 1,
    ) {
    }

    #[Override]
    public function dispatch(object $event): object
    {
        if ($this->failures > 0 && ($this->fails)($event)) {
            --$this->failures;

            throw new RuntimeException(\sprintf('The listener failed, time %d.', ++$this->failed));
        }

        $this->delivered[] = $event;

        return $event;
    }
}
