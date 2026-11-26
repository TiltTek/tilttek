<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use SensitiveParameter;
use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * The seed of a daily game: an HMAC-SHA256 of the game and the day under a key derived from a secret of the site, so
 * that every player of a day gets the same game, nobody can work out tomorrow's, and another site with another secret
 * gets other games. It is the 64 hexadecimal digits a table of `tilttek/game-engine` is opened with.
 */
final readonly class DailySeed
{
    /** The shortest secret, in bytes. */
    public const int MIN_SECRET = 32;

    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'tilttek_mini_games.daily_seed';

    /** The key of this usage alone: no other usage of the same secret gives the same seeds. */
    private string $key;

    /**
     * @throws InvalidMiniGame when the secret is shorter than `MIN_SECRET` bytes
     */
    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        if (\strlen($secret) < self::MIN_SECRET) {
            throw InvalidMiniGame::because(\sprintf('The secret of the daily games is at least %d bytes.', self::MIN_SECRET));
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    /**
     * The seed of a game on a day, 64 lowercase hexadecimal digits.
     */
    public function for(string $game, Day $day): string
    {
        return hash_hmac('sha256', $game."\n".$day->value, $this->key);
    }
}
