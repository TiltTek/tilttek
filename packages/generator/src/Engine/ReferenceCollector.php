<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\RangeBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Reads a parsed template for the lists, the tags and the generators it names in plain words — `pick('a.list',
 * where: {gender: 'f'})`, `generate('npc')` — so that a name that does not exist is an error when the catalog loads,
 * not an empty text at the first draw; notes the range operator, which could build an array of any size; and lists the
 * tags, filters, functions and tests the template uses, for the sandbox policy to judge before any draw.
 *
 * @internal
 */
final class ReferenceCollector implements NodeVisitorInterface
{
    /**
     * @var list<array{function: string, name: string, tags: list<string>}>
     */
    public array $references = [];

    public bool $range = false;

    /**
     * @var list<string>
     */
    public array $tags = [];

    /**
     * @var list<string>
     */
    public array $filters = [];

    /**
     * @var list<string>
     */
    public array $functions = [];

    /**
     * @var list<string>
     */
    public array $tests = [];

    #[Override]
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof RangeBinary) {
            $this->range = true;
        }

        $name = $this->name($node);

        if (null !== $node->getNodeTag()) {
            $this->tags[] = $node->getNodeTag();
        }

        if ($node instanceof FilterExpression && null !== $name) {
            $this->filters[] = $name;
        }

        if ($node instanceof TestExpression && null !== $name) {
            $this->tests[] = $name;
        }

        if ($node instanceof FunctionExpression && null !== $name) {
            $this->functions[] = $name;

            if (\in_array($name, ['pick', 'generate'], true)) {
                $this->collect($name, $node->getNode('arguments'));
            }
        }

        return $node;
    }

    #[Override]
    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    private function name(Node $node): ?string
    {
        $name = $node->hasAttribute('name') ? $node->getAttribute('name') : null;

        return \is_string($name) ? $name : null;
    }

    private function collect(string $function, Node $arguments): void
    {
        $name = $arguments->hasNode('0') ? $arguments->getNode('0') : null;

        if (!$name instanceof ConstantExpression || !\is_string($name->getAttribute('value'))) {
            return;
        }

        $tags = [];
        $where = $arguments->hasNode('where') ? $arguments->getNode('where') : ($arguments->hasNode('2') ? $arguments->getNode('2') : null);

        if ($where instanceof ArrayExpression) {
            foreach (array_chunk(iterator_to_array($where), 2) as [$key]) {
                if ($key instanceof ConstantExpression && \is_scalar($value = $key->getAttribute('value'))) {
                    $tags[] = (string) $value;
                }
            }
        }

        $this->references[] = ['function' => $function, 'name' => $name->getAttribute('value'), 'tags' => $tags];
    }
}
