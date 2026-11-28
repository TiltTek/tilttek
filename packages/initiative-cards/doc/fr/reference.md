<!-- phil-dev source-sha256=92a44b0d65189554a4c6c8e64439061caaa03aa23441369e106730bc9cb4ffe8 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Rencontres

| Type | Rôle |
|---|---|
| `InitiativeTracker` | Le service : commencer, distribuer, terminer un tour, joindre, partir, vaincre, relever, cacher, révéler, terminer et voir. |
| `Encounter` | Une rencontre : son maître de jeu, son jeu, ses piles, ses combattants dans l'ordre des tours, sa ronde et sa version. |
| `Combatant` | Un combattant : son contrôleur, s'il est caché ou vaincu, ses pioches, sa carte et s'il a agi. |
| `StandardRanking` | Le classement du jeu de 52 cartes et de ses jokers, avec un ordre des couleurs. |
| `View\InitiativeView` | Ce qu'un spectateur peut voir d'une rencontre. |
| `View\Slot` | Un combattant tel qu'un spectateur le voit. |

## Ports et stockages

| Type | Rôle |
|---|---|
| `Contract\EncounterStore` | Garde les rencontres à une version. |
| `Contract\CardRanking` | Ordonne les faces d'un jeu, et dit lesquelles remélangent. |
| `Contract\InitiativeObserver` | Informé des événements d'une rencontre. |
| `Store\InMemoryEncounterStore` | Les rencontres en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\InitiativeEvent` | Chaque événement : la rencontre. |
| `Event\CombatantEvent` | Les événements d'un combattant : la rencontre et le combattant. |
| `Event\EncounterStarted` | Une rencontre a commencé. |
| `Event\RoundDealt` | Une ronde a été distribuée. |
| `Event\DeckReshuffled` | La défausse a été remise dans la pioche. |
| `Event\TurnStarted` | Le tour d'un combattant a commencé. |
| `Event\TurnEnded` | Le tour d'un combattant a pris fin. |
| `Event\RoundEnded` | Tous les combattants debout ont agi. |
| `Event\CombatantJoined` | Un combattant s'est joint. |
| `Event\CombatantLeft` | Un combattant est parti. |
| `Event\CombatantDefeated` | Un combattant a été vaincu. |
| `Event\CombatantRevived` | Un combattant vaincu est de nouveau debout. |
| `Event\CombatantRevealed` | Un combattant caché a été révélé. |
| `Event\CombatantHidden` | Un combattant a été caché. |
| `Event\EncounterEnded` | Une rencontre a été terminée. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\InitiativeException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidEncounter` | Une valeur sort de sa forme ou de ses bornes. |
| `Exception\InitiativeRefused` | Un changement que le gestionnaire n'autorise pas. |
| `Exception\EncounterNotFound` | Aucune rencontre ni aucun combattant n'a l'identifiant. |
| `Exception\EncounterConflict` | La rencontre a changé depuis sa lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant. |
