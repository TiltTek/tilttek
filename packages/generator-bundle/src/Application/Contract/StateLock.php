<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application\Contract;

use Closure;

/**
 * Keeps two draws on the same state apart: the work of one runs while the other waits, so that the second starts from
 * the state the first left. The bundle holds it with Symfony's lock factory; an application gives its own.
 */
interface StateLock
{
    /**
     * Runs the work while no other work on the same generator and key runs, and gives back what it returns; the lock is
     * released when the work ends, by a return or by an exception.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function hold(string $generator, string $key, Closure $work): mixed;
}
