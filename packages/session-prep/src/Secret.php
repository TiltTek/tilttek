<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Parts;
use Tilttek\SessionPrep\Internal\Text;

/**
 * Something the players may learn, tied to no scene, so that it comes out wherever it fits: its text, the clues that
 * lead to it, and whether it is revealed.
 */
final readonly class Secret implements Part
{
    public const int MAX_TEXT = 1_000;

    public const int MAX_CLUES = 10;

    public string $text;

    /** @var list<Clue> */
    public array $clues;

    /**
     * @param string     $text  lines, 1 to 1000 characters
     * @param list<Clue> $clues at most 10, each key once
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(public string $key, string $text, array $clues = [], public bool $revealed = false)
    {
        Identifier::key($key, 'a secret');
        $this->text = Text::block($text, self::MAX_TEXT, 'text of a secret', true);
        $this->clues = Parts::distinct($clues, self::MAX_CLUES, 'clues of a secret');
    }

    public function clue(string $key): ?Clue
    {
        return Parts::find($this->clues, $key);
    }

    public function withRevealed(bool $revealed): self
    {
        return new self($this->key, $this->text, $this->clues, $revealed);
    }

    /**
     * @throws InvalidPreparation when no clue of the secret has the key
     */
    public function withClueFound(string $clue, bool $found): self
    {
        return new self($this->key, $this->text, Parts::put($this->clues, Parts::get($this->clues, $clue, 'clue')->withFound($found)), $this->revealed);
    }

    /**
     * The secret revealed as the other one is, and each clue found as the clue of its key there; the rest are not.
     */
    public function withFactsOf(?self $before): self
    {
        return new self(
            $this->key,
            $this->text,
            array_map(static fn (Clue $clue): Clue => $clue->withFound($before?->clue($clue->key)->found ?? false), $this->clues),
            $before->revealed ?? false,
        );
    }
}
