<!-- phil-dev source-sha256=0db33ca93ec4ce948f2ad1b21e9c502b7993372407e4a24ce86dab955fca1ef8 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Listes et factions

| Type | Rôle |
|---|---|
| `Factions` | Le service : créer, ajouter, changer et retirer une faction, changer l'échelle, décaler ou fixer une position, fixer un rang, fixer et terminer des relations, céder, supprimer, lire l'historique, et voir. |
| `Roster` | Les factions d'une campagne, son échelle et les relations entre ses factions, vérifiées d'un bloc. |
| `Faction` | Une faction trouvée par sa clé : son nom, son résumé, ses rangs, ses positions, ses membres et son secret. |
| `Rank` | Un rang d'une faction, par sa clé, avec son titre. |
| `Membership` | Le rang qu'un personnage tient dans une faction. |
| `Standing` | Où se tient un sujet auprès d'une faction. |
| `Subject` | Le groupe, ou un personnage, et sa clé. |
| `Scale` | Les bornes, le début et les paliers des positions d'une campagne. |
| `Tier` | Un palier nommé d'une échelle, à partir de sa position la plus basse. |
| `Relation` | Comment deux factions se tiennent l'une envers l'autre, la paire conservée avec la clé la plus basse d'abord. |
| `Stance` | Alliée, amicale, neutre, rivale, hostile ou en guerre. |
| `Reason` | Pourquoi une position, un rang ou une relation a changé, et la session. |

## Historique

| Type | Rôle |
|---|---|
| `History\Entry` | Un changement de l'historique : sa campagne, sa faction, sa raison, son auteur et son moment. |
| `History\StandingEntry` | Une position a bougé, avant et après. |
| `History\RankEntry` | Un rang a changé, avant et après. |
| `History\RelationEntry` | Une relation a changé, avant et après. |

## Vues

| Type | Rôle |
|---|---|
| `View\RosterView` | Une liste telle que son lecteur la voit : l'échelle, les factions et les relations qu'il connaît. |
| `View\FactionView` | Une faction telle que montrée, avec les positions et les membres que son lecteur peut voir. |
| `View\StandingView` | Une position telle que montrée : le sujet, la valeur et son palier. |

## Ports et magasins

| Type | Rôle |
|---|---|
| `Contract\RosterStore` | Conserve les listes à une version avec leur historique, les supprime et lit l'historique d'une faction. |
| `Contract\RosterObserver` | Informé des événements d'une liste. |
| `Store\InMemoryRosterStore` | Les listes et leur historique en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\RosterEvent` | Un fait d'une liste, par identifiants seulement. |
| `Event\RosterCreated` | Une liste a été créée. |
| `Event\FactionAdded` | Une faction a été ajoutée. |
| `Event\FactionChanged` | Les détails d'une faction ont changé. |
| `Event\FactionRemoved` | Une faction a été retirée. |
| `Event\ScaleChanged` | L'échelle a changé. |
| `Event\StandingChanged` | Une position a bougé. |
| `Event\RankChanged` | Un rang a changé. |
| `Event\RelationChanged` | Une relation a changé. |
| `Event\RosterHandedOver` | La liste a changé de mains. |
| `Event\RosterDeleted` | La liste a été supprimée. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\FactionsException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidRoster` | Une valeur hors de sa forme ou de ses bornes, ou un nom que la liste n'a pas. |
| `Exception\RosterNotFound` | Aucune liste pour la campagne. |
| `Exception\RosterRefused` | Un appel non autorisé. |
| `Exception\RosterConflict` | La liste a changé depuis qu'elle a été lue. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme des identifiants et des clés. Interne. |
| `Internal\Text` | La forme des textes. Interne. |
