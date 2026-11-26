<!-- phil-dev source-sha256=98c0d039da1fe5e639ad8932a70bf4b5a10db211445756701bc1537a0d4b24d9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les préparations | Implémentez `Contract\PreparationStore`, et passez-le à `SessionPreparer`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\PreparationObserver`, et passez-le à `SessionPreparer`. |
| Donner ses étapes à une préparation | Construisez un `ChecklistTemplate` de vos éléments, et appliquez-le à une préparation. |

## Le dépôt

`Contract\PreparationStore` garde les préparations, chacune à une version :

- `preparation()` en donne une par son identifiant, ou null ;
- `save()` en garde une à la version suivante et la retourne. Une préparation à la version 0 est nouvelle, et le dépôt
  n'a aucune préparation de son identifiant ; toute autre n'est gardée que si le dépôt la détient à cette version, et
  `PreparationConflict` est levée sinon, rien n'étant gardé ;
- `delete()` en oublie une quand le dépôt la détient à sa version, et lève `PreparationConflict` sinon, rien n'étant
  oublié ;
- `preparations()` donne les préparations qu'une `PreparationQuery` retient, au plus la limite, par identifiant.
  `PreparationQuery::matches()` énonce ce que veut dire chaque requête ; un dépôt la lit à partir de `campaign`,
  `gameMaster`, `session` et `status`, par un index sur chacun.

`Store\InMemoryPreparationStore` les garde en mémoire, tant que l'objet vit : il sert aux tests. Une application les
garde dans sa base de données : une préparation contient des chaînes, un entier, une énumération et ses listes de
parties, qui contiennent des chaînes, des booléens, des énumérations et des listes de clés, et tiennent chacune dans
une colonne JSON, ou dans des lignes à part gardées dans leur ordre.

## L'observateur et ses événements

`Contract\PreparationObserver::notify()` est informé de chaque fait une fois la préparation gardée, par identifiants
seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\PreparationCreated` | Une préparation a été créée. | |
| `Event\PreparationChanged` | Le maître de jeu a changé ses parties. | |
| `Event\SessionLinked` | Elle a été liée à une séance, ou à aucune. | la séance, ou null |
| `Event\SceneMarked` | Une scène a été jouée, sautée, ou de nouveau planifiée. | la scène, son statut |
| `Event\SecretMarked` | Un secret a été révélé, ou de nouveau caché. | le secret, s'il est révélé |
| `Event\ClueMarked` | Un indice a été trouvé, ou non. | le secret, l'indice, s'il est trouvé |
| `Event\ItemMarked` | Un élément de la liste de contrôle a été coché, ou non. | l'élément, s'il est coché |
| `Event\PreparationReady` | Elle est prête pour la table. | |
| `Event\PreparationReopened` | Une préparation prête ou fermée est de nouveau un brouillon. | |
| `Event\PreparationClosed` | Elle a été fermée, la séance étant terminée. | |
| `Event\PreparationCarriedOver` | Ce qu'une autre a laissé a été reporté dedans. | la préparation d'où l'on reporte |
| `Event\PreparationDeleted` | Elle a été supprimée. | |

Chaque événement implémente `Event\PreparationEvent`, dont `preparation` est un identifiant. Une application envoie un
message à partir d'eux — par Messenger, avec les identifiants seuls — et lit le reste dans le dépôt : sur
`SecretMarked`, ce que les joueurs ont appris est consigné dans leur connaissance de la campagne ; sur
`PreparationClosed`, le journal de la séance est commencé à partir des scènes jouées. Le texte d'un secret ne quitte
jamais le dépôt dans un message.

## Les gabarits

Un `ChecklistTemplate` est une valeur : une application garde les gabarits de ses maîtres de jeu comme elle garde leurs
préparations, sous forme de listes d'éléments, et construit le gabarit quand une préparation est créée.
`ChecklistTemplate::standard()` est un gabarit général, dont l'application traduit les libellés par leurs clés.
