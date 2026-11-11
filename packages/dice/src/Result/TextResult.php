<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;
use Tilttek\Dice\Exception\CannotRoll;

/**
 * A text: a sentence with its placeholders replaced, or a text or translation key as written — never HTML.
 */
final readonly class TextResult implements NodeResult
{
    public function __construct(
        public string $text,
    ) {
    }

    #[Override]
    public function value(): int
    {
        throw CannotRoll::notANumber('a text');
    }

    #[Override]
    public function output(): string
    {
        return $this->text;
    }

    #[Override]
    public function sets(): array
    {
        return [];
    }

    /**
     * @return array{type: 'text', text: string}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'text', 'text' => $this->text];
    }
}
