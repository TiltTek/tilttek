<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Fixture;

use Override;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * A loader that remembers every template name it is asked about.
 */
final class SpyLoader implements LoaderInterface
{
    /**
     * @var list<string>
     */
    public array $asked = [];

    private readonly ArrayLoader $loader;

    /**
     * @param array<string, string> $templates
     */
    public function __construct(array $templates)
    {
        $this->loader = new ArrayLoader($templates);
    }

    #[Override]
    public function getSourceContext(string $name): Source
    {
        $this->asked[] = $name;

        return $this->loader->getSourceContext($name);
    }

    #[Override]
    public function getCacheKey(string $name): string
    {
        $this->asked[] = $name;

        return $this->loader->getCacheKey($name);
    }

    #[Override]
    public function isFresh(string $name, int $time): bool
    {
        $this->asked[] = $name;

        return $this->loader->isFresh($name, $time);
    }

    #[Override]
    public function exists(string $name): bool
    {
        $this->asked[] = $name;

        return $this->loader->exists($name);
    }
}
