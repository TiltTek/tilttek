<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Http;

use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\EscapeRoom\Exception\EscapeRoomException;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\StoredGame;
use Tilttek\PuzzleBuilder\Exception\PuzzleBuilderException;

/**
 * `GET /escape-room/console/{game}`: the host's console — the countdown, the puzzles with their hints revealed and
 * left, what the hint screen shows —, and `POST /escape-room/console/{game}/{action}`: push a hint, skip a puzzle,
 * pause, resume, send a message, clear the screen, end the game. Each form works without a script, answered by a
 * redirection to the console; the Stimulus controller sends it in the background and reads JSON.
 */
final readonly class ConsoleController
{
    public const string CONSOLE = 'tilttek_escape_room_console';

    public const string ACT = 'tilttek_escape_room_console_act';

    public const array ACTIONS = ['hint', 'skip', 'pause', 'resume', 'message', 'clear', 'abandon'];

    public function __construct(
        private Screens $screens,
        private GameDesk $desk,
        private Views $views,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function show(Request $request, string $game): Response
    {
        $stored = $this->screens->game($game);
        $this->screens->host($stored);

        return $this->screens->render('@TilttekEscapeRoom/console.html.twig', [
            'game' => $this->views->game($stored),
            'puzzles' => $this->desk->puzzles($stored)->puzzles,
            'live' => $this->views->live($stored),
            'refused' => mb_substr($request->query->getString('refused'), 0, 300),
            'actions' => array_combine(self::ACTIONS, array_map(fn (string $action): string => $this->urls->generate(self::ACT, ['game' => $game, 'action' => $action]), self::ACTIONS)),
        ]);
    }

    public function act(Request $request, string $game, string $action): Response
    {
        if (!\in_array($action, self::ACTIONS, true)) {
            throw new NotFoundHttpException('No such action.');
        }

        $stored = $this->screens->game($game);
        $host = $this->screens->host($stored);
        $this->screens->assertToken($request, Screens::CONSOLE_TOKEN);

        try {
            $this->apply($stored, $host, $action, $request->request->getString('puzzle'), $request->request->getString('message'));
        } catch (EscapeRoomException|PuzzleBuilderException $refused) {
            if (Screens::wantsJson($request)) {
                return Screens::private(new JsonResponse(['error' => $refused->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            return Screens::private(new RedirectResponse($this->urls->generate(self::CONSOLE, ['game' => $game, 'refused' => $refused->getMessage()]), Response::HTTP_SEE_OTHER));
        }

        if (Screens::wantsJson($request)) {
            return Screens::private(new JsonResponse(['action' => $action, 'game' => $this->views->game($this->desk->game($game))]));
        }

        return Screens::private(new RedirectResponse($this->urls->generate(self::CONSOLE, ['game' => $game]), Response::HTTP_SEE_OTHER));
    }

    private function apply(StoredGame $game, ResourceReference $host, string $action, string $puzzle, string $message): void
    {
        match ($action) {
            'hint' => $this->desk->pushHint($game->id, $puzzle, $host),
            'skip' => $this->desk->skip($game->id, $puzzle, $host),
            'pause' => $this->desk->pause($game->id, $host),
            'resume' => $this->desk->resume($game->id, $host),
            'message' => $this->desk->message($game->id, $host, $message),
            'clear' => $this->desk->clearScreen($game->id, $host),
            default => $this->desk->abandon($game->id),
        };
    }
}
