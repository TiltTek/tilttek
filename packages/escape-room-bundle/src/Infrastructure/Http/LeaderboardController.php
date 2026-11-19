<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tilttek\EscapeRoom\Standing;
use Tilttek\EscapeRoomBundle\Application\Contract\RoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\TeamNames;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Twig\Environment;

/**
 * `GET /escape-room/leaderboard/{room}`: the public leaderboard of a room — only the teams that agreed to show their
 * result —, as a table readable without a script, the hints beside the time. A team is named by the name
 * `TeamNames` gives, or by its place; never by a member.
 */
final readonly class LeaderboardController
{
    public const string LEADERBOARD = 'tilttek_escape_room_leaderboard';

    public function __construct(
        private GameDesk $desk,
        private RoomCatalog $catalog,
        private TeamNames $names,
        private Environment $twig,
        private string $layout,
        private int $size = 10,
    ) {
    }

    public function __invoke(string $room): Response
    {
        $found = $this->catalog->room($room) ?? throw new NotFoundHttpException('No such room.');
        $response = new Response($this->twig->render('@TilttekEscapeRoom/leaderboard.html.twig', [
            'layout' => $this->layout,
            'room' => $found,
            'standings' => array_map(fn (Standing $standing): array => [
                'position' => $standing->position,
                'team' => $this->names->name($standing->result->team),
                'final' => $standing->result->finalSeconds,
                'hints' => $standing->result->hints,
                'skips' => $standing->result->skips,
            ], $this->desk->leaderboard($found->id, $this->size)),
        ]));
        $response->setPublic();
        $response->setMaxAge(60);

        return $response;
    }
}
