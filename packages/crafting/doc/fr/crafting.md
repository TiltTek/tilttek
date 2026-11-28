<!-- phil-dev source-sha256=15656030d5ec3faca41b0ac1457453596e61042b183ce90f89b6721826c63a3b -->
# Fabrication

`Crafter` fabrique depuis le livre avec ce que les détenteurs ont. Il lit les objets d'un détenteur par le
`Contract\Inventory` de l'application : un `Holding` par objet, avec sa quantité et les étiquettes qu'il porte.

## Aperçus et ce qui manque

`preview($holder, $recipe, $times, $selection)` renvoie un `Plan` et ne déplace rien. Le plan contient un `Requirement`
par ingrédient, dans l'ordre de la recette : combien il en `needed`, les objets du détenteur mis de côté pour lui dans
`allocation`, `held()`, `missing()` et `isMet()`.

| Méthode de `Plan` | Ce qu'elle renvoie |
|---|---|
| `isCraftable()` | Si chaque ingrédient est satisfait |
| `missing()` | Les besoins non satisfaits, dans l'ordre de la recette |
| `missingCount()`, `heldCount()` | Combien d'objets manquent, et sont détenus, pour tous les ingrédients |
| `consumed()`, `used()` | Les objets que la fabrication prend, et les outils qu'elle a besoin de voir détenus, dans l'ordre de leurs codes |
| `returned()`, `produced()` | Les objets qu'elle rend et produit, répétés `$times` fois |

Une recette se répète de 1 à 100 fois.

## Comment les objets sont mis de côté

Le fabricant met les objets de côté ingrédient par ingrédient, et un objet mis de côté pour l'un ne sert à aucun autre :

1. les ingrédients nommés par le code d'un objet, dans l'ordre de la recette ;
2. les ingrédients demandés par une étiquette pour laquelle le membre a choisi des objets ;
3. les autres ingrédients demandés par une étiquette, celle que portent le moins d'objets détenus d'abord, les égalités
   dans l'ordre de la recette.

Un ingrédient demandé par une étiquette prend ses objets dans l'ordre de leurs codes. Servir d'abord les étiquettes les
plus rares évite qu'une recette qui demande « deux métaux » et « un métal rare » dépense l'unique métal rare pour les
métaux. Un objet protégé ne sert jamais un ingrédient consommé.

## Choisir les objets d'une étiquette

Une `Selection` nomme, par la position d'un ingrédient dans sa recette, les objets qu'un membre choisit pour lui — les
métaux qui entrent dans la lame :

```php
use Tilttek\Crafting\Selection;

$crafter->craft($member, 'blade', 'craft-91c2', selection: new Selection([0 => ['copper' => 1, 'iron' => 1]]));
```

Une sélection choisit seulement pour des ingrédients demandés par une étiquette, de 1 à 16 objets chacun. Les quantités
choisies s'additionnent pour donner ce dont l'ingrédient a besoin ; un objet choisi que le détenteur a porte
l'étiquette, et n'est pas protégé quand l'ingrédient est consommé. Un objet choisi que le détenteur n'a pas est compté
comme manquant.

## Fabriquer une seule fois par clé

`craft($holder, $recipe, $key, $times, $selection)` planifie la fabrication et, quand chaque ingrédient est satisfait,
remet un `Craft` à l'inventaire, qui l'applique en entier ou pas du tout : il retire ce que la fabrication consomme,
ajoute ce qu'elle rend et produit, et l'enregistre sous sa clé.

La clé — de 1 à 191 caractères ASCII visibles, l'identifiant de la requête du membre — fait compter la fabrication une
seule fois. La même clé redonnée renvoie la fabrication qu'elle a faite, sans fabriquer de nouveau, même une fois les
objets partis ; une clé qui a fait une autre fabrication — un autre détenteur, une autre recette ou un autre nombre de
répétitions — est refusée. Une fabrication dont un objet manque est refusée avec ce qui manque, et rien ne bouge.

Le `Craft` contient sa clé, son détenteur, sa recette, ses répétitions, les objets consommés, utilisés, rendus et
produits, son moment, et, pour une recette qui fait évoluer, l'objet qui a évolué. Une fois appliqué, chaque
`Contract\CraftObserver` est informé d'un `Crafted` — le détenteur, la recette, la clé et le moment, identifiants
seulement. Une fabrication retrouvée sous sa clé n'est pas signalée deux fois.

## Fabricable et le plus proche

`craftable($holder)` liste les plans des recettes que le détenteur peut fabriquer une fois maintenant, dans l'ordre du
livre. `closest($holder, $limit)` liste les recettes que le détenteur ne peut pas encore fabriquer mais pour lesquelles
il détient quelque chose, celles où il manque le moins d'objets d'abord, les égalités dans l'ordre du livre — de 1 à
50, 10 par défaut : ce qu'il faut chercher ensuite. Chacune lit une fois les objets du détenteur pour chaque recette.

Le fabricant lit au plus 10 000 avoirs d'un détenteur, un par objet.

## Effacement

Les fabrications et les objets vivent dans l'inventaire de l'application, qui efface un détenteur par ses propres
moyens ; `Store\InMemoryInventory::forget()` retire les objets et les fabrications d'un détenteur.
