<!-- phil-dev source-sha256=d2a0f2fea7fa4dd1ad721e333909e7c62b79e98423c6be784bd33517ed60f318 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Les règles

| Type | Rôle |
|---|---|
| `SystemRules` | Les activités que déclarent les règles d'un système de jeu, chaque clé une fois. |
| `Activity` | Une activité : son nom, ses jours, ses coûts, ses issues et sa résolution. |
| `Cost` | Ce qu'une activité coûte dans une ressource, une fois ou chaque jour. |
| `CostBasis` | Une fois ou par jour. |
| `Outcome` | Un résultat par lequel une activité peut se terminer : son libellé, son texte et ses effets. |
| `Effect` | Un gain ou une perte dans une ressource. |
| `Resolution` | Comment une activité se termine : une issue fixe, ou la plage où tombe son jet. |
| `Band` | Les totaux d'un jet qui terminent une activité par une issue. |

## Le journal

| Type | Rôle |
|---|---|
| `Downtime` | Le service : choisir, faire avancer une campagne, enregistrer un jet, confirmer, annuler, lire un journal et une entrée. |
| `Entry` | Une activité dans le journal d'un personnage, avec les jours écoulés, le jet, l'issue et son étape. |
| `Stage` | En file, en attente de son jet, résolue, confirmée ou annulée. |

## Les ports et les implémentations en mémoire

| Type | Rôle |
|---|---|
| `Contract\EntryStore` | Conserve les entrées à une version, toutes ou aucune, et les lit dans l'ordre choisi. |
| `Contract\RuleBook` | Les activités que déclarent les règles de chaque système. |
| `Contract\Roster` | Qui mène une campagne et qui joue chaque personnage. |
| `Contract\Roller` | Le total d'un lancer d'une expression de dés. |
| `Contract\DowntimeObserver` | Averti des événements des entrées. |
| `Rules\InMemoryRuleBook` | Les règles des systèmes qu'une application déclare une fois. |
| `Store\InMemoryEntryStore` | Les entrées en mémoire, pour les tests. |

## Les événements

| Type | Rôle |
|---|---|
| `Event\DowntimeEvent` | Tout événement : l'entrée, sa campagne et son personnage. |
| `Event\ActivityChosen` | Un personnage a choisi une activité. |
| `Event\ActivityProgressed` | Des jours ont été dépensés sur une activité, et il en reste. |
| `Event\ActivityCompleted` | Tous les jours d'une activité ont été dépensés, et son jet est attendu. |
| `Event\ActivityResolved` | Une activité s'est terminée par une issue. |
| `Event\ActivityConfirmed` | Le maître de jeu a confirmé l'issue. |
| `Event\ActivityCancelled` | Une activité a été annulée. |

## Les exceptions

| Type | Rôle |
|---|---|
| `Exception\DowntimeException` | Toute exception de la bibliothèque. |
| `Exception\InvalidDowntime` | Une valeur hors de sa forme ou de ses bornes, ou des parties qui ne s'accordent pas. |
| `Exception\UnknownActivity` | Les règles du système ne déclarent aucune activité de cette clé. |
| `Exception\DowntimeRefused` | Un changement que le service n'autorise pas. |
| `Exception\EntryNotFound` | Aucune entrée n'a cet identifiant. |
| `Exception\DowntimeConflict` | Une entrée a changé depuis qu'elle a été lue. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant et d'une clé, et la première valeur d'une liste qui revient. |
| `Internal\Text` | Une ligne ou des lignes de texte, vérifiées et bornées. |
