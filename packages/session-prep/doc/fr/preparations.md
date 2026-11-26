<!-- phil-dev source-sha256=ebea4f60212d96ab98addcb0a530d549f1e581ed55fae05ce015ac3c96d3bfca -->
# Préparations

## Une préparation

Une `Preparation` est la préparation, par le maître de jeu, d'une séance d'une campagne :

| Valeur | Bornes | Sens |
|---|---|---|
| `id` | un identifiant | la préparation |
| `campaign` | un identifiant | la campagne, qu'un changement ne déplace jamais |
| `gameMaster` | un identifiant | le membre qui la prépare et la modifie |
| `title` | une ligne, de 1 à 200 caractères | comment le maître de jeu l'appelle |
| `session` | un identifiant, ou null par défaut | la séance pour laquelle elle est faite, dans l'horaire de l'application |
| `scenes` | de 0 à 50 `Scene` | les scènes qui pourraient avoir lieu, dans leur ordre |
| `secrets` | de 0 à 50 `Secret` | ce que les joueurs peuvent apprendre, avec ses indices |
| `characters` | de 0 à 100 `NonPlayerCharacter` | les personnages que joue le maître de jeu |
| `places` | de 0 à 50 `Place` | les lieux où les personnages peuvent aller |
| `notes` | de 0 à 100 `Note` | des notes libres |
| `checklist` | de 0 à 50 `ChecklistItem` | les étapes de la préparation |
| `status` | un `PreparationStatus`, `Draft` par défaut | en rédaction, prête ou fermée |
| `version` | 0 ou plus, 0 par défaut | 0 pour une nouvelle préparation ; sinon la version du dépôt |

Un identifiant compte de 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, et commence par une lettre : une référence
opaque, jamais un nom ou une adresse. L'identifiant de la séance est celui que donne le planificateur de l'application,
comme une séance de `tilttek/session-scheduler` ; la bibliothèque n'en lit rien.

## Ses parties

Chaque partie implémente `Part` : sa `key`, de 1 à 64 lettres, chiffres, `_`, `.`, `:` ou `-` commençant par une
lettre, est unique parmi les parties de son genre dans la préparation, et la nomme dans chaque appel.

| Partie | Valeurs |
|---|---|
| `Scene` | `title`, une ligne de 1 à 200 caractères ; `summary`, des lignes d'au plus 4000 ; `characters`, les clés d'au plus 20 personnages qu'elle met en scène ; `places`, les clés d'au plus 10 lieux où elle se passe ; `status`, un `SceneStatus` : `Planned`, `Played` ou `Skipped` |
| `Secret` | `text`, des lignes de 1 à 1000 caractères ; `clues`, au plus 10 `Clue` ; `revealed` |
| `Clue` | `text`, une ligne de 1 à 500 caractères — une lettre, une rumeur, une piste — ; `found` |
| `NonPlayerCharacter` | `name`, une ligne de 1 à 120 caractères ; `role`, une ligne d'au plus 200 — ce qu'il veut, ce qu'il fait — ; `notes`, des lignes d'au plus 2000 ; `reference`, l'identifiant de sa fiche ailleurs, ou null |
| `Place` | `name`, une ligne de 1 à 120 caractères ; `description`, des lignes d'au plus 2000 ; `reference`, l'identifiant de sa fiche ailleurs, ou null |
| `Note` | `text`, des lignes de 1 à 4000 caractères |
| `ChecklistItem` | `label`, une ligne de 1 à 200 caractères ; `required`, si la préparation l'attend avant d'être prête ; `checked` |

Un texte est de l'UTF-8 valide, sans espaces aux extrémités, et compté en caractères. Une ligne ne contient aucun
caractère de contrôle ; des lignes peuvent contenir des sauts de ligne et des tabulations, leurs fins étant écrites
`\n`. Aucun texte ne contient les caractères qui intègrent, remplacent ou isolent le sens d'un texte. Une scène ne met
en scène que les personnages et les lieux que sa préparation contient.

Un secret n'est lié à aucune scène : il sort là où il convient, et un indice trouvé ne le révèle pas, c'est le maître
de jeu qui dit quand il l'est.

## Modifier une préparation

Une préparation est une valeur : chaque méthode en retourne une nouvelle, vérifiée dans son ensemble, et laisse la
première telle qu'elle était.

| Méthode | Retourne la préparation |
|---|---|
| `withTitle()`, `withGameMaster()`, `withSession()`, `withStatus()`, `withVersion()` | avec cette valeur |
| `withScene()`, `withSecret()`, `withCharacter()`, `withPlace()`, `withNote()`, `withItem()` | avec la partie à la place de la partie de sa clé, ou après les autres |
| `withoutScene()`, `withoutSecret()`, `withoutNote()`, `withoutItem()` | sans la partie de la clé |
| `withoutCharacter()`, `withoutPlace()` | sans la partie de la clé, ni aucune scène qui la met en scène |
| `moveScene($key, $before)`, `moveItem($key, $before)` | avec la partie déplacée juste avant une autre, ou après toutes les autres quand `$before` est null |
| `withSceneStatus()`, `withSecretRevealed()`, `withClueFound()`, `withItemChecked()` | avec ce fait |
| `withFactsOf($other)` | avec chaque fait tel qu'il est pour la partie de sa clé dans l'autre, et le reste à son départ ; null les remet tous au départ |

L'ordre d'une liste est celui que lui donne le maître de jeu : il n'y a aucun numéro pour trier. Une clé qu'aucune
partie n'a lève `InvalidPreparation`, tout comme une liste rendue trop longue.

`scene()`, `secret()`, `character()`, `place()`, `note()` et `item()` donnent une partie par sa clé, ou null.
`unrevealedSecrets()` donne les secrets pas encore révélés, et `missingItems()` les clés des éléments exigés pas encore
cochés.

## Les gabarits de liste de contrôle

Un `ChecklistTemplate` contient jusqu'à 50 éléments, aucun coché. `applyTo()` les ajoute après les éléments de la
préparation, en laissant ceux dont elle contient déjà la clé.

`ChecklistTemplate::standard()` parcourt les étapes habituelles d'une préparation, chacune nommée par son utilité :

| Clé | Étape | Exigée |
|---|---|---|
| `recap` | Rappeler ce qui s'est passé à la dernière séance | |
| `characters` | Revoir les personnages joueurs et ce qu'ils cherchent | |
| `limits` | Vérifier les limites de contenu dont la table a convenu | |
| `opening` | Écrire une ouverture qui commence dans l'action | oui |
| `scenes` | Esquisser les scènes qui pourraient avoir lieu | oui |
| `secrets` | Écrire des secrets et les indices qui y mènent | oui |
| `places` | Choisir les lieux que les personnages peuvent visiter | |
| `npcs` | Choisir les personnages non joueurs et ce qu'ils veulent | |
| `threats` | Choisir les menaces et chercher leurs statistiques | |
| `rewards` | Choisir les récompenses | |
| `materials` | Rassembler les cartes, les aide-mémoire et la musique | |

Les libellés sont en anglais ; une application qui les affiche dans une autre langue les traduit par leur clé, ou
garde ses propres gabarits.
