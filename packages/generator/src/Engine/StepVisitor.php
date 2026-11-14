<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Twig\Environment;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\SetNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Puts a step at the start of each turn of a loop and after each assignment of a template, so that the budget of a
 * result is checked inside a loop that never picks and between assignments that grow a text.
 *
 * @internal
 */
final class StepVisitor implements NodeVisitorInterface
{
    #[Override]
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    #[Override]
    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ForNode) {
            $node->setNode('body', new Nodes([new StepNode($node->getTemplateLine()), $node->getNode('body')]));

            return $node;
        }

        if ($node instanceof SetNode) {
            return new Nodes([$node, new StepNode($node->getTemplateLine())]);
        }

        return $node;
    }

    /**
     * After the sandbox's own visitor, so that the steps are put in the nodes it leaves.
     */
    #[Override]
    public function getPriority(): int
    {
        return 10;
    }
}
