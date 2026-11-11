<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Notation\NotationWriter;

/**
 * The instructions of an input, rolled in order, each with its own result — `2d6, 1d8` or `8d10; @1c[>=6]` —, and the
 * comments written in them. An instruction refers only to those before it.
 */
final readonly class Pool
{
    /**
     * @var list<Node>
     */
    public array $expressions;

    /**
     * The comments of each instruction, by its index from 0, in the order they were written: they take no part in the
     * roll nor in the canonical form.
     *
     * @var array<int, list<string>>
     */
    public array $comments;

    public function __construct(Node $expression, Node ...$expressions)
    {
        $this->expressions = [$expression, ...array_values($expressions)];
        $this->comments = [];
    }

    /**
     * The same instructions, with these comments.
     *
     * @param array<int, list<string>> $comments
     */
    public function withComments(array $comments): self
    {
        return clone ($this, ['comments' => $comments]);
    }

    /**
     * The canonical form: lower-case letters, every count written, modifiers in the order they apply.
     */
    public function canonical(): string
    {
        return new NotationWriter()->write($this);
    }
}
