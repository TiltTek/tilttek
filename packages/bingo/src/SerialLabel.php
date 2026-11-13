<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * What a printed card shows to be checked: its serial and a check code of eight characters, drawn from the server's
 * secret, written `42-7KQ3-M9XA`. Without the secret, nobody can write the code of a serial, so that a card drawn by
 * hand with a made-up number is told apart at once.
 *
 * The code is in Crockford's base 32: digits and capital letters without I, L, O and U. What an organiser types is read
 * leniently — any case, spaces or hyphens between the parts, O read as 0, I and L as 1 — and at most `MAX_INPUT` bytes.
 */
final readonly class SerialLabel
{
    /** The longest input read, in bytes. */
    public const int MAX_INPUT = 32;

    /** The letters of a code. */
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private function __construct(
        public int $serial,
        public string $code,
    ) {
    }

    public static function of(int $serial, string $code): self
    {
        if ($serial < 1 || $serial > BingoGame::MOST_CARDS) {
            throw InvalidBingo::because(\sprintf('The serial of a card is 1 to %d; %d is not one.', BingoGame::MOST_CARDS, $serial));
        }

        if (1 !== preg_match('/^[0-9A-HJKMNP-TV-Z]{8}$/D', $code)) {
            throw InvalidBingo::because('The check code of a card is 8 characters of base 32: digits and capital letters without I, L, O and U.');
        }

        return new self($serial, $code);
    }

    /**
     * Reads a label as an organiser types it.
     *
     * @throws InvalidBingo when the input is not a label
     */
    public static function parse(string $input): self
    {
        if (\strlen($input) > self::MAX_INPUT) {
            throw InvalidBingo::because(\sprintf('A label is read from at most %d bytes.', self::MAX_INPUT));
        }

        if (1 !== preg_match('/^(?<serial>\d{1,7})[ -]+(?<head>[0-9A-Z]{4})[ -]?(?<tail>[0-9A-Z]{4})$/', strtoupper(trim($input)), $matches)) {
            throw InvalidBingo::because('A label is the serial of a card and its check code, as in 42-7KQ3-M9XA.');
        }

        return self::of((int) $matches['serial'], strtr($matches['head'].$matches['tail'], ['O' => '0', 'I' => '1', 'L' => '1']));
    }

    /**
     * The code of 40 bits, in eight characters.
     */
    public static function encode(string $bytes): string
    {
        if (5 !== \strlen($bytes)) {
            throw InvalidBingo::because('A check code is drawn from 5 bytes.');
        }

        $value = 0;

        foreach (str_split($bytes) as $byte) {
            $value = $value << 8 | \ord($byte[0]);
        }

        $digits = str_pad(base_convert((string) $value, 10, 32), 8, '0', \STR_PAD_LEFT);

        return strtr(strtoupper($digits), '0123456789ABCDEFGHIJKLMNOPQRSTUV', self::ALPHABET);
    }

    public function toString(): string
    {
        return \sprintf('%d-%s-%s', $this->serial, substr($this->code, 0, 4), substr($this->code, 4));
    }
}
