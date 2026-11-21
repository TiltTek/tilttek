<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Sandbox\Sandbox;
use Twig\Sandbox\SandboxInterface;

/**
 * The Twig of a generator's templates: an environment of its own, never the application's, whose every template renders
 * in a `Twig\Sandbox\Sandbox` under the strict `SandboxPolicy`. A tag, a filter, a function or a test outside the policy
 * is refused before the template runs, and before any template it names is loaded.
 *
 * @internal
 */
final readonly class SandboxedTwig
{
    /**
     * The environment, to tokenize, parse and compile the templates and to add the generator's functions and filters.
     */
    public Environment $environment;

    /**
     * What renders the templates.
     */
    public SandboxInterface $sandbox;

    public function __construct(LoaderInterface $loader, string|false $cache = false)
    {
        $this->environment = new Environment($loader, ['autoescape' => false, 'strict_variables' => true, 'cache' => $cache]);
        $this->sandbox = new Sandbox($this->environment, new SandboxPolicy());
    }
}
