<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Tilttek\TableSafetyBundle\Application\NoTableAccess;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Screens;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(Screens::class)]
final class ScreensTest extends TestCase
{
    public function testATableThatIsNoReferenceIsNotFound(): void
    {
        $screens = new Screens(new NoTableAccess(), new Environment(new ArrayLoader()), new CsrfTokenManager(), 'base.html.twig');

        self::assertSame('table:7', $screens->table('table:7')->toString());

        $this->expectException(NotFoundHttpException::class);

        $screens->table('Table 7');
    }

    public function testAScriptAsksForJson(): void
    {
        self::assertFalse(Screens::wantsJson(Request::create('/')));
        self::assertFalse(Screens::wantsJson(Request::create('/', server: ['HTTP_ACCEPT' => 'text/html'])));
        self::assertTrue(Screens::wantsJson(Request::create('/', server: ['HTTP_ACCEPT' => 'application/json'])));
    }
}
