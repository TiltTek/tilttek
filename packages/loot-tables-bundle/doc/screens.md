# Screens

The bundle draws two screens: the draws of an order on the buyer's page, and the form of the physical dice on the
register's page. Their words are in the `tilttek_loot_tables` domain, in English and French. Neither needs
JavaScript.

## The buyer's draws

```twig
<twig:TilttekLootTables:Commitments order="{{ order.number }}" />
```

A Twig component, `TilttekLootTables:Commitments`, for the order page and the confirmation. Each draw is a `Card` of
the UX kit, the recipes `ux:install` puts in the application:

- its title names the line, the option and the unit it draws;
- a `Badge` says where it stands: sealed until the order is paid, checking out against its commitment, not checking
  out, or physical dice entered at a register;
- its commitment, made when the order was placed and shown before the payment;
- once revealed, the seed it published, the entry of each table with its face, and the shop code.

| Property | What it holds |
|---|---|
| `order` | The number of the order |
| `id` | The identifier of the section, `tilttek-loot-commitments` by default; its heading is `<id>-title` |

The component only reads: the draws are committed when the order is placed, never by showing them. It does not decide
who may see an order: render it only on a page the buyer of the order may open. An order that does not exist, or has
nothing to draw here, says that it has nothing to draw.

The recipes it draws with are `Card`, `Card:Header`, `Card:Title`, `Card:Content` and `Badge`, as the PHIL UX kit
gives them:

```sh
php bin/console ux:install card badge
```

They are named after the components subdirectory of the Symfony UX Toolkit, `ui:Card` when it is `ui`, unless
`kit_subdirectory` says where they are.

The classes, to style it: `tilttek-loot-commitments`, `__empty`, `__intro`, `__draws`, `__draw`, `__state`,
`__commitment`, `__seed` and `__entries`. Each draw carries its identifier in `data-draw`.

## The physical dice at a register

`PhysicalDiceType` is the form of the dice a buyer rolled in front of the cashier, and
`TilttekLootTables:PhysicalDice` draws it:

```php
$toEnter = $physicalDice->toEnter($order, $line->reference, 'loot'); // the box and the number of units
$form = $this->createForm(PhysicalDiceType::class, null, $toEnter)->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $physicalDice->enter($order, $line->reference, 'loot', PhysicalDiceType::codes($form));
    $orders->add($order);
}
```

```twig
<twig:TilttekLootTables:PhysicalDice :form="form.createView" />
```

- `toEnter()` refuses, with `LootDrawRefused`, a line the order does not have or an option that is not a box left to
  draw here, as `enter()` does, before the form is shown.
- One field per unit, `unit_1`, `unit_2`…, each labelled with its unit, its help naming the tables in the order of the
  dice. A field takes one character per die — `1`–`9`, then `A` for 10 up to `K` for 20, in upper or lower case —:
  the browser checks its length and its characters, and the form reads each code against the box, refusing on its
  field one that does not read before anything is entered.
- `codes()` gives the codes of a submitted form, in the order of the units, for `PhysicalDice::enter()`.
- A form asks for 1 to 100 units.

The component draws the legend naming the box, the fields with their labels, help and errors, and the button. The
register's page decides who may enter dice, as any page of the register.

## Accessibility

- The buyer's draws are a section with its heading, an ordered list of draws, each with a heading of the next level.
- Where a draw stands is said in words in its badge, never by a colour alone.
- The commitment and the seed are in `code` elements, under the terms of a description list.
- Each field of the physical dice has its label and its help, and its error is attached to it.
