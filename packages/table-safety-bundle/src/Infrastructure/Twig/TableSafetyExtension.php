<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Twig;

use Tilttek\TableSafety\SafetyTool;
use Twig\Attribute\AsTwigFunction;

/**
 * The safety tools in templates:
 *
 * - `table_safety_kit(component)` names a component of the UX kit the screens draw with;
 * - `table_safety_tools()` lists every tool, in the order of a game, for an announcement or a legend.
 */
final readonly class TableSafetyExtension
{
    public function __construct(
        private KitComponents $kit = new KitComponents(),
    ) {
    }

    #[AsTwigFunction('table_safety_kit')]
    public function kit(string $component): string
    {
        return $this->kit->name($component);
    }

    /**
     * @return list<SafetyTool>
     */
    #[AsTwigFunction('table_safety_tools')]
    public function tools(): array
    {
        return SafetyTool::all();
    }
}
