<!-- phil-dev source-sha256=0ef5f1c19cadd71bc0b3ee36e8383de6df79d5e4dd461fc4bf9c842a5e5f9d05 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/predictions` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Predictions` : des questions sur les issues datées d'événements, posées sous la clé de l'application, la même
  question posée de nouveau renvoyée et une question posée autrement refusée ; les choix des membres, modifiables
  jusqu'à l'échéance ; rien de misé.
- `Question` : de 2 à 32 issues qui ne contiennent aucun deux-points, pour que la référence d'un membre n'en soit jamais
  une, une échéance en UTC, de 1 à 1 000 000 de points, une notation et un statut.
- `Scoring::Choice`, les points de la question pour un bon choix, et `Scoring::Probability`, une part de ceux-ci selon
  le score de Brier de la prévision ; `Brier`, en nombres entiers, arrondi à la demie supérieure.
- `Pick` : une issue, ou des pourcentages entiers dont la somme fait 100, une issue laissée de côté recevant 0 %.
- L'échéance : les prédictions fermées à ce moment, et vérifiées de nouveau par le stockage au moment d'écrire, pour
  qu'aucune ne soit écrite une fois l'issue déclarée.
- `resolve()` et `settle()` : les points de chaque prédiction accordés par `tilttek/points` sous une clé construite à
  partir de son identifiant aléatoire, pour qu'un règlement exécuté de nouveau, ou par deux processus en même temps, ne
  paie rien deux fois ; les prédictions lues en pages de leurs identifiants ; `Settlement`.
- `cancel()` : une question fermée sans aucun point, qui ne peut plus être résolue.
- La lecture : `question()`, `predictionOf()` et `predictionsOf()`, au plus 100 à la fois.
- L'effacement d'un membre : ses prédictions supprimées.
- `Contract\PredictionStore` avec `Store\InMemoryPredictionStore` ; `QuestionKey`, `PredictionId`, `Prediction` et
  `QuestionStatus`.
- Des exceptions sous une seule interface `PredictionsException` : `QuestionClosed`, `UnknownQuestion` et
  `InvalidPrediction`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
