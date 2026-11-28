<!-- phil-dev source-sha256=78d09f1c258238ada3182e2ae77662d0b9bf5f9259f2f8c4ca78d8d1edf319aa -->
# Exceptions

Toute exception de la bibliothèque implémente `Exception\CraftingException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Lancée quand | Message |
|---|---|---|
| `InvalidRecipe` | Un code, une quantité, une recette, un livre, une sélection, une clé, des répétitions ou une limite est hors limites | Ce qui est hors limites, et la limite |
| `RecipeNotFound` | Le livre ne contient aucune recette de ce code | `The recipe book holds no recipe "…".` |
| `CraftRefused` | Des objets manquent, une clé a fait une autre fabrication, ou l'inventaire a refusé | Pourquoi, avec ce qui manque |

`InvalidRecipe` étend `InvalidArgumentException`, `RecipeNotFound` étend `OutOfBoundsException` et `CraftRefused` étend
`RuntimeException`.

## Les messages de `InvalidRecipe`

| Valeur | Message |
|---|---|
| Un code | `The code of an item must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "…" is not.`, et de même pour une recette, une étiquette, un attribut et un objet protégé |
| Une quantité | `The quantity of the ingredient … is 1 to 999; it is ….`, et de même pour les autres |
| Une clé | `The key of a craft is 1 to 191 visible ASCII characters, without spaces.` |
| Les étiquettes d'un avoir | `An item carries at most 16 tags; "…" has ….` |
| Les ingrédients | `The recipe "…" lists 1 to 16 ingredients; it has ….` |
| Un ingrédient nommé deux fois | `The recipe "…" names the ingredient … twice.` |
| Des outils seulement | `The recipe "…" consumes at least one ingredient; tools alone produce nothing.` |
| Les produits ou les objets rendus | `The recipe "…" lists 1 to 8 outputs; it has ….`, `The recipe "…" names "…" twice among its outputs.` |
| La quantité d'un produit | `The quantity of "…" in the recipe "…" is 1 to 999; it is ….` |
| Les attributs | `A recipe carries at most 10 attributes; "…" has ….`, `The attribute "…" is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.` |
| Une recette qui fait évoluer | `The evolving recipe "…" takes its first ingredient, one item named by its code, and consumes it.`, `The evolving recipe "…" produces one item of another kind than "…".` |
| Le livre | `A recipe book holds at most 1000 recipes; this one has ….`, `The recipe book holds the recipe "…" twice.` |
| Un objet protégé | `The recipe "…" consumes "…", which no craft may consume.` |
| Des valeurs simples | `A recipe book has no "…"; it knows recipes.`, et un message qui nomme la valeur et sa forme attendue pour chacune des autres |
| Une sélection | `A selection names the ingredients by their position, 0 to 15; … is not one.`, `A selection chooses 1 to 16 items for an ingredient; … were chosen for the ingredient ….` |
| Un choix | `A selection chooses items for an ingredient asked by a tag; the ingredient … of the recipe "…" is not one.`, `The items chosen for the ingredient … of the recipe "…" add up to …; it needs ….`, `"…" cannot serve the ingredient … of the recipe "…".` |
| Les recettes les plus proches | `The closest recipes are 1 to 50; … is out of bounds.` |

## Les messages de `CraftRefused`

| Cas | Message |
|---|---|
| Des objets manquent | `The recipe "…" cannot be crafted: 1 × #metal, 2 × iron missing.` |
| Une clé qui a fait une autre fabrication | `The key "…" already made another craft.` |
| Une clé enregistrée sans sa fabrication | `The key "…" is recorded, yet its craft cannot be read.` |
| Trop d'avoirs | `The crafter reads at most 10000 holdings of a holder.` |
| Un objet donné deux fois | `The inventory gives one holding per item; "…" came twice.` |
| L'inventaire en mémoire | `The craft "…" needs … × "…"; the holder has ….` |

Les exceptions de `phil/resource` parviennent telles quelles à l'appelant quand une référence est mal formée.
