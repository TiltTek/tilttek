<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Tilttek\GameCatalog\Exception\InvalidCollectionFile;

/**
 * The bounds of a collection file, which a member uploads and nobody checked: its size, its rows, its columns, the
 * size of a cell, and how many row errors are reported.
 */
final readonly class Limits
{
    /**
     * @throws InvalidCollectionFile when a bound is under 1
     */
    public function __construct(
        public int $bytes = 2_097_152,
        public int $rows = 20_000,
        public int $columns = 100,
        public int $cellBytes = 4_096,
        public int $errors = 100,
    ) {
        if (min($bytes, $rows, $columns, $cellBytes, $errors) < 1) {
            throw InvalidCollectionFile::because('Each limit of a collection file is 1 or more.');
        }
    }
}
