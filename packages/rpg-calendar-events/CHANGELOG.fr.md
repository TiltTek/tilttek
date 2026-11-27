<!-- phil-dev source-sha256=58318c637329088a7a99aeafc855575f56e8aeaa853cd7a60d96765c9eec7883 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/rpg-calendar-events` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Entry` et `Status` : un événement du calendrier du jeu de rôle avec un identifiant stable, des noms en anglais, en
  français et dans jusqu'à 32 locales, une règle de `phil/almanac`, une source https lue par `phil/url` et gardée sous
  sa forme canonique, une communauté, le jour de sa dernière vérification, jusqu'à 16 étiquettes, de 1 à 366 jours, s'il
  est vérifié ou encore à vérifier, et s'il est une période thématique de 2 jours ou plus avec une invite par jour ; son
  nom dans la locale la plus proche et sa définition d'almanach.
- `EntryReader` : des événements lus à partir de tableaux, la forme des données fournies et des propres événements d'une
  application, tout champ inconnu refusé.
- `Catalog`, `Filter` et `Occurrence` : les événements dans un seul fuseau horaire, les dates d'une année, d'une période
  ou d'un jour, filtrées par étiquette, communauté ou vérification, chacune avec son entrée et sa source ; les propres
  événements d'une application ajoutés à côté de ceux qui sont fournis, un identifiant en double refusé ; les années
  1000 à 3000.
- `PromptDay` et `Catalog::promptDays()` : le jour d'une période thématique d'invites quotidiennes, avec son indice à
  partir de 1 en jours civils et sa longueur, pour la propre invite de l'application à cet indice.
- `ShippedData`, `data/events.php` et `data/VERSION` : le GM's Day, le RPGaDAY et le National Game Design Month, chacun
  vérifié contre sa source ; aucune convention et aucune liste d'invites.
- `DataCheck` : chaque problème d'un ensemble de lignes, une règle qui ne se lit pas, une source ou une date de
  vérification manquante, un identifiant en double.
- Des exceptions sous une seule interface `RpgCalendarEventsException` : `InvalidEntry` et `InvalidQuery`.
- Des bancs d'essai de la construction du catalogue fourni et des dates d'un jour et d'une année.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
