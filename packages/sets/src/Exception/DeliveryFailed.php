<?php

declare(strict_types=1);

namespace Tilttek\Sets\Exception;

use RuntimeException;
use Throwable;

/**
 * A pending delivery whose handlers threw for one holder or more: the other holders were told all the same. It carries
 * the counts of the run and, as its previous exception, the first exception a handler threw.
 */
final class DeliveryFailed extends RuntimeException implements SetsException
{
    /**
     * @param int       $delivered     the completions delivered by the run, failing holders included
     * @param int       $failedHolders the holders a handler threw for
     * @param int       $holders       the holders the run told
     * @param Throwable $first         the first exception a handler threw
     */
    public function __construct(
        public readonly int $delivered,
        public readonly int $failedHolders,
        public readonly int $holders,
        Throwable $first,
    ) {
        parent::__construct(
            \sprintf('A handler threw for %d of the %d holders told; %d completions were delivered all the same. The first exception is the previous one.', $failedHolders, $holders, $delivered),
            previous: $first,
        );
    }
}
