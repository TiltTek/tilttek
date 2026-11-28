<!-- phil-dev source-sha256=2a4e1a714cc6ae612a8a239d8b7aeec47b2051ca992f71396523777709c3b6fe -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Crafter` | Fait des aperçus, fabrique une seule fois par clé, liste ce qui est fabricable et le plus proche |
| `RecipeBook` | Les recettes par leurs codes, lues depuis des valeurs simples et réécrites |
| `Recipe` | Des ingrédients, des produits, des objets rendus, un genre et des attributs |
| `RecipeKind` | `Combine` ou `Evolve` |
| `Ingredient` | Un objet ou une étiquette, une quantité, consommé ou outil |
| `ItemQuantity` | Un nombre d'un objet |
| `Holding` | Ce qu'un détenteur a d'un objet, avec ses étiquettes |
| `Plan` | Ce qu'une fabrication prendrait, utiliserait, rendrait et produirait, et ce qui manque |
| `Requirement` | Un ingrédient d'un plan : nécessaire, mis de côté, manquant |
| `Selection` | Les objets qu'un membre choisit pour les ingrédients demandés par une étiquette |
| `Craft` | Une fabrication faite : clé, détenteur, recette, répétitions, objets, moment, objet qui a évolué |
| `Crafted` | Signalé une fois la fabrication appliquée, identifiants seulement |
| `Contract\Inventory` | Les objets des détenteurs et les fabrications qui leur sont appliquées |
| `Contract\ConsumptionPolicy` | Les objets qu'aucune fabrication ne consomme |
| `Contract\CraftObserver` | Ce que l'application fait une fois la fabrication appliquée |
| `Policy\ProtectedItems` | Une liste fixe d'objets qu'aucune fabrication ne consomme |
| `Store\InMemoryInventory` | L'inventaire en mémoire |
| `Exception\CraftingException` | Toute exception de la bibliothèque |
| `Exception\InvalidRecipe` | Une valeur hors limites |
| `Exception\RecipeNotFound` | Une recette que le livre ne contient pas |
| `Exception\CraftRefused` | Une fabrication qui ne peut pas se faire |
