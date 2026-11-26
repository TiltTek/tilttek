<!-- phil-dev source-sha256=239d70c163f00df27df8263fd0239278b3bbfb3a7365b7d5f08b0ddd3f3542ca -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Préparations

| Type | Rôle |
|---|---|
| `SessionPreparer` | Le service : créer, modifier, lier, marquer les faits, rendre prête, fermer, rouvrir, reporter, supprimer et lire. |
| `Preparation` | La préparation d'une séance : son titre, sa séance, ses parties et son statut. |
| `PreparationStatus` | Brouillon, prête ou fermée. |
| `Part` | Un élément d'une préparation, retrouvé par sa clé. |
| `Scene` | Une scène qui pourrait avoir lieu, les personnages et les lieux qu'elle met en scène, et son statut. |
| `SceneStatus` | Planifiée, jouée ou sautée. |
| `Secret` | Quelque chose que les joueurs peuvent apprendre, ses indices, et s'il est révélé. |
| `Clue` | Une façon de tomber sur un secret, et si elle a été trouvée. |
| `NonPlayerCharacter` | Un personnage que joue le maître de jeu, et sa fiche ailleurs. |
| `Place` | Un lieu où les personnages peuvent aller, et sa fiche ailleurs. |
| `Note` | Une note libre du maître de jeu. |
| `ChecklistItem` | Une étape de la préparation, exigée ou non, et si elle est cochée. |
| `ChecklistTemplate` | Les étapes données à une préparation comme liste de contrôle ; `standard()` est un gabarit général. |
| `PreparationQuery` | Quelles préparations lire : d'une campagne, d'un maître de jeu, pour une séance, dans un statut. |

## Ports et dépôts

| Type | Rôle |
|---|---|
| `Contract\PreparationStore` | Garde les préparations à une version, les supprime et les lit par requête. |
| `Contract\PreparationObserver` | Informé des événements d'une préparation. |
| `Store\InMemoryPreparationStore` | Les préparations en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\PreparationEvent` | Chaque événement : la préparation. |
| `Event\PreparationCreated` | Une préparation a été créée. |
| `Event\PreparationChanged` | Ses parties ont été changées. |
| `Event\SessionLinked` | Elle a été liée à une séance, ou à aucune. |
| `Event\SceneMarked` | Une scène a été jouée, sautée, ou de nouveau planifiée. |
| `Event\SecretMarked` | Un secret a été révélé, ou de nouveau caché. |
| `Event\ClueMarked` | Un indice a été trouvé, ou non. |
| `Event\ItemMarked` | Un élément de la liste de contrôle a été coché, ou non. |
| `Event\PreparationReady` | Elle est prête pour la table. |
| `Event\PreparationReopened` | Elle est de nouveau un brouillon. |
| `Event\PreparationClosed` | Elle a été fermée. |
| `Event\PreparationCarriedOver` | Ce qu'une autre a laissé a été reporté dedans. |
| `Event\PreparationDeleted` | Elle a été supprimée. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\SessionPrepException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidPreparation` | Une valeur hors de sa forme ou de ses bornes, ou une partie qu'aucune préparation n'a. |
| `Exception\PreparationRefused` | Un changement que le service n'autorise pas. |
| `Exception\PreparationNotFound` | Aucune préparation n'a l'identifiant. |
| `Exception\PreparationConflict` | La préparation a changé depuis qu'elle a été lue. |

## Internes

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant et d'une clé, et les listes de clés distinctes. |
| `Internal\Text` | Une ligne ou des lignes de texte, vérifiées et bornées. |
| `Internal\Parts` | Des listes de parties par clé : distinctes, retrouvées, placées, retirées et déplacées. |
