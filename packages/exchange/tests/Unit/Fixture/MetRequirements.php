<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Reward\Requirements;

/**
 * The requirements each member meets, as the test gives them.
 */
final readonly class MetRequirements implements Requirements
{
    /**
     * @param array<string, list<string>> $met
     */
    public function __construct(
        private array $met,
    ) {
    }

    #[Override]
    public function meets(ResourceReference $member, string $requirement): bool
    {
        return \in_array($requirement, $this->met[$member->toString()] ?? [], true);
    }
}
