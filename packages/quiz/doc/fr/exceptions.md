<!-- phil-dev source-sha256=fb53ec46c6c7d635922e3cbb1d7337bf0eda9597f0a0015bd2f69f56bf9bb53d -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\QuizException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidQuiz` | `InvalidArgumentException` | Une valeur sort de ses bornes : un identifiant, un énoncé, des points, une limite de temps, des choix, une réponse d'une mauvaise sorte, un pseudonyme, un NIP, un bonus de vitesse, les équipes d'une session, une liste de mots ou un préfixe de sujet |
| `QuizRefused` | `DomainException` | Les règles refusent un geste maintenant : une réponse après le temps ou donnée deux fois, un invité à son rythme, la partie d'un autre membre, des résultats avant la fin d'une partie, une reprise refusée, une session dans le mauvais état, une équipe que la session n'a pas, un jeu-questionnaire, une partie ou une session inconnus, aucun NIP libre |
| `QuizConflict` | `RuntimeException` | Une partie ou une session a changé dans le dépôt depuis sa lecture, son identifiant est pris, ou une autre partie du membre a pris sa tentative |

Les messages nomment des identifiants et des bornes, jamais un pseudonyme ni une réponse saisie.
