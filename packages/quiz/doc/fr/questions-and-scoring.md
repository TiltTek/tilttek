<!-- phil-dev source-sha256=3f8c237aa390e534b4f1c719854dcedc38e9599260029b6adda1160910acc2ff -->
# Questions et score

## Les questions

Chaque question a un identifiant, unique dans son jeu-questionnaire, un énoncé de 1 à 1 000 octets, ses points, de 0 à
10 000 et 100 par défaut, et une limite de temps facultative de 5 à 600 secondes. Une question sans limite de temps
prend celle du jeu-questionnaire ; un jeu-questionnaire qui n'en a pas laisse ses questions sans minuterie.

| Question | Réponse | Bonne quand |
|---|---|---|
| `SingleChoiceQuestion` | `ChoiceAnswer` | Elle choisit seule le bon choix, parmi 2 à 12 |
| `SingleChoiceQuestion::trueOrFalse()` | `ChoiceAnswer` de `true` ou `false` | Elle choisit la vérité |
| `MultipleChoiceQuestion` | `ChoiceAnswer` | Elle choisit exactement les bons choix ; avec crédit partiel, voir plus bas |
| `OrderingQuestion` | `OrderAnswer` | Chaque élément est à sa place ; avec crédit partiel, chaque élément à sa place rapporte sa part |
| `TypedAnswerQuestion` | `TextAnswer` | Les mots, pliés, correspondent à l'une des 1 à 20 réponses acceptées |
| `NumericQuestion` | `NumberAnswer` | Le nombre entier est dans la tolérance de la réponse, bornes comprises |

Une question à choix multiples avec crédit partiel donne à chaque bon choix retenu sa part des points et reprend une
part pour chaque mauvais choix retenu, jamais sous zéro. Une réponse saisie et les réponses acceptées sont pliées avant
d'être comparées : minuscules, espaces d'extrémité retirés et suites d'espaces réduites à une seule, accents retirés
sauf si la question les garde (`keepAccents: true`). Les éléments d'une question d'ordre sont donnés dans le bon ordre ;
l'application les mélange quand elle les montre.

Une question refuse une réponse d'une autre sorte avec `InvalidQuiz`. Une application ajoute une sorte bien à elle en
implémentant `Contract\Question` ; sa note doit dépendre de la réponse seule.

## Les notes

`Grade` dit à quel point une réponse est juste, en millièmes : 1000 est juste, 0 est fausse, et le crédit partiel se
situe entre les deux. Des nombres entiers gardent chaque score exact : les points qu'une réponse rapporte sont les
points de la question multipliés par la note, divisés par 1 000 et arrondis vers le bas.

## La minuterie

`QuestionTimer` garde le moment où une question a été montrée et sa limite de temps. Elle compte les millisecondes entre
deux moments de l'horloge que donne l'application, si bien qu'un test avance le temps en avançant son horloge. Une
question minutée expire après sa dernière milliseconde : une réponse à exactement 20 secondes d'une question de
20 secondes est dans le temps, une milliseconde plus tard ne l'est pas.

À son rythme, une réponse après le temps ne rapporte rien et la partie continue ; en direct, elle est refusée, et la
fermeture de la question inscrit le joueur comme expiré.

## Justesse et vitesse

`Scoring` est la règle du jeu-questionnaire :

| Mode | Points d'une bonne réponse |
|---|---|
| `Scoring::accuracy()`, la valeur par défaut | Les points de la question |
| `Scoring::speed($bonus = 50)` | Les points de la question, plus jusqu'à `$bonus` pour cent d'entre eux, en baisse linéaire depuis le moment où la question est montrée jusqu'à rien à la fin du temps |

Le bonus va seulement à une réponse entièrement juste à une question minutée ; il va de 0 à 100 pour cent.
`Quiz::maxScore()` est le plus qu'un jeu-questionnaire puisse donner : toutes les réponses justes, et instantanées en
mode vitesse.

## Composer un jeu-questionnaire à partir d'une banque

`Quiz::fromSource()` demande à un `Contract\QuestionSource` jusqu'au nombre de questions d'une banque, et n'en garde pas
plus que demandé. `Store\InMemoryQuestionSource` garde des banques en mémoire ; une bibliothèque d'évaluation ou un
fichier de questions sous licence libre se branche par le même contrat.
