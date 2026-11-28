# Exceptions

Every exception of the library implements `Exception\CraftingException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `InvalidRecipe` | A code, a quantity, a recipe, a book, a selection, a key, repetitions or a limit out of bounds | What is out of bounds, and the bound |
| `RecipeNotFound` | The book holds no recipe of that code | `The recipe book holds no recipe "…".` |
| `CraftRefused` | Items are missing, a key made another craft, or the inventory refused | Why, with what is missing |

`InvalidRecipe` extends `InvalidArgumentException`, `RecipeNotFound` `OutOfBoundsException` and `CraftRefused`
`RuntimeException`.

## The messages of `InvalidRecipe`

| Value | Message |
|---|---|
| A code | `The code of an item must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "…" is not.`, and likewise for a recipe, a tag, an attribute and a protected item |
| A quantity | `The quantity of the ingredient … is 1 to 999; it is ….`, and likewise for the others |
| A key | `The key of a craft is 1 to 191 visible ASCII characters, without spaces.` |
| The tags of a holding | `An item carries at most 16 tags; "…" has ….` |
| The ingredients | `The recipe "…" lists 1 to 16 ingredients; it has ….` |
| An ingredient named twice | `The recipe "…" names the ingredient … twice.` |
| Tools only | `The recipe "…" consumes at least one ingredient; tools alone produce nothing.` |
| The outputs or items given back | `The recipe "…" lists 1 to 8 outputs; it has ….`, `The recipe "…" names "…" twice among its outputs.` |
| An output's quantity | `The quantity of "…" in the recipe "…" is 1 to 999; it is ….` |
| The attributes | `A recipe carries at most 10 attributes; "…" has ….`, `The attribute "…" is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.` |
| An evolving recipe | `The evolving recipe "…" takes its first ingredient, one item named by its code, and consumes it.`, `The evolving recipe "…" produces one item of another kind than "…".` |
| The book | `A recipe book holds at most 1000 recipes; this one has ….`, `The recipe book holds the recipe "…" twice.` |
| A protected item | `The recipe "…" consumes "…", which no craft may consume.` |
| Plain values | `A recipe book has no "…"; it knows recipes.`, and a message naming the value and its expected form for every other |
| A selection | `A selection names the ingredients by their position, 0 to 15; … is not one.`, `A selection chooses 1 to 16 items for an ingredient; … were chosen for the ingredient ….` |
| A choice | `A selection chooses items for an ingredient asked by a tag; the ingredient … of the recipe "…" is not one.`, `The items chosen for the ingredient … of the recipe "…" add up to …; it needs ….`, `"…" cannot serve the ingredient … of the recipe "…".` |
| The closest recipes | `The closest recipes are 1 to 50; … is out of bounds.` |

## The messages of `CraftRefused`

| Case | Message |
|---|---|
| Items missing | `The recipe "…" cannot be crafted: 1 × #metal, 2 × iron missing.` |
| A key that made another craft | `The key "…" already made another craft.` |
| A key recorded without its craft | `The key "…" is recorded, yet its craft cannot be read.` |
| Too many holdings | `The crafter reads at most 10000 holdings of a holder.` |
| An item given twice | `The inventory gives one holding per item; "…" came twice.` |
| The in-memory inventory | `The craft "…" needs … × "…"; the holder has ….` |

The exceptions of `phil/resource` reach the caller unchanged when a reference is malformed.
