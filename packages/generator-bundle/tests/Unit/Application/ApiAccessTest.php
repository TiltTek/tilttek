<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Application;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GeneratorBundle\Application\ApiAccess;
use Tilttek\GeneratorBundle\Application\Client;
use Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter;
use Tilttek\GeneratorBundle\Application\Exception\AccessDenied;
use Tilttek\GeneratorBundle\Application\Grant;

#[CoversClass(ApiAccess::class)]
#[CoversClass(Client::class)]
#[CoversClass(Grant::class)]
#[CoversClass(AccessDenied::class)]
final class ApiAccessTest extends TestCase
{
    public function testTheApiIsOffByDefault(): void
    {
        self::assertFalse(new ApiAccess()->isEnabled());
        self::assertTrue(new ApiAccess(true)->isEnabled());
    }

    public function testAPublicGeneratorIsDrawnByAddress(): void
    {
        $limiter = $this->limiter(null);
        $grant = new ApiAccess(true, ['name'], [], $limiter)->grant('name', null, '192.0.2.1');

        self::assertNull($grant->client);
        self::assertSame('address:192.0.2.1', $grant->key);
        self::assertNull($grant->retryAfter());
        self::assertSame(['address:192.0.2.1'], $limiter->keys);
        self::assertNull(new Grant(null, 'k')->retryAfter());
    }

    public function testAnotherGeneratorAsksForAToken(): void
    {
        try {
            new ApiAccess(true, ['name'])->grant('inn', null, '192.0.2.1');
            self::fail('A private generator was drawn without a token.');
        } catch (AccessDenied $accessDenied) {
            self::assertTrue($accessDenied->tokenMissing);
            self::assertSame('The generator "inn" is drawn with a token only.', $accessDenied->getMessage());
        }
    }

    public function testAClientDrawsTheGeneratorsOpenToIt(): void
    {
        $limiter = $this->limiter(30);
        $access = new ApiAccess(true, [], [new Client('other', 'secret-0', ['*']), new Client('partner', 'secret-1', ['inn', 'name'], $limiter)]);

        $grant = $access->grant('name', 'secret-1', '192.0.2.1');
        self::assertSame(['partner', 'client:partner'], [$grant->client, $grant->key]);
        self::assertSame(30, $grant->retryAfter());
        self::assertSame(['client:partner'], $limiter->keys);
        self::assertSame('other', $access->grant('anything', 'secret-0', '192.0.2.1')->client);
    }

    public function testAClientCannotDrawAGeneratorNotOpenToIt(): void
    {
        try {
            new ApiAccess(true, ['dungeon'], [new Client('partner', 'secret-1', ['inn'])])->grant('dungeon', 'secret-1', '192.0.2.1');
            self::fail('A generator not open to the client was drawn.');
        } catch (AccessDenied $accessDenied) {
            self::assertFalse($accessDenied->tokenMissing);
            self::assertSame('The generator "dungeon" is not open to this client.', $accessDenied->getMessage());
        }
    }

    public function testAnUnknownTokenIsRefused(): void
    {
        try {
            new ApiAccess(true, ['name'], [new Client('partner', 'secret-1', ['*'])])->grant('name', 'secret-2', '192.0.2.1');
            self::fail('An unknown token was accepted.');
        } catch (AccessDenied $accessDenied) {
            self::assertTrue($accessDenied->tokenMissing);
            self::assertSame('The token is not known.', $accessDenied->getMessage());
        }
    }

    public function testAClientKnowsItsToken(): void
    {
        $client = new Client('partner', 'secret', ['inn']);

        self::assertTrue($client->hasToken('secret'));
        self::assertFalse($client->hasToken('secret '));
        self::assertTrue($client->mayDraw('inn'));
        self::assertFalse($client->mayDraw('name'));
        self::assertNull($client->limiter);
        self::assertSame(['partner', ['inn']], [$client->id, $client->generators]);
    }

    /**
     * @return DrawRateLimiter&object{keys: list<string>}
     */
    private function limiter(?int $retryAfter): DrawRateLimiter
    {
        return new class($retryAfter) implements DrawRateLimiter {
            /**
             * @var list<string>
             */
            public array $keys = [];

            public function __construct(private readonly ?int $retryAfter)
            {
            }

            #[Override]
            public function consume(string $key): ?int
            {
                $this->keys[] = $key;

                return $this->retryAfter;
            }
        };
    }
}
