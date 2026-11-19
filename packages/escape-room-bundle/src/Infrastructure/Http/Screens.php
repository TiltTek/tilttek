<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Http;

use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tilttek\EscapeRoom\Exception\EscapeRoomException;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\StoredGame;
use Twig\Environment;

/**
 * What the screens share: the game read from the path, the viewer the application's `EscapeRoomAccess` names, the
 * check of a form's CSRF token, and the rendering of a template inside the application's layout. Every answer is
 * private and never cached.
 */
final readonly class Screens
{
    /** A game's identifier in the path. */
    public const string GAME = '[A-Za-z0-9][A-Za-z0-9_-]{0,63}';

    /** A room's identifier in the path. */
    public const string ROOM = '[a-z](?:[a-z0-9_-]{0,62}[a-z0-9])?';

    public const string CONSOLE_TOKEN = 'tilttek_escape_room_console';

    public const string CONSENT_TOKEN = 'tilttek_escape_room_consent';

    public function __construct(
        private GameDesk $desk,
        private EscapeRoomAccess $access,
        private Environment $twig,
        private CsrfTokenManagerInterface $tokens,
        private string $layout,
    ) {
    }

    /**
     * The game of the path; not found when there is none, or it does not read back.
     */
    public function game(string $id): StoredGame
    {
        try {
            return $this->desk->game($id);
        } catch (EscapeRoomException $escapeRoomException) {
            throw new NotFoundHttpException('No such game.', $escapeRoomException);
        }
    }

    /**
     * The viewer as the host of the game; refused when they do not run it.
     */
    public function host(StoredGame $game): ResourceReference
    {
        return $this->access->host($game) ?? throw new AccessDeniedHttpException('Only the host of this game opens its console.');
    }

    public function assertWatches(StoredGame $game): void
    {
        if (!$this->access->watches($game)) {
            throw new AccessDeniedHttpException('This hint screen is not yours.');
        }
    }

    /**
     * Whether the viewer speaks for the team, or runs the game: who records the team's consent.
     */
    public function speaksForTeam(StoredGame $game): bool
    {
        return $this->access->speaksForTeam($game) || $this->access->host($game) instanceof ResourceReference;
    }

    public function assertSpeaksForTeam(StoredGame $game): void
    {
        if (!$this->speaksForTeam($game)) {
            throw new AccessDeniedHttpException('Only the team chooses to show its result.');
        }
    }

    /**
     * Refused unless the request carries the token of the form.
     */
    public function assertToken(Request $request, string $id): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken($id, $request->request->getString('_token')))) {
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
