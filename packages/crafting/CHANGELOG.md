# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/crafting` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Ingredient`: an item named by its code or any items carrying a tag, in a quantity of 1 to 999, consumed once per
  repetition or held as a tool once for every repetition.
- `Recipe` and `RecipeKind`: a recipe that combines or evolves, with 1 to 16 ingredients, at least one of them
  consumed, 1 to 8 outputs, up to 8 items given back and up to 10 attributes; an evolving recipe takes one item, its
  first ingredient, into one item of another kind.
- `RecipeBook`: up to 1,000 recipes by their codes, read from plain values with every unknown key refused and written
  back to them; the recipes consuming or producing an item; a recipe consuming a protected item refused.
- `Contract\ConsumptionPolicy`, with `Policy\ProtectedItems`: items no craft consumes, which may still serve as tools.
- `Crafter`: previews of a recipe repeated 1 to 100 times; items set aside by code first, then the items chosen for a
  tag, then the other tags, the one fewest held items carry first, each from its items in the order of their codes;
  the recipes craftable now; the recipes closest to craftable, the fewest items missing first; crafts made once per
  key, at once or not at all, a key that made another craft refused.
- `Plan`, `Requirement` and `Selection`: each ingredient with its items set aside and what is missing, the items
  consumed, used, given back and produced; the items a member chooses for an ingredient asked by a tag.
- `Craft`, with the item that evolved; `Crafted`, told to each `Contract\CraftObserver`, identifiers only.
- `Contract\Inventory` and `Holding`, with `Store\InMemoryInventory`.
- `Exception\InvalidRecipe`, `Exception\RecipeNotFound` and `Exception\CraftRefused`, under the
  `Exception\CraftingException` interface.
- A fuzz target of the books read from plain values, and a bench of the fullest book for a holder of 500 items.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
