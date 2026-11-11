<!-- phil-dev source-sha256=80b7424c2a75c099c3d19d583f1fdb9a8fd0bba88200dd60d4e988650b3e03fd -->
# Les exemplaires et l'album

## Un exemplaire par clé

Un membre possède des exemplaires d'objets : deux exemplaires d'un objet font un double.
`Collections::grant(member, item, source, key)` en remet un, sous une clé tirée de ce qui le rend unique —
`order:2026-0193:line:2`, `quest:first-steps:member:44` — : la même clé donnée de nouveau renvoie l'exemplaire qu'elle
a remis. Une tâche exécutée deux fois ou un message livré deux fois remet ainsi un seul exemplaire. L'identifiant de
l'exemplaire, 32 chiffres hexadécimaux, découle de la clé ; hors de la bibliothèque, un exemplaire est connu sous le nom
`collection-copy:<identifier>`. Une clé qui a remis un exemplaire d'un autre objet, ou à un autre membre qui l'a
encore, est refusée.

Une clé d'application fait de 1 à 200 octets, sans espace blanc, caractère de contrôle ni `#`. Les clés que dérive la
bibliothèque portent `#` : `<key>#<item>` pour un ensemble remis en entier, `<key>#<set>#<item>` à l'arrivée,
`<key>#<place>` pour un paquet, et `#daily/…` pour l'objet du jour.

## Sources

Chaque exemplaire se souvient de la façon dont il est parvenu à son premier membre : `welcome`, `daily`, `pack`,
`reward`, `exchange`, `purchase`, `gift` ou `granted`. Un échange change son propriétaire, jamais sa source, et le
marque comme ayant changé de mains.

## Exemplaires liés

Un exemplaire remis avec `tradable: false` reste avec son membre — un objet gagné par une quête, par exemple. Les
exemplaires d'un objet que le catalogue marque comme non échangeable sont toujours liés.

## Ensembles remis en entier, ensembles de base

`grantSet(member, set, source, key)` remet chaque objet d'un ensemble — un ensemble gagné une fois que
`tilttek/exchange` confirme la réservation, un ensemble payé une fois que le commerce du site confirme l'achat ou le
cadeau. `welcome(member, key)` remet chaque ensemble `base` à un membre qui arrive sur le site. Un achat ne convertit
jamais des points en argent : la vente appartient au site.

## L'album

`album(member)` renvoie un `Album` : un `Holding` par objet du catalogue — les exemplaires que possède le membre,
aucun pour un objet encore à trouver, et ses doubles — et une `SetProgress` par ensemble : les objets possédés et
manquants, `total()` — *12 sur 20* —, `isComplete()`, et si le membre l'a déjà complété une fois. `owned()` et
`doubles()` filtrent les détentions.

## Ensembles complétés

Quand un exemplaire remis ou déplacé vers un membre complète un ensemble, l'ensemble est inscrit comme complété — une
seule fois par ensemble et par membre — et le `CollectionObserver` est averti par `setCompleted(SetCompleted)`, avec
l'ensemble et ses récompenses, pour que l'application les remette par les paquets dont elle dispose. Échanger un objet
puis le récupérer n'annonce rien de plus. L'observateur est aussi averti de chaque exemplaire remis.

## Déplacements

`move(list<CopyMove>)` déplace des exemplaires, chacun du membre qui le donne vers le membre qui le reçoit, tous ou
aucun : chaque exemplaire est vérifié avant tout déplacement, et chaque déplacement nomme le propriétaire qu'il attend.
Un exemplaire échangé entre-temps fait ainsi refuser tout le déplacement. Exécutez-le dans une transaction du stockage
quand deux processus peuvent déplacer les mêmes exemplaires. `ownerOf(copy)` et `isTradable(copy)` répondent à ce que
demande un échange ; `tilttek/collections-bundle` en fait l'inventaire de `tilttek/exchange`.

## Exemplaires repris

`takeBack(member, key)` supprime l'exemplaire qu'une clé a remis — un achat remboursé — s'il est encore avec son
membre et n'a jamais changé de mains.
