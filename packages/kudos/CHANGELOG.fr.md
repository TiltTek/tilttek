<!-- phil-dev source-sha256=b286d4564974ee60e5214c6308a6d5e38f8e2fc7cae4e75605970b5f750e137e -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/kudos` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Kudos` : des remerciements donnés par un membre à un autre avec l'un des messages fixes de l'application, au sujet
  d'un objet ou non, enregistrés comme activité du verbe `kudos.given` de `tilttek/activity` sous une clé
  d'idempotence — celle de l'application, ou une clé faite de la personne qui donne, de celle qui reçoit, du message, de
  l'objet et du jour de la personne qui donne — ; des remerciements donnés de nouveau sous leur clé renvoyés sans refus
  ni second événement.
- `Refusal` et `KudosRefused` : des remerciements refusés à soi-même, à une personne qui les décline, au-delà du plafond
  quotidien de la personne qui donne compté à partir de minuit dans son fuseau horaire, et au-delà de la limite par
  personne qui reçoit dans une période, les remerciements retirés compris ; `refusal()` et `remainingToday()` qu'un
  écran lit avant de donner.
- `Limits` : cinq remerciements par jour et trois au même membre en sept jours par défaut, chaque nombre de 1 à 100 et
  la période d'une heure à 366 jours.
- `Message` et `Messages` : la liste fermée des messages, jamais un texte libre, chacun avec un nom, une clé de
  traduction et un texte anglais ; six messages par défaut, et ceux de l'application ajoutés, remplacés ou retirés.
- La lecture : `received()` pour la seule personne qui reçoit et `given()` pour celle qui donne, en pages de `Thanks` du
  plus ancien au plus récent, les remerciements retirés laissés de côté ; `givenCount()`, les remerciements donnés par
  période, la mesure d'un badge ; aucun total des remerciements reçus, aucun classement.
- `withdraw()` par la personne qui donne et `cancel()` par un modérateur, chacun une entrée à part dans le journal ; un
  membre effacé par le journal, la personne qui reçoit oubliée sur les remerciements qu'elle a reçus.
- `Contract\KudosPreferences`, `Contract\MemberTimeZones` et `Contract\KudosObserver`, avec `Preferences\AcceptAll` et
  `TimeZone\SiteTimeZone` ; les événements `KudosGiven` et `KudosCancelled`, avec des identifiants seulement.
- Des exceptions sous une seule interface `KudosException` : `KudosRefused`, `UnknownMessage` et `InvalidKudos`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
