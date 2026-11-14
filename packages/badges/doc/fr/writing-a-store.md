<!-- phil-dev source-sha256=787d1c0ec4f7848759cff344818de7c2251cae54fb3e93f350f9980cc5e708fa -->
# Écrire un stockage

Une application garde les badges sur son propre stockage en implémentant les quatre stockages. Chaque contrat est le
comportement que vérifient les tests de la bibliothèque sur les implémentations en mémoire, et que
`tilttek/badges-bundle` vérifie sur Doctrine DBAL.

## `ProgressStore`

- `add()` garde une contribution une seule fois par badge, chemin et activité, et renvoie faux pour une contribution
  déjà présente — même quand deux processus l'écrivent au même moment.
- `remove($activity)` supprime chaque contribution de l'activité et les renvoie, sous forme de liste.
- `count()`, `quantity()` et `moments()` lisent les contributions d'un membre à un chemin d'un badge.
- `forget($since)` supprime les contributions des activités à partir de `since`, toutes quand il est null.
- `eraseMember()` supprime les contributions du membre.

## `AwardStore`

- `add()` garde une attribution une seule fois par badge, membre et numéro, et renvoie faux pour une attribution déjà
  présente, détenue ou retirée — même quand deux processus l'écrivent au même moment. Une attribution qui n'est pas
  faite à la main est gardée avec son avis dû ; une attribution faite à la main n'en appelle aucun.
- `markDelivered()` note que l'avis de l'attribution a été donné ; `undelivered($member)` énumère les attributions du
  membre dont l'avis est dû, par badge et par numéro.
- `withdraw()` sort l'attribution de celles que détient le membre et renvoie vrai : elle attend l'avis de son retrait,
  même quand son propre avis est encore dû, car cet avis peut être en cours dans un autre processus. Le changement se
  fait en une étape atomique, une mise à jour conditionnelle par exemple, et `markDelivered()` laisse ensuite dû l'avis
  du retrait. Une attribution que le membre ne détient pas est laissée telle quelle : faux. `withdrawn($member)`
  énumère les attributions retirées dont l'avis est dû, par badge et par numéro.
- `remove()` supprime l'attribution de ce badge, de ce membre et de ce numéro, détenue ou retirée.
- `of($member, $badge)` énumère les attributions que détient le membre — d'un seul badge quand il est nommé — selon le
  moment où elles ont été gagnées, puis par badge et par numéro ; les attributions retirées sont omises.
- `forgetDerived($since)` supprime les attributions non faites à la main gagnées à partir de `since`, toutes quand il
  est null, détenues ou retirées, et renvoie celles qui sont détenues et dont l'avis a été donné.
- `eraseMember()` supprime les attributions du membre, détenues ou retirées.

## `ProfileStore`

- `get()` renvoie le profil du membre, un profil vide s'il n'y en a pas.
- `save()` garde un profil et supprime un profil vide.
- `erase()` le supprime, et indique s'il y en avait un.

## `GrantStore`

- `grant()` garde un consentement ; donné de nouveau, il reste donné.
- `withdraw()` le supprime, et indique s'il y en avait un ; `allows()` indique s'il est présent.
- `clients()` énumère les applications que le membre a autorisées, par nom.
- `eraseMember()` supprime les consentements du membre.
