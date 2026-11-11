<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * Whether every face matches, tried face by face up to a thousand faces.
 *
 * @phpstan-require-implements Validator
 */
trait ByEachFace
{
    public const int FACES_TRIED = 1000;

    public function matchesAll(int $lowest, int $highest): bool
    {
        if ($highest - $lowest >= self::FACES_TRIED) {
            return false;
        }

        for ($face = $lowest; $face <= $highest; ++$face) {
            if (!$this->matches($face)) {
                return false;
            }
        }

        return true;
    }
}
