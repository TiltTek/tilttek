<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application\Exception;

use Throwable;

/**
 * An expression the bundle refuses on its own, before or after the library: besides its message in English, the key
 * of its reason in the `tilttek_dice_probability` translation domain, and the parameters of that reason.
 */
interface Refusal extends Throwable
{
    /**
     * The key of the reason, `refusal.groups`, in the `tilttek_dice_probability` domain.
     */
    public function key(): string;

    /**
     * The parameters of the reason, by their placeholders: `%limit%`.
     *
     * @return array<string, int|string>
     */
    public function parameters(): array;
}
