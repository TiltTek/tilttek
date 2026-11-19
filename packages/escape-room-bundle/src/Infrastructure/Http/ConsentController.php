<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\EscapeRoomBundle\Application\GameDesk;

/**
 * `POST /escape-room/games/{game}/consent`: the team agrees, or no longer agrees, to show its result on the public
 * leaderboard — `public=1` or `public=0` —; the host may record it for the team. It changes at any time.
 */
final readonly class ConsentController
{
    public const string CONSENT = 'tilttek_escape_room_consent';

    public function __construct(
        private Screens $screens,
        private GameDesk $desk,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(Request $request, string $game): Response
    {
        $stored = $this->screens->game($game);
        $this->screens->assertSpeaksForTeam($stored);
        $this->screens->assertToken($request, Screens::CONSENT_TOKEN);

        $public = $request->request->getBoolean('public');
        $this->desk->consent($game, $public);

        if (Screens::wantsJson($request)) {
            return Screens::private(new JsonResponse(['public' => $public]));
        }

        return Screens::private(new RedirectResponse($this->urls->generate(ScreenController::SCREEN, ['game' => $game]), Response::HTTP_SEE_OTHER));
    }
}
