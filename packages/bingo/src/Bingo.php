<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Closure;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Activity\Activity;
use Tilttek\Bingo\Contract\BingoStore;
use Tilttek\Bingo\Event\BingoWon;
use Tilttek\Bingo\Event\SquareCalled;
use Tilttek\Bingo\Event\SquareTicked;
use Tilttek\Bingo\Exception\BingoConflict;
use Tilttek\Bingo\Exception\BingoRefused;
use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * Bingo games: create one, deal each player a card of their own, tick the cards by the players' gestures or call
 * squares for all, and check every win on the server. A player never ticks a card nor declares a win: in a game ticked
 * by activity, the server ticks and a completed pattern wins on its own; in a game whose squares are called, a claim
 * wins only when the squares called complete a pattern of the card. The cards of a game whose squares are called may
 * also be printed in batches, each with a label that the organiser checks.
 *
 * A change that meets a newer version in the store is read again and made again, a few times. It keeps no state
 * between calls: the games and cards live in the store.
 */
final readonly class Bingo
{
    /** How many times a change is made again when the game or the card changed in between. */
    public const int ATTEMPTS = 8;

    /** How many serials are drawn for one player before giving up on a card unlike every other. */
    public const int DEAL_ATTEMPTS = 32;

    /** The most open cards of a player one gesture ticks. */
    public const int MOST_OPEN_CARDS = 50;

    /** The most cards one batch prints. */
    public const int MOST_PRINTED = 1000;

    public function __construct(
        private BingoStore $store,
        private CardDealer $dealer,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $game, SquarePool $pool, Grid $grid, WinPatterns $patterns, TickMode $mode = TickMode::Activity): BingoGame
    {
        $created = new BingoGame($game, $pool, $grid, $patterns, $mode);
        $this->store->addGame($created);

        return $created;
    }

    /**
     * The player's card in the game: dealt on the first call, the same one after. A serial whose layout another card
     * has already is passed over.
     */
    public function deal(string $game, string $player): Card
    {
        for ($attempt = 1; $attempt <= self::DEAL_ATTEMPTS; ++$attempt) {
            $held = $this->store->cardOf($game, $player);

            if ($held instanceof Card) {
                return $held;
            }

            [$current, $serial] = $this->changeGame($game, static fn (BingoGame $live): int => $live->nextSerial());
            $layout = $this->dealer->layout($current, $serial);
            $card = new Card($game, $serial, $player, $layout);

            try {
                if ($this->store->addCard($card, CardDealer::fingerprint($layout))) {
                    return $card;
                }
            } catch (BingoConflict) {
                // Another request dealt this player a card in between: the next turn reads it.
            }
        }

        throw BingoRefused::because(\sprintf('No card unlike the others was found for a player of the bingo game %s.', $game));
    }

    /**
     * Deals a batch of cards to print, held by no player, in a game whose squares are called: the serials are reserved
     * at once, and a serial whose layout another card has already is passed over for a new one, up to
     * `DEAL_ATTEMPTS` times in the batch. Each card shows its `label()`.
     *
     * @return list<Card> in the order of their serials
     */
    public function print(string $game, int $count): array
    {
        if ($count < 1 || $count > self::MOST_PRINTED) {
            throw InvalidBingo::because(\sprintf('A batch prints 1 to %d cards; %d asked.', self::MOST_PRINTED, $count));
        }

        if (TickMode::Called !== $this->game($game)->mode) {
            throw BingoRefused::because(\sprintf('The cards of the bingo game %s are ticked by activity: each belongs to a member, none is printed.', $game));
        }

        [$current, $first] = $this->changeGame($game, static fn (BingoGame $live): int => $live->nextSerials($count));
        $serials = range($first, $first + $count - 1);
        $cards = [];
        $passed = 0;

        while ([] !== $serials) {
            $serial = array_shift($serials);
            $layout = $this->dealer->layout($current, $serial);
            $card = new Card($game, $serial, null, $layout);

            if ($this->store->addCard($card, CardDealer::fingerprint($layout))) {
                $cards[] = $card;

                continue;
            }

            if (++$passed > self::DEAL_ATTEMPTS) {
                throw BingoRefused::because(\sprintf('No card unlike the others was found for a batch of the bingo game %s.', $game));
            }

            [, $serials[]] = $this->changeGame($game, static fn (BingoGame $live): int => $live->nextSerial());
        }

        return $cards;
    }

    /**
     * The label a card of the game shows when printed: its serial and its check code.
     */
    public function label(string $game, int $serial): SerialLabel
    {
        return $this->dealer->label($this->game($game), $serial);
    }

    /**
     * The organiser's check of a printed card by the label it shows, as they type it: refused when the code is not the
     * one the secret draws for the serial, then the same as `verify()`.
     */
    public function verifyLabel(string $game, string $label): Claim
    {
        $typed = SerialLabel::parse($label);

        if (!hash_equals($this->label($game, $typed->serial)->code, $typed->code)) {
            throw BingoRefused::because(\sprintf('The label %s is not the label of a card of the bingo game %s.', $typed->toString(), $game));
        }

        return $this->verify($game, $typed->serial);
    }

    /**
     * Calls a square for every card of a game whose squares are called.
     */
    public function call(string $game, string $square): void
    {
        [, $new] = $this->changeGame($game, static fn (BingoGame $live): bool => $live->call($square));

        if ($new) {
            $this->events->dispatch(new SquareCalled($game, $square));
        }
    }

    /**
     * Ticks, on the member's cards of the open games ticked by activity, the squares the gesture triggers, and wins a
     * card whose pattern it completes.
     */
    public function record(Activity $activity): void
    {
        $player = $activity->member->toString();

        foreach ($this->store->openCardsOf($player, self::MOST_OPEN_CARDS) as $held) {
            $game = $this->store->game($held->game);

            if (!$game instanceof BingoGame || TickMode::Activity !== $game->mode || !$game->isOpen()) {
                continue;
            }

            $squares = [];

            foreach (array_filter($held->layout, static fn (?string $square): bool => null !== $square) as $id) {
                $trigger = $game->pool->get($id)->trigger;

                if ($trigger instanceof Trigger && $trigger->matches($activity)) {
                    $squares[] = $id;
                }
            }

            if ([] === $squares) {
                continue;
            }

            $key = $activity->key->value;
            [$card, $ticked] = $this->changeCard($held->game, $held->serial, static fn (Card $card): array => $card->tickAll($squares, $key));

            foreach ($ticked as $square) {
                $this->events->dispatch(new SquareTicked($card->game, $card->serial, $square));
            }

            $this->settle($game, $card);
        }
    }

    /**
     * Unticks, on the member's cards that have not won, the cells the cancelled gesture ticked.
     */
    public function cancel(Activity $activity): void
    {
        $key = $activity->key->value;

        foreach ($this->store->openCardsOf($activity->member->toString(), self::MOST_OPEN_CARDS) as $held) {
            if ([] !== array_filter($held->ticks(), static fn (array $keys): bool => \in_array($key, $keys, true))) {
                $this->changeCard($held->game, $held->serial, static fn (Card $card): array => $card->untick($key));
            }
        }
    }

    /**
     * A player's claim on their card: the server checks the card against its seed and its ticks — the squares called,
     * or the gestures recorded — and wins it when they complete a pattern.
     */
    public function claim(string $game, int $serial, string $player): Claim
    {
        $card = $this->card($game, $serial);

        if ($card->player !== $player) {
            throw BingoRefused::because(\sprintf('The card %d of the bingo game %s is not the card of this player.', $serial, $game));
        }

        return $this->verify($game, $serial);
    }

    /**
     * The organiser's check of a card by its serial, a printed one included: the same as a claim, without the player.
     * A card of a closed game is checked, never won.
     */
    public function verify(string $game, int $serial): Claim
    {
        $current = $this->game($game);
        $card = $this->card($game, $serial);

        if ($card->layout !== $this->dealer->layout($current, $serial)) {
            throw BingoRefused::because(\sprintf('The card %d of the bingo game %s does not match its seed.', $serial, $game));
        }

        if (!$card->hasWon() && $current->isOpen()) {
            $card = $this->settle($current, $card);
        }

        $ticked = $this->ticked($current, $card);
        sort($ticked);

        return new Claim($game, $serial, $ticked, $card->pattern());
    }

    /**
     * The cells of the card ticked, the free centre included: the squares called, or the cells its gestures ticked.
     *
     * @return array<int, int> the cells ticked, by themselves
     */
    public function ticked(BingoGame $game, Card $card): array
    {
        $ticked = [];
        $centre = $game->grid->centre();

        if (null !== $centre) {
            $ticked[$centre] = $centre;
        }

        if (TickMode::Called === $game->mode) {
            foreach ($card->layout as $cell => $square) {
                if (null !== $square && $game->isCalled($square)) {
                    $ticked[$cell] = $cell;
                }
            }
        } else {
            foreach (array_keys($card->ticks()) as $cell) {
                $ticked[$cell] = $cell;
            }
        }

        return $ticked;
    }

    public function close(string $game): void
    {
        $this->changeGame($game, static function (BingoGame $live): null {
            $live->close();

            return null;
        });
    }

    public function game(string $game): BingoGame
    {
        return $this->store->game($game) ?? throw BingoRefused::unknownGame($game);
    }

    public function card(string $game, int $serial): Card
    {
        return $this->store->card($game, $serial) ?? throw BingoRefused::unknownCard($game, $serial);
    }

    /**
     * Wins the card when its ticks complete a pattern, and says so once.
     */
    private function settle(BingoGame $game, Card $card): Card
    {
        $pattern = $game->patterns->matched($this->ticked($game, $card));

        if (!$pattern instanceof WinPattern || $card->hasWon()) {
            return $card;
        }

        $now = $this->clock->now();
        [$won, $first] = $this->changeCard($card->game, $card->serial, static function (Card $card) use ($pattern, $now): bool {
            $first = !$card->hasWon();
            $card->win($pattern->name, $now);

            return $first;
        });

        if ($first) {
            $this->changeGame($game->id, static function (BingoGame $live) use ($won): null {
                $live->won($won->serial);

                return null;
            });
            $this->events->dispatch(new BingoWon($won->game, $won->serial, $won->player, $pattern->name));
        }

        return $won;
    }

    /**
     * @template T
     *
     * @param Closure(BingoGame): T $change
     *
     * @return array{BingoGame, T}
     */
    private function changeGame(string $game, Closure $change): array
    {
        for ($attempt = 1;; ++$attempt) {
            $current = $this->game($game);
            $version = $current->version();
            $outcome = $change($current);

            if ($current->version() === $version) {
                return [$current, $outcome];
            }

            try {
                $this->store->saveGame($current);

                return [$current, $outcome];
            } catch (BingoConflict $conflict) {
                if ($attempt >= self::ATTEMPTS) {
                    throw $conflict;
                }
            }
        }
    }

    /**
     * @template T
     *
     * @param Closure(Card): T $change
     *
     * @return array{Card, T}
     */
    private function changeCard(string $game, int $serial, Closure $change): array
    {
        for ($attempt = 1;; ++$attempt) {
            $current = $this->card($game, $serial);
            $version = $current->version();
            $outcome = $change($current);

            if ($current->version() === $version) {
                return [$current, $outcome];
            }

            try {
                $this->store->saveCard($current);

                return [$current, $outcome];
            } catch (BingoConflict $conflict) {
                if ($attempt >= self::ATTEMPTS) {
                    throw $conflict;
                }
            }
        }
    }
}
