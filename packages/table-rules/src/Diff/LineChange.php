<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Diff;

/**
 * One line of a rule's text, kept, removed or added.
 */
final readonly class LineChange
{
    public function __construct(public LineKind $kind, public string $text)
    {
    }

    /**
     * @return array{kind: string, text: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'text' => $this->text];
    }
}
