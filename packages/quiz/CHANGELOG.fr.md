<!-- phil-dev source-sha256=a49b0c3575cd44d2b963ce21e8bb8acb21a7aa4c337cdcb5d9a135ac9ad71746 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/quiz` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Modifié

- Les prérequis de développement demandent la dernière version mineure de `twig/twig`, 3.31.

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.1.0-alpha - 2026-11-14

### Ajouté

- `Quiz` : de 1 à 200 questions avec des identifiants distincts, une limite de temps par défaut de 5 à 600 secondes, sa
  notation, la correction et la notation d'une réponse selon son minuteur, `maxScore()`, et `Quiz::fromSource()` qui
  compose un quiz à partir d'une banque d'un `Contract\QuestionSource`.
- Les questions, derrière `Contract\Question` : `SingleChoiceQuestion` avec `trueOrFalse()`, `MultipleChoiceQuestion`
  avec un crédit partiel facultatif, `OrderingQuestion` avec un crédit partiel facultatif, `TypedAnswerQuestion` qui
  compare du texte normalisé — casse, espaces et, à moins de les garder, accents — et `NumericQuestion` avec une
  tolérance ; de 2 à 12 choix, des énoncés de 1 à 1 000 octets, de 0 à 10 000 points.
- Les réponses, derrière `Contract\Answer` : `ChoiceAnswer`, `OrderAnswer`, `TextAnswer` d'au plus 200 octets d'UTF-8 et
  `NumberAnswer` ; une question refuse une réponse d'un autre type.
- `Grade` en millièmes, `Scoring` et `ScoringMode` : la justesse par défaut, ou la rapidité avec une prime de 0 à 100
  pour cent des points qui baisse en ligne droite jusqu'à rien à la fin du temps.
- `QuestionTimer` : des millisecondes lues dans l'horloge PSR-20 de l'application ; `AnswerResult`, temps écoulé
  compris.
- `SelfPacedQuizzes` et `SelfPaced\Play` : la partie d'un membre à son propre rythme, chaque question montrée quand la
  précédente a reçu sa réponse ; un invité refusé ; chaque partie liée à son membre, qui seul y répond, la lit et voit
  son résultat ; les résultats retenus jusqu'à la fin de la partie à moins de `SelfPaced\Feedback::AfterEachAnswer` ;
  chaque partie à une tentative, seule la première comptant, les suivantes pour s'exercer ou refusées par
  `SelfPaced\Replays`, deux parties lancées en même temps jamais toutes deux premières ; `PerfectScore` une seule fois
  par quiz et par membre par défaut, ou pour chaque partie parfaite par `SelfPaced\PerfectScoreRule` ; `erasePlayer()`
  qui supprime les parties d'un membre.
- `LiveQuizzes`, `Live\LiveSession` et `Live\LiveState` : un NIP à six chiffres tiré d'une source sûre et libre parmi
  les séances pas encore finies, jusqu'à 1 000 joueurs, membres et invités, chaque question montrée, à laquelle chaque
  joueur répond une seule fois à temps, fermée avec les absents en temps écoulé, puis révisée ; les changements refaits
  sur un conflit, jusqu'à huit fois. Le NIP n'est pris par `Contract\LiveSessionStore::add()` que si aucune séance pas
  encore finie ne le tient, en une seule étape atomique, et tiré de nouveau sinon, jusqu'à `LiveQuizzes::PIN_DRAWS`
  fois. Les événements de la fin sont gardés dans la séance jusqu'à ce que chacun ait été distribué sans exception :
  `LiveSession::undelivered()` et `markDelivered()`, les événements restants distribués par `finish()` appelée de
  nouveau ou par `LiveQuizzes::deliver()`, un écouteur qui échoue n'arrêtant jamais les autres.
- `Pin` : le NIP que tape un joueur, espaces et traits d'union laissés de côté, au plus 16 octets lus, refusé avec
  `InvalidQuiz` à moins d'être six chiffres ASCII, lu par `LiveQuizzes::join()` avant qu'aucun stockage soit interrogé.
- Les équipes : de 2 à 50 par séance, par identifiant ; un joueur rejoint l'équipe donnée ou la plus petite ;
  `Team\TeamLeaderboard` et `Team\TeamStanding` les classent selon `Team\TeamScoring` — le total de leurs joueurs, ou
  leur moyenne arrondie à la demie supérieure — puis selon le temps, avec des rangs partagés ; l'équipe d'un invité suit
  le membre qu'il est devenu.
- `Player` : un membre désigné par une référence opaque, ou un invité sous un pseudonyme de 1 à 24 caractères sans
  caractères de contrôle ni invisibles, unique par séance quelle que soit sa casse, filtré par un
  `Contract\PseudonymFilter` facultatif ; `LiveQuizzes::claimGuest()` donne la place et les réponses d'un invité au
  membre qu'il est devenu, et la séance oublie le pseudonyme.
- `Pseudonym\WordListFilter`, dont le fichier s'ouvre sur un avertissement de contenu : des mots entiers et des termes
  n'importe où, normalisés — casse, accents, chiffres et symboles qui remplacent des lettres —, jusqu'à 10 000 mots,
  avec `defaults()`, la courte liste de la bibliothèque en anglais et en français, et `with()` pour les mots de
  l'application ; `Pseudonym\ChainFilter` interroge plusieurs filtres dans l'ordre.
- `Leaderboard` et `Standing` : le pointage le plus élevé en premier, puis le moins de millisecondes des réponses qui
  marquent, des rangs partagés (1, 1, 3), `top()` avec les égalités à la limite.
- Des événements qui ne portent que des identifiants : `QuizCompleted` et `PerfectScore`, qui disent tous deux si une
  partie sert à s'exercer, `LiveQuizFinished`, `TeamWon`, `GuestInvited`, et les étapes d'une séance en direct,
  `PlayerJoined`, `QuestionShown` et `QuestionClosed`, chacun distribué une fois le changement enregistré ; les
  événements de la fin d'une séance en direct portent une `key()` stable pour les écouteurs idempotents.
- `Mercure\LiveSessionPublisher` : les étapes d'une séance en direct poussées comme mises à jour Mercure privées sur le
  sujet de la séance, qui ne nomment que la séance et la position d'une question ; `symfony/mercure` suggéré.
- `View\ResultView` et `templates/result.html.twig` : le pointage sur le maximum, le rang d'une partie en direct et de
  l'équipe du joueur, si une partie sert à s'exercer, les blocs `summary` et `celebration` ; le contrôleur Stimulus
  `result` qui distribue `quiz:completed`, `quiz:perfect` et `quiz:team-won` sur la fenêtre une seule fois par partie
  dans un onglet du navigateur, pour la célébration du kit PHIL UX.
- `Contract\QuizCatalog`, `Contract\PlayStore` — avec le nombre de parties d'un quiz par un membre, une partie par
  tentative et l'effacement d'un membre — et `Contract\LiveSessionStore`, avec des versions et une seule séance pas
  encore finie par NIP, et leurs stockages en mémoire, avec `Store\InMemoryQuestionSource`.
- Des exceptions sous `Exception\QuizException` : `InvalidQuiz`, `QuizRefused` et `QuizConflict`.
- Des bancs d'essai d'une réponse dans une séance de 1 000 joueurs, de son classement, du classement de ses joueurs en
  dix équipes et d'un pseudonyme passé par la liste de mots.
- Des cibles de fuzzing du NIP et d'un pseudonyme passé par la liste de mots.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
