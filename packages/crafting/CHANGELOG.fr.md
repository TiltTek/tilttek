<!-- phil-dev source-sha256=a0b707a65b3dbcc49a2c35b9f557b05341ba257593292da80785054fa9fe6ec0 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/crafting` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Ingredient` : un objet nommé par son code ou n'importe quels objets qui portent une étiquette, en une quantité de 1 à
  999, consommé une fois par répétition ou détenu comme outil une fois pour toutes les répétitions.
- `Recipe` et `RecipeKind` : une recette qui combine ou fait évoluer, avec de 1 à 16 ingrédients dont au moins un
  consommé, de 1 à 8 produits, jusqu'à 8 objets rendus et jusqu'à 10 attributs ; une recette qui fait évoluer prend un
  objet, son premier ingrédient, et le transforme en un objet d'un autre genre.
- `RecipeBook` : jusqu'à 1 000 recettes par leurs codes, lues depuis des valeurs simples en refusant toute clé inconnue
  et réécrites vers elles ; les recettes qui consomment ou produisent un objet ; une recette qui consomme un objet
  protégé est refusée.
- `Contract\ConsumptionPolicy`, avec `Policy\ProtectedItems` : les objets qu'aucune fabrication ne consomme, et qui
  peuvent quand même servir d'outils.
- `Crafter` : des aperçus d'une recette répétée de 1 à 100 fois ; les objets mis de côté par code d'abord, puis les
  objets choisis pour une étiquette, puis les autres étiquettes, celle que portent le moins d'objets détenus d'abord,
  chacune depuis ses objets dans l'ordre de leurs codes ; les recettes fabricables maintenant ; les recettes les plus
  proches d'être fabricables, celles où il manque le moins d'objets d'abord ; des fabrications faites une seule fois
  par clé, en entier ou pas du tout, une clé qui a fait une autre fabrication étant refusée.
- `Plan`, `Requirement` et `Selection` : chaque ingrédient avec ses objets mis de côté et ce qui manque, les objets
  consommés, utilisés, rendus et produits ; les objets qu'un membre choisit pour un ingrédient demandé par une
  étiquette.
- `Craft`, avec l'objet qui a évolué ; `Crafted`, signalé à chaque `Contract\CraftObserver`, identifiants seulement.
- `Contract\Inventory` et `Holding`, avec `Store\InMemoryInventory`.
- `Exception\InvalidRecipe`, `Exception\RecipeNotFound` et `Exception\CraftRefused`, sous l'interface
  `Exception\CraftingException`.
- Une cible de fuzzing des livres lus depuis des valeurs simples, et un banc d'essai du livre le plus plein pour un
  détenteur de 500 objets.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
