<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with its router and a rate limiter, TwigBundle, and the
 * bundle reading config/generators of the project, its API open to anyone for one generator and to a client for all.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/tilttek-generator-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-generator-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'rate_limiter' => ['public_draws' => ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute']],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'strict_variables' => true]);
        $container->extension('tilttek_generator', [
            'api' => [
                'enabled' => true,
                'public' => ['name.short'],
                'rate_limiter' => 'limiter.public_draws',
                'clients' => ['partner' => ['token' => 'secret', 'generators' => ['*']]],
            ],
        ]);
    }
}
