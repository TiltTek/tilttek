<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Override;
use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Expression\Pool;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;

/**
 * The macros of an input, looked for on its shelves in their order; the names a macro writes are looked for from its
 * own level on, so that a site's macro means the same at every table and for every member. A macro being defined is
 * read as it will be kept.
 *
 * @internal
 */
final readonly class MacroResolution implements Macros
{
    public function __construct(
        private DiceRoller $roller,
        private DiceMacroRepository $macros,
        private MacroShelves $shelves,
        private MacroSession $session,
        private ?DiceMacro $pending = null,
    ) {
    }

    #[Override]
    public function pool(string $name): ?Pool
    {
        foreach ($this->shelves->shelves() as [$level, $owner]) {
            $macro = true === $this->pending?->is($level, $owner, $name) ? $this->pending : $this->macros->get($level, $owner, $name);

            if ($macro instanceof DiceMacro) {
                $within = new self($this->roller, $this->macros, $this->shelves->from($level), $this->session, $this->pending);

                return $this->session->expand(
                    $level->value."\n".$owner->toString()."\n".$name,
                    $name,
                    $macro->expression(),
                    fn (): Pool => $this->roller->parse($macro->expression(), macros: $within),
                );
            }
        }

        return null;
    }
}
