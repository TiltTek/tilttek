<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Parts;

/**
 * The steps a game master goes through before each session, given to a preparation as its checklist. `standard()` is
 * a general one; an application or a game master keeps their own.
 */
final readonly class ChecklistTemplate
{
    /** @var list<ChecklistItem> */
    public array $items;

    /**
     * @param list<ChecklistItem> $items at most 50, each key once, none checked
     *
     * @throws InvalidPreparation when there are too many, a key comes twice, or one is checked
     */
    public function __construct(array $items)
    {
        $this->items = Parts::distinct($items, Preparation::MAX_CHECKLIST, 'items of the checklist');

        foreach ($this->items as $item) {
            if ($item->checked) {
                throw InvalidPreparation::because(\sprintf('The item %s of a template is not checked.', $item->key));
            }
        }
    }

    /**
     * A general preparation in steps, each named by what it is for; the opening, the scenes and the secrets are
     * required.
     */
    public static function standard(): self
    {
        return new self([
            new ChecklistItem('recap', 'Recall what happened in the last session'),
            new ChecklistItem('characters', 'Review the player characters and what they are after'),
            new ChecklistItem('limits', 'Check the content limits the table agreed on'),
            new ChecklistItem('opening', 'Write an opening that starts in the action', true),
            new ChecklistItem('scenes', 'Sketch the scenes that might happen', true),
            new ChecklistItem('secrets', 'Write secrets and the clues that lead to them', true),
            new ChecklistItem('places', 'Pick the places the characters may visit'),
            new ChecklistItem('npcs', 'Pick the non-player characters and what they want'),
            new ChecklistItem('threats', 'Pick the threats and look up their statistics'),
            new ChecklistItem('rewards', 'Pick the rewards'),
            new ChecklistItem('materials', 'Gather the maps, handouts and music'),
        ]);
    }

    /**
     * The preparation with the template's items after its own, those whose key it holds already left as they are.
     *
     * @throws InvalidPreparation when the checklist would hold too many items
     */
    public function applyTo(Preparation $preparation): Preparation
    {
        foreach ($this->items as $item) {
            if (!$preparation->item($item->key) instanceof ChecklistItem) {
                $preparation = $preparation->withItem($item);
            }
        }

        return $preparation;
    }
}
