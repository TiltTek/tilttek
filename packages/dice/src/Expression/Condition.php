<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A validator and the scope it is checked in: `[>=6]`, `[die:6]`, `[all:>2]`, `[total:>=10]`.
 */
final readonly class Condition
{
    public function __construct(
        public Validator $validator,
        public Scope $scope = Scope::Value,
    ) {
    }
}
