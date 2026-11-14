<!-- phil-dev source-sha256=c5cd7f19355dc42c50b379a5477a3606dfd8c5cf5d7fbb62ba0d7fb7772aa186 -->
# tilttek/quiz

**Jeux-questionnaires : des questions de six sortes, une minuterie sur une horloge injectée, un score avec bonus de
vitesse, des parties à son rythme pour les membres et des sessions en direct avec des invités pseudonymes et des
équipes, des classements, un filtre de pseudonymes, des mises à jour en direct par Mercure, ainsi que les
événements et le crochet Stimulus d'une célébration.**

Un jeu-questionnaire est une liste de questions, chacune valant des points et parfois minutée. Un membre le joue à son
rythme : chaque question vient quand la précédente a reçu sa réponse, et la partie se termine après la dernière ; la
partie est celle du membre seul, ses résultats ne se montrent qu'une fois la partie terminée, et seule la première
partie d'un membre compte pour un jeu-questionnaire, les suivantes étant de l'entraînement. Un
animateur le joue en direct : les joueurs, membres ou invités sous un pseudonyme, seuls ou en équipes, rejoignent une
session avec son NIP à six chiffres, et l'animateur montre chaque question à tous en même temps, la ferme, montre le
classement et passe à la suite, chaque écran suivant par Mercure. Un invité n'a pas de compte ; son pseudonyme passe
d'abord une liste de mots, et une fois la session terminée, l'application lui propose de devenir membre, et sa place
et son score le suivent s'il accepte.

Chaque réponse est corrigée sur le serveur, en millièmes pour que le crédit partiel reste exact, et notée selon la
règle du jeu-questionnaire : la justesse par défaut, ou la vitesse, où une bonne réponse rapporte un bonus qui tombe à
rien à la fin du temps. Le temps est lu sur l'horloge PSR-20 que donne l'application, jamais sur le navigateur. La fin
d'une partie envoie `QuizCompleted`, puis `PerfectScore` pour une partie parfaite qui compte et `TeamWon` pour les équipes gagnantes ; le gabarit du
résultat et son contrôleur Stimulus les transforment en événements du DOM `quiz:completed`, `quiz:perfect` et
`quiz:team-won`, sur lesquels joue la célébration du kit PHIL UX.

Il convient à une application qui organise des soirées de questions, des jeux-questionnaires sur l'univers ou les règles
d'un jeu, ou une ronde rapide entre deux conférences. Ce qui se demande sans bonne réponse — un sondage, un vote —
relève d'une bibliothèque de sondages ; une banque de questions tenue ailleurs se branche par `Contract\QuestionSource`.
C'est une bibliothèque PHP ordinaire dont les seules dépendances sont `psr/clock` et `psr/event-dispatcher`, avec
`ext-intl` et `ext-mbstring` pour comparer les réponses saisies ; `symfony/mercure` n'est nécessaire que pour les mises à jour en direct.


## État

| Partie | État |
|---|---|
| `Quiz`, `Scoring`, `ScoringMode`, `Grade`, `QuestionTimer`, `AnswerResult` | Implémentés et testés |
| Questions : `SingleChoiceQuestion` (vrai ou faux compris), `MultipleChoiceQuestion`, `OrderingQuestion`, `TypedAnswerQuestion`, `NumericQuestion` | Implémentées et testées |
| Réponses : `ChoiceAnswer`, `OrderAnswer`, `TextAnswer`, `NumberAnswer` | Implémentées et testées |
| `SelfPacedQuizzes` et `SelfPaced\Play` : les membres à leur rythme, parties liées à leur membre, résultats après la fin, reprises d'entraînement, effacement | Implémentés et testés |
| `SelfPaced\Replays`, `SelfPaced\Feedback`, `SelfPaced\PerfectScoreRule` | Implémentés et testés |
| `LiveQuizzes`, `Live\LiveSession`, `Live\LiveState` : sessions en direct, NIP, invités et leur reprise par un membre | Implémentés et testés |
| `Pin` : le NIP que saisit un joueur | Implémenté et testé |

| `Player`, `Leaderboard`, `Standing` | Implémentés et testés |
| Équipes : `Team\TeamLeaderboard`, `Team\TeamStanding`, `Team\TeamScoring` | Implémentées et testées |
| `Pseudonym\WordListFilter` avec la courte liste de la bibliothèque, `Pseudonym\ChainFilter` | Implémentés et testés |
| `Mercure\LiveSessionPublisher` : les gestes d'une session en direct poussés par Mercure | Implémenté et testé |
| Événements : `QuizCompleted`, `PerfectScore`, `LiveQuizFinished`, `TeamWon`, `GuestInvited`, `PlayerJoined`, `QuestionShown`, `QuestionClosed` | Implémentés et testés |
| `View\ResultView`, `templates/result.html.twig` et le contrôleur Stimulus `result` | Implémentés et testés |
| Dépôts en mémoire et leurs contrats, `Contract\QuestionSource`, `Contract\PseudonymFilter` | Implémentés et testés |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Questions et score](doc/fr/questions-and-scoring.md) | Les sortes de questions, les notes, la minuterie, la justesse et la vitesse. |
| [Jouer](doc/fr/playing.md) | Les parties à son rythme, les sessions en direct, les invités et leurs pseudonymes, les équipes et les classements. |
| [Mises à jour en direct](doc/fr/live-updates.md) | Les gestes d'une session en direct poussés par Mercure. |

| [Célébrations](doc/fr/celebrations.md) | Les événements, le gabarit du résultat et son contrôleur Stimulus. |
| [Stockage](doc/fr/storing.md) | Les contrats de stockage, les versions et les réponses simultanées. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `QuizException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Quiz\Answer\{ChoiceAnswer, NumberAnswer};
use Tilttek\Quiz\Question\{Choice, NumericQuestion, SingleChoiceQuestion};
use Tilttek\Quiz\Store\{InMemoryPlayStore, InMemoryQuizCatalog};
use Tilttek\Quiz\{Player, Quiz, Scoring, SelfPacedQuizzes};

$quiz = new Quiz('lore', [
    new SingleChoiceQuestion('d20', 'How many faces has a d20?', [new Choice('a', 'Twelve'), new Choice('b', 'Twenty')], 'b'),
    new NumericQuestion('year', 'In which year was the first role-playing game published?', 1974, tolerance: 1),
], Scoring::speed(), timeLimit: 20);

$quizzes = new SelfPacedQuizzes(new InMemoryQuizCatalog($quiz), new InMemoryPlayStore(), $dispatcher, $clock);
$quizzes->start('play-7', 'lore', Player::member('member:44'));
$quizzes->answer('play-7', 'member:44', ChoiceAnswer::of('b'));     // null: nothing shows before the end
$quizzes->answer('play-7', 'member:44', new NumberAnswer(1974));    // the last: its AnswerResult, QuizCompleted, then PerfectScore
$quizzes->results('play-7', 'member:44');                           // every AnswerResult: grade, points, bonus
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-intl` et `ext-mbstring` ; `psr/clock` et `psr/event-dispatcher`. Le
gabarit exige Twig, et son contrôleur Stimulus, seulement là où l'application les emploie ; les mises à jour en direct exigent `symfony/mercure`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
