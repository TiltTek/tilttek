<?php

declare(strict_types=1);

namespace Tilttek\SetsBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\Tier;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger and the Scheduler, and the bundle with
 * its stores in memory, the definitions holding one set.
 *
 * The event dispatcher is declared by the application itself. Symfony's ServicesBundle keeps its own `event_dispatcher`
 * only when symfony/event-dispatcher is a production requirement or symfony/dependency-injection a development one;
 * with the bundle installed on its own, the first comes through FrameworkBundle in require-dev while the second is in
 * require, and Messenger's sync transport would then point to a service that is not there.
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
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    /**
     * The definitions of the application: one set of two items, with a tier at one.
     */
    public static function definitions(): InMemoryDefinitionStore
    {
        $definitions = new InMemoryDefinitionStore();
        $definitions->add(new SetDefinition('starter', 1, ['a', 'b'], [new Tier('half', required: 1)]));

        return $definitions;
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/tilttek-sets-bundle-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'messenger' => ['enabled' => true], 'scheduler' => ['enabled' => true],
        ]);
        $container->services()->set('event_dispatcher', EventDispatcher::class)->public()
            ->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $container->services()->set('app.definitions', InMemoryDefinitionStore::class)->factory([self::class, 'definitions'])->public();
        $container->extension('tilttek_sets', ['definition_store' => 'app.definitions', 'schedule' => ['frequency' => '1 hour', 'limit' => 10]]);
    }
}
