<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\GeneratorBundle\Application\ApiAccess;
use Tilttek\GeneratorBundle\Application\Client;
use Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter;
use Tilttek\GeneratorBundle\Infrastructure\Http\DrawController;

#[CoversClass(DrawController::class)]
final class DrawControllerTest extends TestCase
{
    public function testAPublicGeneratorIsDrawn(): void
    {
        $response = $this->call('/name.short?count=2&unique=1&seed=x', 'name.short');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $body = $this->body($response);
        self::assertSame(['generator', 'results', 'requested', 'short'], array_keys($body));
        self::assertSame(['name.short', 2, false], [$body['generator'], $body['requested'], $body['short']]);
        self::assertSame($body, $this->body($this->call('/name.short?count=2&unique=1&seed=x', 'name.short')));
    }

    public function testATableWithAMemoryTakesBackTheStateOfItsLastAnswer(): void
    {
        $first = $this->body($this->call('/days?count=2', 'days', 'Bearer all'));

        self::assertSame(['Day 1 of spring', 'Day 2 of spring'], $first['results']);
        self::assertSame(['day' => '2', 'season' => 'spring'], $first['state']);

        $next = $this->body($this->call('/days?state[day]=2&state[season]=winter', 'days', 'Bearer all'));
        self::assertSame(['Day 3 of winter'], $next['results']);
        self::assertSame(['day' => '3', 'season' => 'winter'], $next['state']);

        $refused = $this->call('/days?state=winter', 'days', 'Bearer all');
        self::assertSame(400, $refused->getStatusCode());
        self::assertSame('The state is a map of names to texts, such as state[weather]=rain.', $this->body($refused)['detail']);
    }

    public function testAClientDrawsWithItsToken(): void
    {
        $response = $this->call('/inn', 'inn', 'Bearer secret');

        self::assertSame(200, $response->getStatusCode());
        $results = $this->body($response)['results'];
        self::assertIsArray($results);
        self::assertSame([['name', 'sign', 'staff']], array_map(array_keys(...), array_filter($results, is_array(...))));
    }

    public function testTheApiAnswersNotFoundWhileItIsDisabled(): void
    {
        $response = new DrawController(new ApiAccess(false, ['name.short']), $this->generator(), 20)(Request::create('/name.short'), 'name.short');

        self::assertSame([404, 'application/problem+json'], [$response->getStatusCode(), $response->headers->get('Content-Type')]);
        self::assertSame(['status' => 404, 'detail' => 'The draw API is disabled.'], $this->body($response));
    }

    public function testAPrivateGeneratorAsksForAToken(): void
    {
        self::assertSame(401, $this->call('/inn', 'inn')->getStatusCode());
        self::assertSame(401, $this->call('/inn', 'inn', 'Basic secret')->getStatusCode());
        self::assertSame(401, $this->call('/inn', 'inn', 'Bearer other')->getStatusCode());
    }

    public function testAGeneratorNotOpenToTheClientIsForbidden(): void
    {
        $response = $this->call('/broken', 'broken', 'Bearer secret');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('The generator "broken" is not open to this client.', $this->body($response)['detail']);
    }

    public function testParametersThatCannotBeReadAreABadRequest(): void
    {
        $response = $this->call('/name.short?count=21', 'name.short');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('The count is a whole number from 1 to 20.', $this->body($response)['detail']);
    }

    public function testTextsThatAreNotUtf8AreABadRequest(): void
    {
        $name = $this->call('/name%FF', "name\xFF");
        $state = $this->call('/name.short?state%5Bw%5D=rain%FF', 'name.short');

        self::assertSame([400, 'The name of the generator is UTF-8.'], [$name->getStatusCode(), $this->body($name)['detail']]);
        self::assertSame(400, $state->getStatusCode());
        self::assertSame('The state is a map of at most 32 names to texts of at most 1024 characters, in UTF-8.', $this->body($state)['detail']);
    }

    public function testADrawPastTheRateLimitWaits(): void
    {
        $limiter = new class implements DrawRateLimiter {
            #[Override]
            public function consume(string $key): ?int
            {
                return 'address:192.0.2.9' === $key ? 42 : null;
            }
        };
        $controller = new DrawController(new ApiAccess(true, ['name.short'], [], $limiter), $this->generator(), 20);

        $response = $controller(Request::create('/name.short', server: ['REMOTE_ADDR' => '192.0.2.9']), 'name.short');
        self::assertSame([429, '42'], [$response->getStatusCode(), $response->headers->get('Retry-After')]);
        self::assertSame(200, $controller(Request::create('/name.short', server: ['REMOTE_ADDR' => '192.0.2.1']), 'name.short')->getStatusCode());
    }

    public function testAnUnknownGeneratorIsNotFound(): void
    {
        $response = $this->call('/nope', 'nope', 'Bearer secret');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('No generator is named "nope".', $this->body($response)['detail']);
    }

    public function testADrawThatFailsIsUnprocessable(): void
    {
        $response = $this->call('/broken', 'broken', 'Bearer all');

        self::assertSame(422, $response->getStatusCode());
        $detail = $this->body($response)['detail'];
        self::assertIsString($detail);
        self::assertStringStartsWith('The template "broken:template:0" failed', $detail);
    }

    public function testAPublicDrawThatFailsHidesItsCause(): void
    {
        $access = new ApiAccess(true, ['broken'], []);
        $controller = new DrawController($access, $this->generator(), 20);
        $response = $controller(Request::create('/broken', server: ['REMOTE_ADDR' => '192.0.2.1']), 'broken');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('The draw failed.', $this->body($response)['detail']);
    }

    private function call(string $uri, string $generator, ?string $authorization = null): JsonResponse
    {
        $access = new ApiAccess(true, ['name.short'], [new Client('partner', 'secret', ['inn', 'name.short', 'nope']), new Client('all', 'all', ['*'])]);
        $request = Request::create($uri, server: null === $authorization ? [] : ['HTTP_AUTHORIZATION' => $authorization]);

        return new DrawController($access, $this->generator(), 20)($request, $generator);
    }

    private function generator(): Generator
    {
        return new Generator(new FileLoader()->catalog([\dirname(__DIR__, 2).'/Fixture/generators', \dirname(__DIR__, 2).'/Fixture/memory.yaml']), new Limits());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(JsonResponse $response): array
    {
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
