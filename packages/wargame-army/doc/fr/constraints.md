<!-- phil-dev source-sha256=382beeffbd1d189e0a4a6826788ac9011700de49c90d9e236cd4356d985b544e -->
# Contraintes

Une contrainte est une règle qu'une liste respecte au-delà des bornes propres à ses unités. Un catalogue déclare dans
ses données celles de la bibliothèque ; une application ajoute les siennes au validateur.

## Sélecteurs

Une contrainte compte les unités qu'un `Constraint\Selector` prend : une unité du catalogue, `Selector::unit('ogre')`,
ou chaque unité d'une catégorie, `Selector::category('hero')`. Dans les données d'un catalogue, une contrainte a soit
une `unit`, soit une `category`, jamais les deux.

## Comptes

`Constraint\UnitCount` contient le plus petit et le plus grand nombre d'unités sélectionnées, dans toute l'armée ou dans
chaque détachement pris à part (`Scope::Army`, `Scope::Detachment`). Elle a un minimum supérieur à 0, un maximum, ou les
deux.

```json
{"type": "count", "category": "leader", "min": 1}
{"type": "count", "unit": "ogre", "max": 1}
{"type": "count", "category": "hero", "scope": "detachment", "min": 1, "max": 1}
```

```text
At least 1 units of the category "leader" in the army; the army has 0.
At most 1 "Ogre" in the army; the army has 2.
At least 1 units of the category "hero" in each detachment; detachment 2 has 0.
```

## Parts de points

`Constraint\PointsShare` contient la part de la limite de points de la liste que les unités sélectionnées peuvent coûter,
en pourcentages entiers de 0 à 100 : une part minimale, une part maximale, ou les deux. Les parts se comparent
exactement, en entiers : 33 % d'une limite de 101 points permet 33 points et en refuse 34 ; une part minimale s'arrondit
à l'entier supérieur.

```json
{"type": "points-share", "category": "hero", "max_percent": 25}
{"type": "points-share", "category": "troops", "min_percent": 25}
```

```text
Units of the category "hero" cost at most 25 % of the points limit, 75 points; the list spends 105.
```

## Les contraintes propres à une application

Une classe qui implémente `Contract\Constraint` reçoit la liste, le catalogue et le `Costing`, et retourne ses
violations, chacune une `Violation` de `Rule::Constraint` avec son message et ses valeurs. Elle est passée au
validateur, qui la vérifie sur chaque liste après les contraintes du catalogue :

```php
final readonly class ClubNightCap implements Constraint
{
    public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array
    {
        return $costing->total > 400
            ? [new Violation(Rule::Constraint, 'Club nights are played at 400 points or less.', ['points' => $costing->total])]
            : [];
    }
}

$validator = new ArmyValidator($catalogues, new ClubNightCap());
```

Une contrainte ne garde rien entre deux appels. `Costing::count()` et `Costing::points()` additionnent les unités qu'un
sélecteur prend sans parcourir de nouveau la liste.
