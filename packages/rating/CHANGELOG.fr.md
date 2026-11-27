<!-- phil-dev source-sha256=71629cd2111ae581f36c705e29d0490b71a2a8994be997bbf76dafb4233d7631 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/rating` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Corrigé

- `GameResult::fromArray()` refuse un jour ou une heure qui n'existe pas, `2026-02-30T20:00:00Z` ou `24:00:00`, avec
  `InvalidRating` : il était reporté au mois ou au jour suivant.

## 0.1.0-alpha - 2026-11-14

### Ajouté

- `Ratings` : des résultats cotés dans un seul système par jeu et gardés pour tous leurs membres d'un coup, chaque
  résultat compté une seule fois avec `Exception\ResultAlreadyRecorded` pour un second enregistrement, des résultats
  amicaux qui ne changent rien, des cotes provisoires selon le nombre de parties jouées — 10 par défaut — et selon
  l'écart d'une compétence OpenSkill, des classements qui peuvent les laisser de côté, la cote d'un membre, des
  classements des seules cotes publiques, la publication et le masquage au choix du membre, une relecture d'un jeu à
  partir de sa source de résultats qui garde le choix de chaque membre, et l'exportation et l'effacement d'un membre.
- `GameResult` : un résultat de 2 à 64 camps de 1 à 32 membres, 256 au plus, avec un rang par camp et des égalités ; des
  duels et des classements ; écrit et relu en valeurs simples.
- `Elo\EloSystem`, `Elo\EloRating`, `Elo\KFactor` et `Elo\KFactorStep` : Elo en centièmes de point, un K fixe ou qui
  diminue selon les parties jouées et la cote, le K de la fédération d'échecs, des parties à plusieurs joueurs et en
  équipes lues par paires de camps avec le propre K de chaque membre, l'échelle et un plancher facultatif.
- `OpenSkill\OpenSkillSystem`, `OpenSkill\SkillRating` et `OpenSkill\OpenSkillModel` : l'approximation bayésienne de
  Weng et Lin, avec les modèles de Plackett-Luce et de Thurstone-Mosteller, la croissance de l'incertitude avant chaque
  partie, la plus petite part d'une variance gardée, la marge d'égalité, mu et sigma en millionièmes, des classements
  ordonnés selon l'estimation prudente, et un écart facultatif au-dessus duquel une compétence est provisoire.
- `MemberRating`, `Visibility` et `RatingChange` : la cote d'un membre telle qu'enregistrée — le système, le pointage en
  centièmes, les parties, le pointage le plus haut, la visibilité, privée par défaut, le dernier résultat et la version
  —, écrite et relue en valeurs simples.
- `Contract\Rating`, `Contract\RatingSystem`, `Contract\RatingStore` — qui garde les résultats que compte chaque jeu à
  côté des cotes, avec les événements de chaque résultat pas encore remis — et `Contract\PlaySource`, avec
  `Store\InMemoryRatingStore`.
- `Event\RatingChanged` et `Event\PeakReached`, qui ne portent que des identifiants et une `key()` stable, pour une
  célébration une fois qu'un membre a joué les parties cotées demandées ; gardés avec le résultat jusqu'à ce que chacun
  ait été distribué sans exception, les événements restants distribués en enregistrant de nouveau le résultat — ce qui
  ne recote rien — ou par `Ratings::deliver()`, un écouteur qui échoue n'arrêtant jamais les autres. Une relecture
  annule les événements du jeu pas encore remis, qu'elle pourrait ne plus donner : `RatingStore::replace()` les vide
  avec les cotes.
- `Exception\RatingException`, avec `InvalidRating`, `RatingRefused`, `ResultAlreadyRecorded` et `RatingConflict`.
- Une cible de fuzzing pour le calcul d'une cote, et des bancs d'essai d'une relecture de 10 000 résultats par chaque
  système.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
