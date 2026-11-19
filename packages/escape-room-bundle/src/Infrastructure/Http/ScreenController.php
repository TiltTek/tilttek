<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `GET /escape-room/screen/{game}`: the hint screen of a game — the countdown, the hint or the message the host shows,
 * the outcome and the celebration of an escape, and the team's choice to show its result —, and `GET /escape-room/screen/{game}/state`: the same without the
 * layout, which the Stimulus controller reads again when the hub tells it the game moved, or at intervals without a
 * hub.
 */
final readonly class ScreenController
{
    public const string SCREEN = 'tilttek_escape_room_screen';

    public const string STATE = 'tilttek_escape_room_screen_state';

    public function __construct(
        private Screens $screens,
        private Views $views,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function show(string $game): Response
    {
        $stored = $this->screens->game($game);
        $this->screens->assertWatches($stored);

        return $this->screens->render('@TilttekEscapeRoom/screen.html.twig', [
            'game' => $this->views->game($stored),
            'live' => $this->views->live($stored),
            'consent' => $this->screens->speaksForTeam($stored) ? ['action' => $this->urls->generate(ConsentController::CONSENT, ['game' => $game]), 'public' => $stored->game->public] : null,
        ]);
    }

    public function state(string $game): Response
    {
        $stored = $this->screens->game($game);
        $this->screens->assertWatches($stored);

        return $this->screens->render('@TilttekEscapeRoom/_screen.html.twig', ['game' => $this->views->game($stored)]);
    }
}
