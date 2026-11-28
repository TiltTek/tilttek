# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Crafter` | Previews, crafts once per key, lists what is craftable and closest |
| `RecipeBook` | The recipes by their codes, read from plain values and written back |
| `Recipe` | Ingredients, outputs, items given back, kind and attributes |
| `RecipeKind` | `Combine` or `Evolve` |
| `Ingredient` | An item or a tag, a quantity, consumed or a tool |
| `ItemQuantity` | A number of one item |
| `Holding` | What a holder has of one item, with its tags |
| `Plan` | What a craft would take, use, give back and produce, and what is missing |
| `Requirement` | One ingredient of a plan: needed, set aside, missing |
| `Selection` | The items a member chooses for the ingredients asked by a tag |
| `Craft` | A craft made: key, holder, recipe, repetitions, items, moment, item that evolved |
| `Crafted` | Told once a craft was applied, identifiers only |
| `Contract\Inventory` | The holders' items and the crafts applied to them |
| `Contract\ConsumptionPolicy` | The items no craft consumes |
| `Contract\CraftObserver` | What the application does once a craft was applied |
| `Policy\ProtectedItems` | A fixed list of items no craft consumes |
| `Store\InMemoryInventory` | The inventory in memory |
| `Exception\CraftingException` | Every exception of the library |
| `Exception\InvalidRecipe` | A value out of bounds |
| `Exception\RecipeNotFound` | A recipe the book does not hold |
| `Exception\CraftRefused` | A craft that cannot be made |
