<!-- phil-dev source-sha256=83be208eb0b85c2b591b79c1a5e0a45fd071084bed789ec006b7da697038e7e8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Défaut |
|---|---|---|
| `Contract\CelebrationStore` | L'endroit où les boîtes et les préférences sont gardées | `Store\InMemoryCelebrationStore` dans les tests |
| `Contract\CelebrationObserver` | Ce qu'il faut faire d'un changement de boîte | Aucun |
| `Limits` | Ses limites sur les entrées en attente, les entrées par membre et les entrées montrées par groupe | 200, 500 et 10 |
| `Psr\Clock\ClockInterface` | Le moment de chaque acte, et de la lecture de ce qui est devenu caduc | — |

## Écrire un dépôt de célébrations

Un `CelebrationStore` désigne les membres par leurs références écrites `genre:identifiant`, et une entrée par son
membre, sa source et son occurrence, uniques ensemble. Il garde :

- les entrées — `find()`, `save()`, qui remplace l'entrée des mêmes membre, source et occurrence, `remove()`,
  `countEntries()`, `entries()`, par séquence, la première reçue en premier, toutes ou celles d'un `EntryState`, et
  `latest()`, une page, la dernière reçue en premier, avant une séquence quand on en donne une ;
- les séquences — `nextSequence()`, une de plus que toute séquence donnée au membre, jamais réutilisée, même après
  `remove()` ou `forget()` : une colonne de la ligne du membre, ou une séquence de la base de données ;
- les préférences — `preferences()`, aucune quand le membre n'en a pas choisi, et `savePreferences()` ;
- `forget()`, qui retire les entrées et les préférences du membre.

La bibliothèque vérifie qu'une entrée est nouvelle et respecte les limites de la boîte avant d'écrire : une application
qui sert des requêtes concurrentes exécute chaque acte de `Celebrations` dans une transaction, ou sous un verrou sur le
membre, et garde un index unique sur le membre, la source et l'occurrence, afin qu'une réussite signalée deux fois en
même temps soit gardée une seule fois. Un dépôt relationnel indexe le membre avec l'état et la séquence, que chaque
lecture filtre et ordonne.

## Observateurs

Un `CelebrationObserver` est informé d'un `CelebrationChanged` après chaque acte, une fois que le dépôt a écrit.
L'événement ne contient que des identifiants — le membre, la source et l'occurrence de l'entrée — avec le `Change` et le
moment ; les paramètres et les préférences n'y sont jamais. Un observateur relaie l'événement — le compte poussé aux
pages ouvertes du membre, un message sur un bus — et ne garde aucun état entre deux appels.

| `Change` | Source et occurrence |
|---|---|
| `Received` | L'entrée reçue, en attente ou déjà caduque |
| `Seen` | L'entrée marquée comme vue |
| `Withdrawn` | L'entrée retirée |
| `Lapsed` | L'entrée devenue caduque, son moment passé ou repoussée par une plus récente |
| `PreferencesChanged`, `Forgotten` | — |

Une réussite signalée de nouveau n'est pas signalée : seule une nouvelle entrée l'est.
