<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Text;

/**
 * One way for the players to come upon a secret — a letter, a rumour, a track — and whether they found it.
 */
final readonly class Clue implements Part
{
    public const int MAX_TEXT = 500;

    public string $text;

    /**
     * @param string $text one line, 1 to 500 characters
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(public string $key, string $text, public bool $found = false)
    {
        Identifier::key($key, 'a clue');
        $this->text = Text::line($text, self::MAX_TEXT, 'text of a clue');
    }

    public function withFound(bool $found): self
    {
        return new self($this->key, $this->text, $found);
    }
}
