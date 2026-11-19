<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Twig\Components;

use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The form of the physical dice on the register's page, `<twig:TilttekLootTables:PhysicalDice :form="form" />`: the
 * box named in its legend, one field per unit with the order of its tables, the errors of each, and the button that
 * enters them. The page builds the form with `PhysicalDiceType`, handles it, and calls `PhysicalDice::enter()`.
 */
#[AsTwigComponent(name: 'TilttekLootTables:PhysicalDice', template: '@TilttekLootTables/components/PhysicalDice.html.twig', exposePublicProps: false)]
final class PhysicalDiceComponent
{
    /** The view of a `PhysicalDiceType` form; nothing is drawn without one. */
    public ?FormView $form = null;
}
