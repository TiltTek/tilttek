<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Tilttek\Generator\Definition\Entry;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityPolicy;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * What a generator's template may use: the tags `set`, `if`, `for` and `apply`, the string filters of Twig and the
 * generator's own, the functions `pick`, `generate` and `roll`, a few tests — and, of objects, only the text and the
 * fields of an entry.
 *
 * @internal
 */
final readonly class SandboxPolicy implements SecurityPolicyInterface
{
    public const array TAGS = ['set', 'if', 'for', 'apply'];

    public const array FILTERS = [
        'capitalize', 'title', 'upper', 'lower', 'trim', 'replace', 'slice', 'join', 'first', 'last', 'length', 'default',
        'abs', 'round', 'upper_first', 'elide', 'article', 'agree', 'plural',
    ];

    public const array FUNCTIONS = ['pick', 'generate', 'roll'];

    public const array TESTS = ['defined', 'empty', 'null', 'none', 'even', 'odd', 'divisible by', 'same as', 'iterable'];

    private SecurityPolicy $policy;

    public function __construct()
    {
        $this->policy = new SecurityPolicy(self::TAGS, self::FILTERS, [], [], self::FUNCTIONS, self::TESTS);
        $this->policy->setStrict(true);
    }

    /**
     * @param list<string> $tests
     */
    #[Override]
    public function checkSecurity($tags, $filters, $functions, array $tests = []): void
    {
        $this->policy->checkSecurity($tags, $filters, $functions, $tests);
    }

    #[Override]
    public function checkMethodAllowed($obj, $method): void
    {
        if (!$obj instanceof Entry || '__tostring' !== strtolower($method)) {
            throw new SecurityNotAllowedMethodError(\sprintf('Calling "%s" on a "%s" object is not allowed.', $method, $obj::class), $obj::class, $method);
        }
    }

    #[Override]
    public function checkPropertyAllowed($obj, $property): void
    {
        if (!$obj instanceof Entry) {
            throw new SecurityNotAllowedPropertyError(\sprintf('Reading "%s" of a "%s" object is not allowed.', $property, $obj::class), $obj::class, $property);
        }
    }
}
