<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Identifier;
use Tilttek\SessionPrep\Internal\Text;

/**
 * A step of the preparation to tick off. A required step holds the preparation back from being ready until it is
 * checked.
 */
final readonly class ChecklistItem implements Part
{
    public const int MAX_LABEL = 200;

    public string $label;

    /**
     * @param string $label one line, 1 to 200 characters
     *
     * @throws InvalidPreparation when a value is out of its shape or its bounds
     */
    public function __construct(public string $key, string $label, public bool $required = false, public bool $checked = false)
    {
        Identifier::key($key, 'an item of the checklist');
        $this->label = Text::line($label, self::MAX_LABEL, 'label of an item of the checklist');
    }

    public function withChecked(bool $checked): self
    {
        return new self($this->key, $this->label, $this->required, $checked);
    }
}
