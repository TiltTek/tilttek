<!-- phil-dev source-sha256=19498c14de656b03e86dada4181d6fff0f2544079f9e957b56902983bf457384 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/downtime` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Activity` : une activité que les règles d'un système déclarent pour le temps entre les séances — 1 à 365 jours,
  jusqu'à 8 coûts, 1 à 16 issues et une résolution —, vérifiée comme un tout, avec `totals()` sur ses jours.
- `Cost` et `CostBasis` : ce qu'une activité coûte dans une ressource, de 1 à 1 000 000, payé une fois ou pour chaque
  jour.
- `Outcome` et `Effect` : un résultat avec son libellé, son texte et jusqu'à 8 gains ou pertes dans les ressources que
  le système nomme, chaque ressource une fois ; des textes en UTF-8 valide, sans espaces aux extrémités, sans caractères
  de contrôle dans une ligne ni forçages de direction nulle part.
- `Resolution` et `Band` : une activité se termine par une issue fixe, ou par l'issue de la tranche où tombe son total ;
  1 à 16 tranches sans chevauchement ni trou, un total au-delà tombant dans la plus proche ; l'expression de dés gardée
  telle que le système l'écrit, bornée dans sa forme, pour que le lanceur la lise.
- `SystemRules` : les 1 à 64 activités d'un système de jeu, chaque clé une fois.
- `Entry` et `Stage` : une activité dans le journal d'un personnage, gardée entière telle qu'elle a été déclarée, avec
  les jours passés, le jet et l'issue, de la file d'attente à l'attente de son jet puis à la résolution, jusqu'à la
  confirmation ou l'annulation ; des valeurs vérifiées selon l'étape, chaque changement renvoyant une nouvelle valeur.
- `Downtime` : un joueur ou le meneur de jeu choisit une activité, jusqu'à 8 ouvertes par personnage ; le meneur de jeu
  avance une campagne de 1 à 365 jours, passés sur la file de chaque personnage dans l'ordre choisi, les jours dont une
  entrée n'a pas besoin allant à la suivante, chaque changement gardé d'un coup ou aucun ; un jet noté depuis la table,
  ou lancé par un lanceur ; le meneur de jeu confirme l'issue ou en nomme une autre, et annule une entrée ouverte, un
  joueur la sienne tant qu'elle attend dans la file ; un journal lu dans l'ordre choisi, jusqu'à 1000 ; il ne garde
  aucun état entre les appels.
- `Contract\Roster`, `Contract\Roller` et `Contract\RuleBook` : qui mène une campagne et qui joue un personnage, le
  total d'une expression de dés, et les activités de chaque système ; `Rules\InMemoryRuleBook` sur les règles déclarées
  une fois.
- Les événements `ActivityChosen`, `ActivityProgressed`, `ActivityCompleted`, `ActivityResolved`, `ActivityConfirmed` et
  `ActivityCancelled`, par identifiants seulement, annoncés une fois à un `Contract\DowntimeObserver`.
- `Contract\EntryStore`, gardé à une version en entier ou pas du tout et lu dans l'ordre choisi par personnage, par
  entrées ouvertes et par entrées en file d'une campagne, et `Store\InMemoryEntryStore` pour les tests.
- Les exceptions `InvalidDowntime`, `UnknownActivity`, `DowntimeRefused`, `EntryNotFound` et `DowntimeConflict`, sous
  `DowntimeException`.
- Des bancs d'essai de la plus grande campagne avancée d'un jour, et des plus grandes règles vérifiées en entier.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
