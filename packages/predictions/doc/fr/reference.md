<!-- phil-dev source-sha256=03d09acf7838fadfd9d8f4209d47ead98d79f71183aa01b6d5d30a6705834546 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Predictions` | Pose des questions, enregistre les choix jusqu'à l'échéance, résout, règle et annule, lit, efface un membre |
| `Question` | Une question sur un résultat daté : ses résultats, son échéance, ses points, sa notation et son statut |
| `QuestionKey` | La clé qu'une application donne à une question |
| `QuestionStatus` | Ouverte, résolue ou annulée |
| `Scoring` | Par choix, ou par probabilité avec le score de Brier |
| `Pick` | Un résultat, ou une probabilité pour chaque résultat |
| `Prediction` | Le choix d'un membre sur une question, avec son identifiant aléatoire |
| `PredictionId` | L'identifiant aléatoire d'une prédiction, que porte la clé de ses points |
| `Brier` | Le score de Brier en dix-millièmes, et les points qu'il rapporte |
| `Settlement` | Ce qu'a fait le règlement : prédictions lues, récompensées, points, mouvements écrits |
| `Contract\PredictionStore` | L'endroit où les questions et les prédictions sont conservées |
| `Store\InMemoryPredictionStore` | Le magasin en mémoire, pour les tests |
| `Exception\PredictionsException` et ses trois exceptions | [Exceptions](exceptions.md) |
