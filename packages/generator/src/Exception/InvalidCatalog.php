<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use InvalidArgumentException;
use Tilttek\Generator\Engine\Problem;

/**
 * A catalog whose templates do not compile, break the sandbox, or call a list, a tag or a generator that does not exist.
 * Every problem found is kept, not only the first.
 */
final class InvalidCatalog extends InvalidArgumentException implements GeneratorException
{
    /**
     * @param non-empty-list<Problem> $problems
     */
    public function __construct(private readonly array $problems)
    {
        parent::__construct(implode("\n", $problems));
    }

    /**
     * @return non-empty-list<Problem>
     */
    public function problems(): array
    {
        return $this->problems;
    }
}
