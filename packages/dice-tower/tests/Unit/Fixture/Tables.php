<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Application\Contract\TableAccess;

/**
 * The tables the viewer sits at.
 */
final readonly class Tables implements TableAccess
{
    /** @var list<string> */
    private array $tables;

    public function __construct(string ...$tables)
    {
        $this->tables = array_values($tables);
    }

    #[Override]
    public function canWatch(ResourceReference $table): bool
    {
        return \in_array($table->toString(), $this->tables, true);
    }
}
