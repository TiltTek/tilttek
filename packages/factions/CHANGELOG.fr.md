<!-- phil-dev source-sha256=314e6089fb8be1930cd1f8c2fcce4d952a559086e4136890a1a50fdb2deba485 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/factions` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Roster` : les factions d'une campagne, tenues par son meneur de jeu — son échelle, jusqu'à 64 factions, chaque clé
  une fois, et jusqu'à 512 relations, chaque paire une fois, entre des factions de la liste —, vérifiées comme un tout,
  chaque changement renvoyant une nouvelle valeur.
- `Scale` et `Tier` : des réputations en nombres entiers entre -100 et 100, un point de départ pour un sujet qu'aucun
  changement n'a touché, et 1 à 12 paliers nommés qui découpent l'échelle à partir du bas ; `Scale::standard()` va de -3
  à 3 ; `tier()`, `contains()` et `clamp()`.
- `Faction`, `Rank`, `Membership` et `Standing` : une faction trouvée par sa clé, son nom et son résumé, jusqu'à 16
  rangs du plus bas au plus haut, jusqu'à 64 réputations, chaque sujet une fois, jusqu'à 64 membres qui détiennent
  chacun un rang de la faction, et si elle est secrète ; des textes en UTF-8 valide, sans espaces aux extrémités, sans
  caractères de contrôle dans une ligne ni forçages de direction nulle part ; `Faction::outranks()`.
- `Subject` : le groupe ou un personnage, de clé `party` ou `character:` suivi de l'identifiant, relu par
  `Subject::fromKey()`.
- `Relation` et `Stance` : la position de deux factions l'une envers l'autre, la même dans les deux sens, gardée avec la
  clé la plus basse en premier, avec une note et si elle est secrète ; une faction n'a pas de relation avec elle-même.
- `Factions` : créer une liste avec le point où la campagne commence ; ajouter une faction sans réputations ni membres,
  changer ses détails en les gardant, la retirer avec ses relations ; changer l'échelle quand chaque réputation y tient
  ; déplacer une réputation de 1 à 200 crans, en s'arrêtant aux extrémités, ou la fixer sur l'échelle ; donner, changer
  ou retirer un rang ; fixer ou terminer une relation ; transmettre ; supprimer ; un changement qui laisse la liste
  telle qu'elle était ne garde et n'annonce rien ; il ne garde aucun état entre les appels.
- `Reason` et l'historique : chaque mouvement d'une réputation, d'un rang ou d'une relation prend les mots du meneur de
  jeu et une séance facultative, et garde un `History\StandingEntry`, un `History\RankEntry` ou un
  `History\RelationEntry` avec la valeur avant et après, le membre et le moment donné par un `Psr\Clock\ClockInterface`,
  dans la même écriture que la liste ; `Factions::history()` lit ceux d'une faction, du plus récent au plus ancien,
  jusqu'à 500.
- `View\RosterView`, `View\FactionView` et `View\StandingView` : la vue du meneur de jeu, avec chaque réputation, membre
  et secret ; la vue du joueur, sans les factions ni les relations secrètes, avec la réputation du groupe et seulement
  sa propre réputation et son propre rang ; chaque réputation avec son palier, et `toArray()` pour une page.
- Les événements `RosterCreated`, `FactionAdded`, `FactionChanged`, `FactionRemoved`, `ScaleChanged`, `StandingChanged`,
  `RankChanged`, `RelationChanged`, `RosterHandedOver` et `RosterDeleted`, par identifiants seulement, annoncés une fois
  à un `Contract\RosterObserver`.
- `Contract\RosterStore`, gardé à une version avec son historique en une seule écriture, supprimé avec lui et lu par
  faction, et `Store\InMemoryRosterStore` pour les tests.
- Les exceptions `InvalidRoster`, `RosterNotFound`, `RosterRefused` et `RosterConflict`, sous `FactionsException`.
- Des bancs d'essai de la plus grande liste : vue par le meneur de jeu et par un joueur, une réputation déplacée.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
