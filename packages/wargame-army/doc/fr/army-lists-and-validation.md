<!-- phil-dev source-sha256=bd9030c6faa866bf0f3b4f9bb810b5ad43794d1431655c8c9ff3e74086cbf0e0 -->
# Listes d'armée et validation

## Une liste

Une `ArmyList` contient son identifiant, son nom, le code et la version de son catalogue, sa limite de points — de 1 à
1 000 000 —, et jusqu'à 20 détachements. `ArmyList::for($catalogue, $id, $name, $limit)` commence une liste vide à la
version courante du catalogue. Un `Detachment` a le code de son type, un nom facultatif que le joueur lui donne, et
jusqu'à 200 unités. Une `UnitSelection` a le code de son unité, son nombre de figurines, de 1 à 1 000, et jusqu'à 100
options prises, chacune avec un compte de 1 à 1 000.

Une liste ne change jamais ; chaque changement donne une nouvelle liste : `withDetachment()`, `withDetachmentAt()`,
`withoutDetachment()`, `renamed()`, `withPointsLimit()`, `withCatalogueVersion()`, et sur un détachement `withUnit()`,
`withUnitAt()`, `withoutUnit()`, et sur une unité `withModels()` et `withOption()`. Les positions se comptent à partir
de 0.

Une liste contient n'importe quel code : qu'une unité ou une option existe et soit permise est la question du
validateur, de sorte qu'une liste en construction n'est jamais refusée pour une unité qu'une nouvelle version du
catalogue a retirée. `ArmyList::fromArray()` et `fromJson()` lisent ce qu'écrivent `toArray()` et `toJson()`, chaque
défaut étant une `InvalidArmyList`.

## Points

`Costing::of($list, $catalogue)` donne les points de chaque unité (`$units`, par détachement), de chaque détachement
(`$detachments`) et de la liste (`$total`) ; `count()` et `points()` additionnent les unités qu'un sélecteur prend. Une
unité ou une option que le catalogue ne contient pas ne coûte rien ; le validateur la signale.

## Le validateur

`ArmyValidator::validate($list)` trouve le catalogue de la liste par le fournisseur — `UnknownCatalogue` quand il n'y en
a aucun — et `validateAgainst($list, $catalogue)` vérifie une liste selon un catalogue donné. Les règles viennent dans
cet ordre :

1. le catalogue que la liste nomme, et la version avec laquelle elle a été construite ;
2. le nombre de détachements, et le plus grand nombre de détachements de chaque type ;
3. chaque détachement, dans l'ordre : son type, puis chaque unité — inconnue, ses figurines, ses options, ses groupes
   d'options, l'emplacement qu'elle remplit —, puis les emplacements du détachement ;
4. la limite de points ;
5. les contraintes que le catalogue déclare, puis celles de l'application.

Chaque violation est retournée, aucune n'arrête les autres. `Validation` donne `isValid()`, `messages()` et `ofRule()`,
avec la liste, le catalogue et le `Costing`. Chaque `Violation` porte sa `Rule`, son message et les valeurs dont il a
été construit — `detachment` et `unit` comptés à partir de 1, `count`, `min`, `max`, `points`… —, pour une application
qui écrit ses propres messages ou les traduit.

| `Rule` | Message, par exemple |
|---|---|
| `Catalogue` | `The list is built with the catalogue "other"; it is checked against "ember-vale".` |
| `CatalogueVersion` | `The list was built with version 2025.9 of the catalogue "ember-vale"; the catalogue is at version 2026.1.` |
| `DetachmentCount` | `The army has 3 detachments; the catalogue "ember-vale" takes at most 2.` |
| `DetachmentLimit` | `The army has 2 detachments "Vanguard"; the catalogue allows at most 1.` |
| `UnknownDetachment` | `Detachment 3 "swamp": the catalogue has no detachment type "swamp".` |
| `UnknownUnit` | `Detachment 1 "Vanguard", unit 4 "dragon": the catalogue has no unit "dragon".` |
| `NotAllowed` | `Detachment 2 "Raid", unit 1 "Ogre": the detachment type "Raid" takes no unit of its categories.` |
| `Slot` | `Detachment 1 "Vanguard": 0 units of the category "troops"; the slot takes at least 1.` |
| `Models` | `Detachment 2 "Second", unit 1 "Scouts": 11 models; the unit takes 5 to 10.` |
| `UnknownOption` | `Detachment 1 "Vanguard", unit 1 "Captain": the unit has no option "axe".` |
| `OptionLimit` | `Detachment 1 "Vanguard", unit 1 "Captain": the option "Banner" is taken 3 times; at most 1 time.` |
| `OptionGroup` | `Detachment 1 "Vanguard", unit 1 "Captain": 0 selections in "Weapon"; the group takes at least 1.` |
| `PointsLimit` | `The list costs 579 points; its limit is 300.` |
| `Constraint` | `At most 1 "Ogre" in the army; the army has 2.` |
