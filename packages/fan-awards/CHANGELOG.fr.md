<!-- phil-dev source-sha256=3feb2265f8ea1d5d8e297d925dc49ad0d7de67fa75b6e55db86fb17e784e5824 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/fan-awards` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Edition`, `Category`, `Nominee` et `Phase` : une édition de 1 à 50 catégories, avec une demi-finale ou non ; par
  catégorie, de 1 à 10 candidats sur un bulletin de mise en nomination, de 2 à 20 demi-finalistes, 2 finalistes jusqu'au
  nombre de demi-finalistes, une méthode finale par classement de `phil/poll` et « aucun prix » ou non ; les candidats
  identifiés par une clé pour les bulletins, `no-award` réservé.
- `FanAwards` : des éditions créées à l'état de brouillon, des candidats proposés avant et pendant les mises en
  nomination, des bulletins de mise en nomination — de 1 jusqu'au nombre de la catégorie — et de demi-finale — un seul —
  avec des candidats inscrits à la main, et des bulletins finaux par classement ; un seul bulletin par votant, catégorie
  et phase, remplacé jusqu'à la fin de la phase ; les premiers candidats d'une catégorie ouverte inscrits un par un.
- L'admissibilité : `Contract\NomineeEligibility`, interrogé pour chaque candidat proposé ou inscrit, sa raison donnée
  au votant, et `Contract\VoterEligibility`, jamais un paiement ni un achat, avec `Eligibility\AnyNominee` et
  `Eligibility\AnyVoter` ; au plus 100 candidats par catégorie et par phase.
- `advance()` : chaque phase de vote comptée par `phil/poll` — le compte des choix pour les mises en nomination et la
  demi-finale, la méthode par classement de la catégorie pour la finale —, les listes courtes dans leur taille avec les
  égalités à la limite comprises et les candidats sans voix laissés de côté, les phases de moins de deux options passées
  sans vote.
- `PhaseCount` et `Results` : les gagnants, plusieurs en cas d'égalité, aucun quand « aucun prix » tient seul la
  première place ou que personne n'a voté ; `preview()` pour les organisateurs une fois le compte fait, `results()` pour
  tout le monde une fois les résultats annoncés, avec le compte détaillé de chaque phase.
- `erase()` : un votant oublié sur ses bulletins, qui restent, anonymes, dans les comptes.
- `Contract\AwardStore`, avec `Store\InMemoryAwardStore`.
- Des exceptions sous une seule interface `FanAwardsException` : `AwardRefused` avec son `Refusal`, `UnknownAward` et
  `InvalidAward`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
