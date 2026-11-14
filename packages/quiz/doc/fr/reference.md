<!-- phil-dev source-sha256=fa48907b98ef459e309bba91e4608a1ab9128ee059201e1d0691b1c54ebbe60e -->
# Référence

| Type | Rôle |
|---|---|
| `Quiz` | Les questions dans l'ordre, la limite de temps par défaut et la notation ; corrige et note une réponse |
| `Scoring`, `ScoringMode` | La justesse ou la vitesse, et le bonus d'une réponse rapide |
| `Grade` | À quel point une réponse est juste, en millièmes |
| `QuestionTimer` | Quand une question a été montrée et combien de temps elle dure |
| `AnswerResult` | Ce qu'une réponse a rapporté : note, points, bonus, millisecondes, expirée ou non |
| `Player` | Un membre, ou un invité sous un pseudonyme |
| `Pin` | Lit le NIP qu'un joueur saisit |
| `SelfPacedQuizzes` | Démarre, reçoit les réponses et montre les parties à son rythme, chacune à son membre seul ; efface les parties d'un membre |
| `SelfPaced\Play` | La partie d'un membre à son rythme, à une tentative et à une version |
| `SelfPaced\Replays` | `Practice` ou `Refused` : les parties suivantes d'un membre à un jeu-questionnaire |
| `SelfPaced\Feedback` | `AfterPlay` ou `AfterEachAnswer` : quand un membre voit ses résultats |
| `SelfPaced\PerfectScoreRule` | `OncePerQuizAndMember` ou `EveryPerfectPlay` |
| `LiveQuizzes` | Ouvre, fait rejoindre, montre, reçoit les réponses, ferme et termine les sessions en direct ; donne la place d'un invité à un membre |
| `Live\LiveSession` | Une session en direct, à une version |
| `Live\LiveState` | `Lobby`, `Question`, `Review` ou `Finished` |
| `Leaderboard`, `Standing` | Le classement d'une session en direct |
| `Team\TeamLeaderboard`, `Team\TeamStanding` | Le classement des équipes d'une session en direct |
| `Team\TeamScoring` | `Total` ou `Average` |
| `Pseudonym\WordListFilter`, `Pseudonym\ChainFilter` | Des filtres de pseudonymes : une liste de mots, et des filtres interrogés dans l'ordre |
| `Mercure\LiveSessionPublisher` | Pousse les gestes d'une session en direct par Mercure |

| `Question\SingleChoiceQuestion`, `MultipleChoiceQuestion`, `OrderingQuestion`, `TypedAnswerQuestion`, `NumericQuestion` | Les sortes de questions |
| `Question\Choice` | Un choix, ou un élément à ordonner |
| `Answer\ChoiceAnswer`, `OrderAnswer`, `TextAnswer`, `NumberAnswer` | Les sortes de réponses |
| `Contract\Question`, `Contract\Answer` | Une question, une réponse |
| `Contract\QuizCatalog`, `Contract\PlayStore`, `Contract\LiveSessionStore` | Les dépôts |
| `Contract\QuestionSource` | Une banque de questions |
| `Contract\PseudonymFilter` | Les pseudonymes permis |
| `Store\InMemoryQuizCatalog`, `InMemoryPlayStore`, `InMemoryLiveSessionStore`, `InMemoryQuestionSource` | Les dépôts en mémoire |
| `Event\QuizCompleted`, `PerfectScore`, `LiveQuizFinished`, `TeamWon`, `GuestInvited` | Voir [Célébrations](celebrations.md#les-événements) |
| `Event\PlayerJoined`, `QuestionShown`, `QuestionClosed` | Voir [Jouer](playing.md#en-direct) |
| `View\ResultView` | Ce que montre le gabarit du résultat |
| `Exception\QuizException`, `InvalidQuiz`, `QuizRefused`, `QuizConflict` | Voir [Exceptions](exceptions.md) |

`Identifier` et `Question\Rules` sont internes.

Le gabarit `templates/result.html.twig` et le contrôleur Stimulus `result`, dans `assets/`, sont décrits dans
[Célébrations](celebrations.md).
