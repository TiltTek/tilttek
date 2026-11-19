<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Http;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;
use Twig\Environment;

/**
 * What the screens share: the table read from the path, the viewer the application's `TableAccess` names, the check
 * of a form's CSRF token, and the rendering of a template inside the application's layout. Every answer is private
 * and never cached.
 */
final readonly class Screens
{
    /** A table's reference in the path: a kind, a colon and an identifier without a slash. */
    public const string TABLE = '[a-z][a-z0-9_.-]{0,63}:[^/]{1,255}';

    public const string SIGNAL_TOKEN = 'tilttek_table_safety_signal';

    public const string ACKNOWLEDGE_TOKEN = 'tilttek_table_safety_acknowledge';

    public function __construct(
        private TableAccess $access,
        private Environment $twig,
        private CsrfTokenManagerInterface $tokens,
        private string $layout,
    ) {
    }

    public function table(string $table): ResourceReference
    {
        try {
            return ResourceReference::fromString($table);
        } catch (InvalidResource $invalidResource) {
            throw new NotFoundHttpException('No such table.', $invalidResource);
        }
    }

    /**
     * The viewer's reference at the table; refused when they do not take part in it.
     */
    public function participant(ResourceReference $table): ResourceReference
    {
        return $this->access->participant($table) ?? throw new AccessDeniedHttpException('This table is not yours.');
    }

    public function isGameMaster(ResourceReference $table): bool
    {
        return $this->access->isGameMaster($table);
    }

    /**
     * Refused unless the viewer runs the table.
     */
    public function assertGameMaster(ResourceReference $table): void
    {
        if (!$this->access->isGameMaster($table)) {
            throw new AccessDeniedHttpException('Only the game master of this table opens this screen.');
        }
    }

    /**
     * Refused unless the request carries the token of the form.
     */
    public function assertToken(Request $request, string $id): void
    {
        $token = $request->request->getString('_token');

        if (!$this->tokens->isTokenValid(new CsrfToken($id, $token))) {
            throw new AccessDeniedHttpException('The form has expired; reload the page.');
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return self::private(new Response($this->twig->render($template, [...$context, 'layout' => $this->layout]), $status));
    }

    public static function private(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public static function wantsJson(Request $request): bool
    {
        return 'json' === $request->getPreferredFormat();
    }
}
