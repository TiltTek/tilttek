<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\ChecklistItem;
use Tilttek\SessionPrep\ChecklistTemplate;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Preparation;

#[CoversClass(ChecklistTemplate::class)]
final class ChecklistTemplateTest extends TestCase
{
    public function testTheStandardTemplateGoesThroughTheStepsOfAPreparation(): void
    {
        $template = ChecklistTemplate::standard();

        self::assertSame(
            ['recap', 'characters', 'limits', 'opening', 'scenes', 'secrets', 'places', 'npcs', 'threats', 'rewards', 'materials'],
            array_map(static fn (ChecklistItem $item): string => $item->key, $template->items),
        );
        self::assertSame(['opening', 'scenes', 'secrets'], array_values(array_map(
            static fn (ChecklistItem $item): string => $item->key,
            array_filter($template->items, static fn (ChecklistItem $item): bool => $item->required),
        )));
    }

    public function testItAddsItsItemsAfterThoseOfThePreparationLeavingTheKeysItHolds(): void
    {
        $own = new ChecklistItem('opening', 'My own opening', false, true);
        $preparation = new Preparation('p', 'c', 'm', 'Title', checklist: [new ChecklistItem('snacks', 'Snacks'), $own]);
        $template = new ChecklistTemplate([new ChecklistItem('opening', 'Opening', true), new ChecklistItem('maps', 'Maps')]);

        $applied = $template->applyTo($preparation);

        self::assertSame(['snacks', 'opening', 'maps'], array_map(static fn (ChecklistItem $item): string => $item->key, $applied->checklist));
        self::assertSame($own, $applied->item('opening'));
        self::assertSame($applied->checklist, $template->applyTo($applied)->checklist);
    }

    public function testATemplateIsBounded(): void
    {
        $items = array_map(static fn (int $index): ChecklistItem => new ChecklistItem('k'.$index, 'Item'), range(1, Preparation::MAX_CHECKLIST));
        self::assertCount(Preparation::MAX_CHECKLIST, new ChecklistTemplate($items)->items);

        $this->expectException(InvalidPreparation::class);

        new ChecklistTemplate([...$items, new ChecklistItem('extra', 'Item')]);
    }

    public function testAKeyComesOnce(): void
    {
        $this->expectException(InvalidPreparation::class);

        new ChecklistTemplate([new ChecklistItem('a', 'One'), new ChecklistItem('a', 'Two')]);
    }

    public function testATemplateItemIsNotChecked(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The item b of a template is not checked.'));

        new ChecklistTemplate([new ChecklistItem('a', 'One'), new ChecklistItem('b', 'Two', checked: true)]);
    }
}
