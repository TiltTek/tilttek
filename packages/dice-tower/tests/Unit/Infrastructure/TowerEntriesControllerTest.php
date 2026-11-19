<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tables;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(TowerEntriesController::class)]
final class TowerEntriesControllerTest extends TestCase
{
    public function testTheEntriesAfterAPositionOrTheLastOnes(): void
    {
        $tower = new Tower();

        foreach (['1d4', '1d6', '1d8', '1d10'] as $expression) {
            $tower->tower->roll(Tower::table(), $expression);
        }

        $controller = new TowerEntriesController($tower->tower, new Tables('table:7'));

        self::assertSame([2, 3, 4], $this->positions($controller(Request::create('/'), 'table:7')->getContent()));
        self::assertSame([2, 3, 4], $this->positions($controller(Request::create('/?after=1'), 'table:7')->getContent()));
        self::assertSame([4], $this->positions($controller(Request::create('/?after=3'), 'table:7')->getContent()));
        self::assertSame([2, 3, 4], $this->positions($controller(Request::create('/?after=-1'), 'table:7')->getContent()));
        self::assertSame([2, 3, 4], $this->positions($controller(Request::create('/?after=x'), 'table:7')->getContent()));
        self::assertSame([1, 2, 3], $this->positions($controller(Request::create('/?after=0'), 'table:7')->getContent()));

        $response = $controller(Request::create('/?after=0'), 'table:7');
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['entries']);
        self::assertIsArray($body['entries'][0]);
        self::assertSame(['position', 'table', 'roll', 'roller', 'rolled_at', 'results'], array_keys($body['entries'][0]));
        self::assertSame('table:7', $body['entries'][0]['table']);
        self::assertSame(1, $body['entries'][0]['position']);
    }

    public function testAViewerWhoMayNotWatchIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        new TowerEntriesController(new Tower()->tower, new Tables('table:7'))(Request::create('/'), 'table:8');
    }

    public function testAnInvalidTableIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIs('No such table.');

        new TowerEntriesController(new Tower()->tower, new Tables('table:7'))(Request::create('/'), 'Table');
    }

    public function testTheRouteTakesAReferenceWithoutSlash(): void
    {
        $pattern = '{^'.TowerEntriesController::TABLE.'$}D';

        self::assertSame([1, 1, 0, 0, 0], [preg_match($pattern, 'table:7'), preg_match($pattern, 'game.table:a:b'), preg_match($pattern, 'table:a/b'), preg_match($pattern, 'Table:7'), preg_match($pattern, 'table:')]);
    }

    /**
     * @return list<int>
     */
    private function positions(string|false $content): array
    {
        /** @var array{entries: list<array{position: int}>} $body */
        $body = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);

        return array_column($body['entries'], 'position');
    }
}
