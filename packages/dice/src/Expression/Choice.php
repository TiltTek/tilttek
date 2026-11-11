<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A switch on the result of its subject: `1d6S[1..2]{"a"}[3..5]{"b"}{"c"}` gives, as a list, the branch of every case
 * that holds; exclusive, `S^`, the branch of the first one. When none holds, the default branch — the last, without a
 * condition — or the subject itself.
 */
final readonly class Choice implements Node
{
    /**
     * @var non-empty-list<SwitchCase>
     */
    public array $cases;

    /**
     * @param list<SwitchCase> $cases
     */
    public function __construct(
        public Node $subject,
        array $cases,
        public ?Node $default = null,
        public bool $exclusive = false,
    ) {
        if ([] === $cases) {
            throw InvalidDefinition::because('A switch has at least one case.');
        }

        $this->cases = $cases;
    }
}
