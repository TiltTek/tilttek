<!-- phil-dev source-sha256=9502cecab7bf9969c48ead2e7e3c62f9681b9ef9af0c0235cd414abe7224558b -->
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
  présente.
- `remove()` supprime l'attribution de ce badge, de ce membre et de ce numéro.
- `of($member, $badge)` énumère les attributions du membre — d'un seul badge quand il est nommé — selon le moment où
  elles ont été gagnées, puis par badge et par numéro.
- `forgetDerived($since)` supprime et renvoie les attributions non faites à la main gagnées à partir de `since`, toutes
  quand il est null.
- `eraseMember()` supprime les attributions du membre.

## `ProfileStore`

- `get()` renvoie le profil du membre, un profil vide s'il n'y en a pas.
- `save()` garde un profil et supprime un profil vide.
- `erase()` le supprime, et indique s'il y en avait un.

## `GrantStore`

- `grant()` garde un consentement ; donné de nouveau, il reste donné.
- `withdraw()` le supprime, et indique s'il y en avait un ; `allows()` indique s'il est présent.
- `clients()` énumère les applications que le membre a autorisées, par nom.
- `eraseMember()` supprime les consentements du membre.
