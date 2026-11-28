<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Diff;

use Tilttek\TableRules\Rule;

/**
 * A rule found in both versions, with another title, text or category.
 */
final readonly class RuleChange
{
    /**
     * @param list<LineChange> $lines the lines of the text, kept, removed and added; none when the text is the same
     */
    public function __construct(public Rule $before, public Rule $after, public array $lines)
    {
    }

    public function titleChanged(): bool
    {
        return $this->before->title !== $this->after->title;
    }

    public function textChanged(): bool
    {
        return $this->before->text !== $this->after->text;
    }

    public function categoryChanged(): bool
    {
        return $this->before->category !== $this->after->category;
    }

    /**
     * @return array{id: string, before: array{id: string, title: string, text: string, category: ?string}, after: array{id: string, title: string, text: string, category: ?string}, titleChanged: bool, textChanged: bool, categoryChanged: bool, lines: list<array{kind: string, text: string}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->after->id,
            'before' => $this->before->toArray(),
            'after' => $this->after->toArray(),
            'titleChanged' => $this->titleChanged(),
            'textChanged' => $this->textChanged(),
            'categoryChanged' => $this->categoryChanged(),
            'lines' => array_map(static fn (LineChange $line): array => $line->toArray(), $this->lines),
        ];
    }
}
