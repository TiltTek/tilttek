<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Http;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\EscapeRoom\GameOutcome;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Application\ScreenKind;
use Tilttek\EscapeRoomBundle\Application\StoredGame;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\ScreenSubscriptions;

/**
 * What the templates read of a game: its countdown and outcome, what its hint screen shows — the hint read from the
 * scenario by its puzzle and tier —, the version that changes when any of it changes, and how the page hears of it.
 */
final readonly class Views
{
    /**
     * @param ?string $celebration the effect of the celebration of an escape, or null when it is disabled
     * @param int     $poll        the seconds between two readings of a screen without a hub
     */
    public function __construct(
        private GameDesk $desk,
        private UrlGeneratorInterface $urls,
        private ScreenSubscriptions $subscriptions,
        private ?string $celebration = 'confetti',
        private int $poll = 5,
    ) {
    }

    /**
     * @return array{id: string, room: string, remaining: int, played: int, penalty: int, final: int, hints: int, skips: int, paused: bool, outcome: ?string, screen: array{kind: ?string, title: ?string, text: ?string, penalty: int}, celebration: ?string, version: string}
     */
    public function game(StoredGame $game): array
    {
        $timer = $this->desk->timer($game);
        $screen = $this->screen($game, $this->desk->screen($game->id));

        return [
            'id' => $game->id,
            'room' => $game->room->title,
            'remaining' => $timer->remaining,
            'played' => $timer->played,
            'penalty' => $timer->penalty,
            'final' => $timer->final(),
            'hints' => $timer->hints,
            'skips' => $timer->skips,
            'paused' => $timer->paused,
            'outcome' => $game->game->outcome?->value,
            'screen' => $screen,
            'celebration' => GameOutcome::Escaped === $game->game->outcome ? $this->celebration : null,
            'version' => hash('xxh128', json_encode([$game->game->toArray(), $screen], \JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * How a page hears of the game's moves: the hub to subscribe to, or none, the URL of the screen's state, and the
     * seconds between two readings.
     *
     * @return array{hub: string, state: string, poll: int}
     */
    public function live(StoredGame $game): array
    {
        return [
            'hub' => $this->subscriptions->subscribe($game->id) ?? '',
            'state' => $this->urls->generate(ScreenController::STATE, ['game' => $game->id]),
            'poll' => $this->poll,
        ];
    }

    /**
     * @return array{kind: ?string, title: ?string, text: ?string, penalty: int}
     */
    private function screen(StoredGame $game, ?Screen $screen): array
    {
        if (!$screen instanceof Screen || ScreenKind::Cleared === $screen->kind) {
            return ['kind' => $screen?->kind->value, 'title' => null, 'text' => null, 'penalty' => 0];
        }

        if (ScreenKind::Message === $screen->kind) {
            return ['kind' => $screen->kind->value, 'title' => null, 'text' => $screen->message, 'penalty' => 0];
        }

        $scenario = $game->room->scenario;
        $puzzle = null !== $screen->puzzle && $scenario->has($screen->puzzle) ? $scenario->puzzle($screen->puzzle) : null;
        $hint = $puzzle?->hints[($screen->tier ?? 0) - 1] ?? null;

        return ['kind' => $screen->kind->value, 'title' => $puzzle?->title, 'text' => $hint?->text, 'penalty' => $screen->penalty];
    }
}
