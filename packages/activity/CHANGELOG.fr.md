<!-- phil-dev source-sha256=aaa424b4359bc845cb1efc7100eb91a1e8e5b758816a0780575cda3ca306a7a9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/activity` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Documentation seulement.

### Modifié

- La documentation : la page sur l'enregistrement se termine par un saut de ligne, et les lignes de la page française
  sur les projections tiennent en 120 colonnes.

## 0.1.1-alpha - 2026-11-14

### Modifié

- `Journal` remet l'entrée qu'il a trouvée, quand le même geste est enregistré ou la même annulation donnée de nouveau,
  à ses observateurs qui implémentent `ResumableObserver` : une requête répétée après l'échec d'un observateur rattrape
  l'échec.
- `LiveProjections` implémente `ResumableObserver` : une entrée remise de nouveau fait rattraper les projections encore
  en retard sur elle, si bien qu'une projection qui a échoué en mode `live` ne reste plus en retard jusqu'à l'entrée
  suivante.

### Ajouté

- `Contract\ResumableObserver` : un observateur qui garde un point de reprise, à qui `resume()` remet de nouveau une
  entrée enregistrée ou annulée de nouveau sous sa clé, qu'il reprend s'il ne l'a pas traitée.
- `Projector::behind()` : si le point de reprise d'une projection est avant une position.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Activity` : un membre et un objet comme références `phil/resource`, un verbe, un moment, une quantité de 1 à un
  milliard et un contexte ; `of()` à partir de valeurs simples, `sameGestureAs()` qui laisse le moment de côté,
  `withoutObject()` et `toArray()`.
- `Verb`, `VerbPattern` et `Verbs` : des verbes nommés par l'application en segments minuscules séparés par des points,
  des motifs pour un verbe, un préfixe (`game.*`) ou tous les verbes (`*`), et les verbes déclarés qui refusent les
  autres.
- `IdempotencyKey` et `Context` : la clé qui fait qu'un geste compte une seule fois, sans espaces ni caractères de
  contrôle, et quelques faits nommés sur un geste — entiers, booléens et chaînes courtes, jamais le texte d'un membre —,
  triés.
- `Entry` avec `EntryKind`, `Appended`, `Totals` et `Erasure` : les lignes du journal à des positions qui ne font que
  croître, enregistrées ou annulées, et ce qu'ont renvoyé l'enregistrement, le comptage et l'effacement.
- `Filter` : des activités par membre, verbes, objet et période, `since` compris et `until` exclu.
- `ActivityLog` et `InMemoryActivityLog` : ajouter une seule fois par clé et refuser la clé pour un autre geste, annuler
  une seule fois, trouver, lire après une position dans l'ordre, la position de la dernière entrée gardée, des totaux
  sans les activités annulées, et l'effacement d'un membre qui garde les gestes des autres avec leur objet oublié ;
  `Reading` vérifie les bornes d'une lecture. `InMemoryActivityLog::reset()` vide le journal, pour un processus de
  longue durée.
- `Journal` : enregistrer, annuler, trouver, lire, compter et effacer, des entrées horodatées par une horloge PSR-20,
  les verbes déclarés vérifiés avant l'enregistrement, un geste daté dans une fenêtre autour de l'horloge — trente jours
  avant et cinq minutes après par défaut, le passé sans borne sur demande —, et des `JournalObserver` avertis de chaque
  nouvelle entrée.
- `Projection`, `Checkpoints`, `InMemoryCheckpoints`, `Projections` et `Projector` : un état dérivé à qui sont remises
  par lots les entrées qu'il n'a pas vues, son point de reprise avancé après chaque lot et au-delà de ce que son filtre
  laisse de côté, une reconstruction de tout le journal ou d'une période à partir du plus tardif de deux débuts, le
  retard d'une projection, et des noms vérifiés et uniques. `InMemoryCheckpoints::reset()` ramène les points de reprise
  à zéro.
- `LiveProjections` : un observateur qui fait rattraper les projections qui lisent chaque nouvelle entrée.
- `ActivityException`, implémentée par `InvalidActivity`, `IdempotencyConflict`, `UnknownVerb`, `UnknownProjection` et
  `InvalidProjection`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
