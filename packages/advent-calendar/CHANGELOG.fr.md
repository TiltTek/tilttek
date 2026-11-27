<!-- phil-dev source-sha256=b0209198b6e24ac5d84f98c849abf7b14c6ad14c97aae38d1d14974a31f7d0e7 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/advent-calendar` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-14

### Modifié

- `Contract\OpeningStore` garde si chaque ouverture a été remise : `undelivered($member)`, les ouvertures du membre pas
  encore marquées comme remises, par calendrier puis par jour, et `markDelivered($opening)`. Une ouverture est ajoutée
  non remise. Un stockage écrit pour 0.1 implémente les deux méthodes ; `doc/writing-a-store.md` donne la colonne.
- `BoxOpened` est distribué au moins une fois : une ouverture est marquée comme remise une fois son événement distribué
  sans exception. Un écouteur qui lève une exception la laisse non remise sans arrêter les autres, et la première
  exception est levée de nouveau à la fin ; le prochain `open()` du calendrier par le membre l'annonce de nouveau. Les
  écouteurs doivent être idempotents, par `idempotencyKey()`.

### Ajouté

- `AdventCalendar::deliver()` : les ouvertures non remises d'un calendrier pour le membre annoncées de nouveau, pour
  reprendre après l'échec d'un écouteur ; une ouverture d'un jour que le calendrier ne contient plus est abandonnée.

### Corrigé

- Un écouteur de `BoxOpened` qui a échoué ne perd plus la surprise d'une ouverture déjà enregistrée.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Calendar` : une case par jour à partir d'une première date, de 1 à 92 cases, comptées dans le fuseau horaire du
  calendrier à travers les changements d'heure, qui ferme à la date de la dernière case ou jusqu'à 366 jours après.
- `Surprise` et `SurpriseKind` : la récompense d'une case sous forme de référence — des points vers un grand livre, un
  badge, un contenu, un réservoir de codes —, accordée par l'application.
- `OpeningPolicy`, `PolicyKind` et `OpeningWindow` : une case passée qui peut s'ouvrir jusqu'à la fermeture du
  calendrier par défaut, le jour même seulement, ou pour un rattrapage de 1 à 31 jours ; jamais avant son jour.
- `AdventCalendar` : des ouvertures idempotentes enregistrées une seule fois, même quand deux requêtes font la course ;
  les cases telles qu'un membre les voit — verrouillées, à ouvrir, ouvertes, manquées — sans révéler une surprise non
  ouverte ; l'effacement d'un membre.
- `Event\BoxOpened` : distribué une seule fois par première ouverture, avec une clé d'idempotence, pour que
  l'application accorde la surprise et lance une célébration.
- `Contract\OpeningStore` et `Store\InMemoryOpeningStore`, avec `OpeningStoreTestCase`, le contrat que passe tout
  stockage.
- Des exceptions sous `Exception\AdventCalendarException` : `InvalidCalendar` et `BoxNotOpenable`.
- `tests/Benchmark/BoxesBench.php` : les cases qu'une page lit, sur des calendriers de 24 et de 92 cases.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
