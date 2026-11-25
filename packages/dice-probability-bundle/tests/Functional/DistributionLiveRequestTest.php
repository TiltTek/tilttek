<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Functional;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent;
use Tilttek\DiceProbabilityBundle\Tests\Functional\App\TestKernel;
use Twig\Environment;

/**
 * `<twig:TilttekDiceProbability:Distribution>` computed again by the requests the browser sends, through the routes
 * of LiveComponent: answered by the name of the component only, never by its class, and never drawn when a 422 is
 * thrown before its action is called.
 */
#[CoversClass(DistributionComponent::class)]
final class DistributionLiveRequestTest extends KernelTestCase
{
    private const string NAME = 'TilttekDiceProbability:Distribution';

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testTheExpressionTypedIsComputedAgain(): void
    {
        $response = $this->live(self::NAME, $this->props(), ['expression' => '1d4']);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/vnd.live-component+html', $response->headers->get('Content-Type'));
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 1d4</caption>', (string) $response->getContent());
        self::assertStringNotContainsString('Probabilités de 3d{coin}', (string) $response->getContent());
        self::assertStringContainsString('data-live-name-value="'.self::NAME.'"', (string) $response->getContent());
    }

    public function testTheClassOfTheComponentIsNoAddress(): void
    {
        $response = $this->live(DistributionComponent::class, $this->props(), ['expression' => '1d4']);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringNotContainsString('Probabilités de 1d4', (string) $response->getContent());
    }

    public function testA422BeforeTheActionDrawsNothing(): void
    {
        // Where a controller attribute or an argument resolver refuses the request, before the action is called.
        $props = $this->props();
        $response = $this->live(self::NAME, $props, ['expression' => '1d4'], refusedBeforeTheAction: true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertNotSame('application/vnd.live-component+html', $response->headers->get('Content-Type'));
        self::assertStringNotContainsString('Probabilités de 1d4', (string) $response->getContent());
        self::assertStringNotContainsString('data-live-name-value', (string) $response->getContent());
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    /**
     * The props the page hands the browser, signed, read from the first render.
     *
     * @return array<mixed>
     */
    private function props(): array
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('page.html.twig');
        self::assertSame(1, preg_match('{data-live-props-value="([^"]+)"}', $html, $match));
        $props = json_decode(html_entity_decode($match[1], \ENT_QUOTES), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($props);
        self::assertSame('3d{coin}', $props['expression']);

        return $props;
    }

    /**
     * A request as the live controller of the browser sends it.
     *
     * @param array<mixed>         $props
     * @param array<string, mixed> $updated
     */
    private function live(string $component, array $props, array $updated, bool $refusedBeforeTheAction = false): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/vnd.live-component+html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];

        if ($refusedBeforeTheAction) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', TestKernel::REFUSE_BEFORE_THE_ACTION))] = '1';
        }

        $request = Request::create(
            '/_components/'.rawurlencode($component),
            'POST',
            ['data' => json_encode(['props' => $props, 'updated' => $updated], \JSON_THROW_ON_ERROR)],
            server: $server,
        );

        return self::$kernel?->handle($request) ?? self::fail('No kernel.');
    }
}
