<!-- phil-dev source-sha256=35184024e6bc96ec1256c29ef92706cc09a7e6cdea699c2f2b936d49ee8b5683 -->
# Sécurité

## Les réponses restent sur le serveur

Une énigme vérifiée dans le navigateur se résout en lisant son code : la réponse, son empreinte ou la fonction qui la
vérifie voyagent avec la page. La bibliothèque vérifie chaque proposition sur le serveur, et rien de ce qu'elle donne
pour le navigateur ne contient une réponse :

- `View\ScenarioView` et `View\PuzzleView` contiennent des états, des titres, les indices révélés et des comptes ; une
  énigme verrouillée ne montre ni son titre ni ses indices.
- Les événements nomment le scénario, l'équipe, l'énigme et une heure ; aucun ne porte une proposition ni une réponse,
  si bien qu'un journal de ces événements ne montre pas non plus de réponse.
- `Answer\AnswerKey` garde ses réponses privées et compare une proposition à chacune avec `hash_equals()`, en temps
  constant.

Une vérification propre à l'application, qui implémente `Contract\AnswerCheck`, respecte la même règle : elle détient sa
réponse et ne la donne jamais.

## La limite de propositions

`PuzzleEngine` ne peut pas être créé sans `Contract\GuessLimiter`. Chaque proposition compte une tentative sous le
scénario, l'équipe et l'énigme ; au-delà de la limite de la période, la proposition n'est pas vérifiée et
`Event\GuessThrottled` est envoyé, pour qu'un journal montre un script qui essaie tous les mots. Les comptes vivent là où
chaque *worker* les lit — `Limiter\RateLimiterGuessLimiter` sur un bassin de cache —, jamais dans un seul processus.

Une proposition refusée parce que mal formée compte quand même dans la limite : envoyer des propositions vides ne la
contourne pas.

## Les entrées bornées

| Entrée | Borne |
|---|---|
| Un scénario en JSON | 256 Kio, 8 niveaux, champs connus seulement |
| Les énigmes d'un scénario | 200, comptées avant d'être lues |
| Les réponses acceptées et partielles d'une énigme | 10 et 20 |
| Une réponse, une réponse partielle, une proposition | 200 caractères, UTF-8 |
| Les indices d'une énigme | 10, chacun de 2 000 caractères |
| Les énigmes qu'une énigme exige | 50 |
| Les propositions gardées dans un avancement pour une énigme | 1 000 000 |

Vérifier un scénario visite chaque énigme et chaque exigence un nombre borné de fois : un cycle se trouve en un seul
parcours du graphe, et l'accessibilité en une passe par énigme au plus. Les bancs d'essai mesurent le plus grand
scénario.

## Les membres

Une équipe est une `ResourceReference` de `phil/resource`, une référence opaque que l'application donne : la
bibliothèque ne lit aucun nom, courriel ni compte. Une référence dont l'identifiant ressemble à une adresse courriel est
refusée.
