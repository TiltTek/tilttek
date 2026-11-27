<!-- phil-dev source-sha256=1a0fe6d9bd79d2aa6262973a0c0ff4f096f9cc68540e114df3f54ca95d772f51 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/badges` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-14

### Modifié

- Un avis de la projection ne peut plus se perdre quand l'observateur échoue. `AwardStore` garde l'avis que chaque
  attribution doit : une attribution gagnée par l'activité est gardée avec son avis dû jusqu'à `markDelivered()`,
  énumérée par `undelivered()` ; une attribution retirée par `withdraw()` quitte les attributions détenues mais reste,
  énumérée par `withdrawn()`, jusqu'à ce que l'observateur ait été averti et que `remove()` la supprime. Un stockage
  écrit pour 0.1 implémente ces quatre méthodes ; `of()` laisse de côté les attributions retirées, et `forgetDerived()`
  ne renvoie que les attributions détenues dont l'avis a été donné.
- `BadgeProjection` donne les avis dus à un membre avant d'évaluer son activité ou son annulation, pour que la requête
  répétée après un échec, ou le prochain rattrapage, avertisse de nouveau l'observateur, et qu'une reconstruction
  annonce une attribution dont le premier avis n'a jamais été donné. L'observateur est averti au moins une fois, et doit
  être idempotent.
- `AwardStore::withdraw()` est une seule étape atomique qui laisse toujours dû l'avis du retrait, et renvoie true, pour
  une attribution que le membre détient, même quand l'avis propre de l'attribution est encore dû : cet avis peut être en
  cours dans un autre processus. `markDelivered()` laisse dû l'avis d'un retrait. L'observateur peut donc être averti du
  retrait d'une attribution dont il n'a jamais été averti.
- `Badges::award()` ne renvoie plus une attribution que le stockage n'a pas gardée. Quand une attribution faite au même
  moment a pris le numéro, un badge qui n'est pas répétable renvoie l'attribution gardée, et un badge répétable prend le
  numéro suivant, au plus `Badges::AWARD_ATTEMPTS` fois.
- `Badges::share()` partage l'attribution du numéro le plus élevé, plutôt que celle dont l'activité est datée en
  dernier.

### Ajouté

- `BadgeProjection::deliver()` : chaque avis dû à un membre donné à l'observateur, pour reprendre après un échec.
- `AwardConflict`, levée quand d'autres attributions ont pris, à chaque tentative, le numéro d'un badge répétable
  attribué à la main.
- La remise des avis documentée : remise au moins une fois, observateurs idempotents et reprise.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Badge` et `Tier` : le code, le nom et la description d'un badge — textes ou clés de traduction —, son image, son
  critère, et s'il est répétable, caché ou révocable ; la série et le rang qui lient les badges d'un palier.
- `Criterion`, `Leaf`, `Reading` et `Scope` : un critère comme un arbre dont les feuilles lisent l'activité par chemin ;
  les verbes par motif, les conditions de contexte et la période des activités que lit une feuille.
- `Count`, `Total`, `Streak`, `Measure`, `Manual` et `AtLeast` : un nombre d'activités et de premières fois, un total de
  leurs quantités, des jours d'affilée dans le fuseau horaire du catalogue, une valeur que l'application mesure par
  `Measures`, une attribution à la main, et une combinaison de tous les critères ou de certains.
- `CriterionReader` et `Catalog` : des critères et des badges à partir de valeurs simples, comme les tient une
  configuration, chaque code et chaque rang d'une série une seule fois, des chemins d'au plus 64 octets, les motifs de
  verbes que lisent les badges et les séries par rang.
- `Progress` : où en est un membre vers un badge, la valeur montrée au plus égale à la cible, et le nombre de fois où la
  cible est atteinte, une fois au plus à moins que le badge soit répétable.
- `Contribution`, `Award`, `Standing`, `Profile`, `Showcase` et `Erasure` : ce qu'une activité apporte à un critère, un
  badge détenu avec son numéro, son moment, sa cause et s'il a été attribué à la main, où en est un membre avec un
  badge, ce qu'il montre, ce que tout le monde peut voir, et ce que son effacement a retiré.
- `ProgressStore`, `AwardStore`, `ProfileStore` et `GrantStore`, avec leurs implémentations en mémoire : des
  contributions écrites une seule fois par badge, chemin et activité et retirées avec leur activité, des attributions
  une seule fois par numéro, des profils retirés quand ils sont vides, des consentements donnés et retirés ; ce
  qu'oublie une reconstruction, et l'effacement d'un membre. Les implémentations en mémoire se vident avec `reset()`,
  pour un processus de longue durée.
- `Evaluator` : la progression d'un membre lue à partir des contributions gardées et des mesures de l'application.
- `BadgeProjection` et `AwardObserver` : une projection de `tilttek/activity` qui apporte chaque activité aux feuilles
  qui l'acceptent, attribue un badge quand sa cible est atteinte et de nouveau pour un badge répétable, au plus
  `MAX_AWARDS_PER_ACTIVITY` attributions (10, ou la borne donnée) pour une activité, retire un badge révocable quand son
  activité est annulée, laisse tel quel un badge détenu à la main, et oublie les contributions et les attributions
  dérivées d'une période avant une reconstruction sans annoncer de nouveau les attributions gagnées de nouveau.
  `BadgeProjection::clearReplay()` oublie ce que la dernière réinitialisation a gardé pour la rejouer.
- `Badges` : les attributions, la progression et la vue d'ensemble qui laisse de côté les badges cachés non gagnés, les
  attributions à la main et leur retrait, les badges montrés, la vitrine d'une taille limitée, le titre, le partage par
  un `ShareChannel` à la demande du membre, les consentements des autres applications et leur lecture, et l'effacement
  d'un membre.
- `BadgesException`, implémentée par `InvalidBadge`, `UnknownBadge`, `NotEarned` et `AccessDenied`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
