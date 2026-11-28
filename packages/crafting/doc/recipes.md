# Recipes

A `Recipe` is data: a code, the ingredients it takes or needs, the items it produces, the items it gives back, its kind
and its attributes. It is always deterministic — it produces what it declares, every time. A random result belongs to
a loot table whose odds are published, drawn by the application after the craft.

## Ingredients

An `Ingredient` asks for one item by its code, `Ingredient::item('red-herb', 2)`, or for any items carrying a tag,
`Ingredient::tagged('rare-metal')` — a "rare metal" rather than one metal. Its quantity is 1 to 999.

A consumed ingredient is taken by the craft, its quantity once per repetition: crafting three potions takes six herbs.
A **tool** — `consumed: false`, a mortar, a hammer, a workshop — is only held: the craft needs its quantity once,
whatever the repetitions, and leaves it. A recipe consumes at least one ingredient, so that tools alone never produce
anything, and names an ingredient at most once as consumed and once as a tool.

## Outputs and items given back

The outputs are the items the craft produces, 1 to 8, each in a quantity of 1 to 999 per repetition. The items given
back, up to 8, are what is left of an ingredient — the empty bucket of a bucket of water, the flask of a potion —, also
per repetition. An item is named once among the outputs and once among the items given back.

## Evolving recipes

A recipe of the kind `RecipeKind::Evolve` turns one item into its next form: a dragon egg hatched by two fires into a
dragon, a blade tempered into a better blade. Its first ingredient is that item, by its code, consumed, in a quantity of
one; it has exactly one output, of another item, in a quantity of one. Other ingredients and items given back are free.
The craft names the item that evolved, so that an inventory that keeps objects — a copy with its own identifier — may
keep it as the same object under its new item.

## Attributes

A recipe carries up to 10 attributes for the application — `['minutes' => 10, 'workshop' => 'forge']` —: each named by
a code, its value an integer, a boolean or 1 to 100 characters of UTF-8 without control characters nor surrounding
spaces. The library never reads them: a larp counts the crafting time in game time, a site shows the workshop.

A code — of a recipe, an item, a tag or an attribute — is 1 to 96 lowercase letters, digits, `_`, `-`, or single dots
between them. The application translates the codes into names, `crafting.recipe.healing-potion`, so every text stays
out of the book.

## The book

A `RecipeBook` holds up to 1,000 recipes, each code once. `fromArray()` reads it from plain values — a YAML file, a
JSON column — and refuses every key it does not know; `toArray()` writes it back, every value spelled out:

```php
$book = RecipeBook::fromArray(['recipes' => [
    'healing-potion' => [
        'kind' => 'combine',                                  // or 'evolve'; 'combine' when left out
        'ingredients' => [
            ['item' => 'red-herb', 'quantity' => 2],
            ['tag' => 'clear-water'],                         // quantity 1 when left out
            ['item' => 'mortar', 'consumed' => false],        // a tool, held and never taken
        ],
        'outputs' => [['item' => 'healing-potion']],
        'returns' => [['item' => 'empty-bucket']],            // none when left out
        'attributes' => ['minutes' => 10],                    // none when left out
    ],
]]);
```

`recipe()` returns a recipe by its code, `has()` tells whether the book holds it, `recipes()` lists them in the order
the book was given them, `consuming()` lists those that consume an item named by its code — what a member can make of
what they hold — and `producing()` those that produce an item — how to get it.

## Protected items

An item a member paid for, or one the site keeps out of crafting, is never consumed. The book takes a
`Contract\ConsumptionPolicy` — `Policy\ProtectedItems` for a fixed list —: it refuses a recipe that consumes such an
item by its code, and a tag never picks one for a consumed ingredient. A protected item may still serve as a tool.
