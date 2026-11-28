<!-- phil-dev source-sha256=27753687bb6b0c2dbb011fda6b4c7d50077bf588fcd84f48b5ed4f2dd41781d4 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Rencontres

| Type | Rôle |
|---|---|
| `Encounter` | Une rencontre : son meneur de jeu, son système de jeu, son titre, ses participants en ordre, si elle a été lancée, sa révision. |
| `Participant` | Une ligne d'une rencontre : un personnage, ou des copies d'un adversaire ou d'un allié, avec sa cote, son contrôleur, sa visibilité, son bonus et sa fiche. |
| `Kind` | Personnage, adversaire ou allié. |
| `EncounterPlanner` | Le service : préparer, retitrer, ajouter, modifier, déplacer, retirer, évaluer, lister, lancer et oublier. |
| `Rulebooks` | Les règles de difficulté de chaque système de jeu, trouvées par sa clé ou par son `GameSystem` ; lues dans les nombres de difficulté des systèmes, avec celles de l'application à leur place. |

## Difficulté

| Type | Rôle |
|---|---|
| `Difficulty\Assessment` | Le coût, les paliers avec leurs seuils, et le palier atteint. |
| `Difficulty\Band` | Un palier de difficulté et son seuil. |
| `Difficulty\BudgetRules` | Des règles par budget : des montants selon le niveau du personnage, des coûts selon le défi de l'adversaire, des multiplicateurs selon le nombre. |
| `Difficulty\LevelRules` | Des règles par niveau : des budgets pour un groupe standard, des ajustements selon sa taille, des coûts selon l'écart de niveau. |
| `Difficulty\Multiplier` | Le pourcentage par lequel le coût est multiplié à partir d'un nombre d'adversaires. |
| `Difficulty\Declaration` | Lit des règles de difficulté dans les nombres de difficulté d'un système, ou dans des données simples. |

## Initiative

| Type | Rôle |
|---|---|
| `Initiative\InitiativeRoster` | Ce que reçoit un suivi : la rencontre, son meneur de jeu, son système et ses combattants en ordre. |
| `Initiative\RosterEntry` | Un combattant : son identifiant, son participant, son genre, son contrôleur, sa visibilité, son bonus et sa fiche. |

## Ports et magasins

| Type | Rôle |
|---|---|
| `Contract\EncounterStore` | Garde chaque rencontre à une révision. |
| `Contract\EncounterObserver` | Informé de chaque fait une fois la rencontre gardée. |
| `Contract\DifficultyRules` | Les règles de difficulté d'un système de jeu. |
| `Contract\InitiativeHandOff` | Remet une rencontre lancée à un suivi d'initiative. |
| `Store\InMemoryEncounterStore` | Les rencontres en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\EncounterEvent` | Un fait d'une rencontre, par identifiants seulement. |
| `Event\EncounterPrepared` | Une nouvelle rencontre a été préparée. |
| `Event\EncounterRetitled` | Son titre a changé ou a été retiré. |
| `Event\ParticipantAdded` | Un participant a été ajouté. |
| `Event\ParticipantChanged` | Un participant a été modifié. |
| `Event\ParticipantMoved` | Un participant s'est déplacé dans l'ordre. |
| `Event\ParticipantRemoved` | Un participant a été retiré. |
| `Event\EncounterLaunched` | La rencontre a été lancée. |
| `Event\EncounterForgotten` | La rencontre a été retirée du magasin. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\EncounterException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidEncounter` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\EncounterRefused` | Un appel non permis sur la rencontre dans son état. |
| `Exception\EncounterNotFound` | Aucune rencontre de cet identifiant. |
| `Exception\EncounterConflict` | La rencontre a changé depuis sa lecture, ou existe déjà. |
| `Exception\UnknownSystem` | Aucune règle de difficulté pour le système de jeu. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant. |
| `Internal\Rating` | La forme d'une cote. |
| `Internal\Text` | La forme d'une ligne de texte. |
