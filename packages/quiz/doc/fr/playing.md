<!-- phil-dev source-sha256=dbb0a7a6e5a9c868d28ca02bb28dd866272318c3241b4f5d1007a0b0e045a863 -->
# Jouer

## À son rythme

`SelfPacedQuizzes::start()` démarre une partie d'un jeu-questionnaire pour un membre ; `answer()` répond à la question
courante pour ce membre et montre la suivante, dont la minuterie démarre à cet instant. Après la dernière réponse, la
partie est terminée : `QuizCompleted` est envoyé, puis `PerfectScore` quand toutes les réponses étaient bonnes et dans
le temps et que la partie compte.

```php
$quizzes->start('play-7', 'lore', Player::member('member:44'));
$quizzes->answer('play-7', 'member:44', ChoiceAnswer::of('b'));   // null: the play is not over
$play = $quizzes->play('play-7', 'member:44');
$play->position();     // the current question
$play->timer($quiz);   // its timer: remaining(), deadline()
// ... the last answer returns its AnswerResult, and the play is over
$quizzes->results('play-7', 'member:44');   // list<AnswerResult>
$quizzes->result('play-7', 'member:44');    // the View\ResultView of the result template
```

Un invité ne joue jamais à son rythme : la partie est refusée avec `QuizRefused`. Une partie terminée ne prend plus de
réponses.

### Une partie appartient à son joueur

Chaque appel sauf `start()` prend le membre qui joue. `answer()`, `play()`, `results()` et `result()` refusent la partie
d'un autre avec `QuizRefused`, comme ils refusent une partie qui n'existe pas, pour qu'un membre n'apprenne jamais que la
partie d'un autre existe. L'application passe le membre de la requête, jamais un identifiant lu dans un formulaire.

### Les résultats une fois la partie terminée

Les réponses d'un membre ne disent rien avant la fin de la partie : `answer()` renvoie null pour toutes les réponses
sauf la dernière, et `results()` et `result()` refusent une partie qui n'est pas terminée. Un membre ne peut pas
apprendre les bonnes réponses au milieu d'une partie pour s'en servir dans la même partie. Un jeu-questionnaire fait
pour enseigner en cours de route montre chaque résultat tout de suite :

| `SelfPaced\Feedback` | Ce qu'un membre voit avant la fin |
|---|---|
| `AfterPlay`, par défaut | Rien : `answer()` renvoie null, `results()` et `result()` sont refusés |
| `AfterEachAnswer` | Le résultat de chaque réponse, renvoyé par `answer()`, ainsi que les résultats et le score jusqu'ici |

`SelfPaced\Play::results()` et `score()` sont l'enregistrement que garde le dépôt ; une page les montre par `results()`
et `result()` du service, qui appliquent la règle.

### Les parties qui comptent, et l'entraînement

Une fois une partie terminée, son membre connaît les bonnes réponses ; toute partie démarrée après la première en a déjà
vu les questions. Chaque partie porte sa `attempt` : 1 pour la première partie du membre au jeu-questionnaire, une de
plus pour chaque partie qu'il a démarrée avant, terminée ou non. Seule la première compte :

| `SelfPaced\Replays` | La deuxième partie d'un membre à un jeu-questionnaire |
|---|---|
| `Practice`, par défaut | Jouée et notée pour que le membre la voie ; `isPractice()` est vrai, `QuizCompleted` et la vue du résultat disent `practice`, et `PerfectScore` n'est pas envoyé |
| `Refused` | Refusée avec `QuizRefused` : chaque membre joue chaque jeu-questionnaire une fois |

`PerfectScore` est donc envoyé une fois au plus par jeu-questionnaire et par membre : pour la première partie, quand
elle est parfaite. Un jeu-questionnaire dont l'entraînement mérite aussi des feux d'artifice règle
`SelfPaced\PerfectScoreRule::EveryPerfectPlay` : chaque partie parfaite envoie alors `PerfectScore`, avec `practice`
vrai pour celles qui ne comptent pas, pour qu'un écouteur qui attribue un insigne les laisse tout de même de côté. Deux
parties démarrées en même temps ne comptent jamais toutes deux : le dépôt refuse une seconde partie à la même
tentative, et `start()` compte de nouveau et prend la tentative suivante, jusqu'à `SelfPacedQuizzes::ATTEMPTS` fois.

### Effacer un membre

`SelfPacedQuizzes::erasePlayer()` supprime toutes les parties d'un membre et renvoie combien il y en avait, quand son
compte est effacé. Un membre qui joue de nouveau ensuite repart à la tentative 1 : les parties qui disaient ce qu'il
avait vu disparaissent avec le compte.

## En direct

L'animateur ouvre une session ; la bibliothèque tire un NIP à six chiffres qu'aucune autre session non terminée ne
détient, à partir de la source sécurisée du système par défaut. Les joueurs rejoignent la session par le NIP à tout
moment jusqu'à sa fin ; celui qui arrive en retard manque les questions déjà fermées. `join()` prend le NIP tel que le joueur
l'a saisi : les espaces et les traits d'union entre les chiffres sont écartés (`123 456`, `123-456`), et une saisie qui
n'est pas faite de six chiffres ASCII une fois ceux-ci écartés, ou qui dépasse 16 octets, est refusée avec
`InvalidQuiz` avant qu'aucun dépôt soit interrogé ; `Pin::parse()` la lit de la même façon pour un formulaire.


```php
$session = $live->open('night-12', 'lore');
$live->join($session->pin, Player::member('member:44'));
$live->join($session->pin, Player::guest('guest-91', 'Pixel'));

$live->next('night-12');                                   // shows the first question to all
$live->answer('night-12', 'member:44', ChoiceAnswer::of('b'));
$live->close('night-12');                                  // the players without an answer are timed out
$live->session('night-12')->leaderboard()->top(3);
// ... next(), answer(), close() for each question
$live->finish('night-12');
```

| État | Depuis | Gestes |
|---|---|---|
| `Lobby` | `open()` | `next()` |
| `Question` | `next()` | `answer()` par chaque joueur une fois, `close()` |
| `Review` | `close()` | `next()`, `finish()` |
| `Finished` | `finish()` | `claimGuest()` |

Chaque joueur répond une fois à une question ; une réponse une fois le temps écoulé est refusée. Fermer une question
déjà fermée ne fait rien.

| Geste | Événement, une fois la session enregistrée |
|---|---|
| Un joueur se joint pour la première fois | `PlayerJoined` |
| `next()` | `QuestionShown`, avec la position de la question |
| `close()` d'une question montrée | `QuestionClosed`, avec la position de la question |
| `finish()` | `LiveQuizFinished`, puis `QuizCompleted` pour chaque joueur, suivi de `PerfectScore` pour celui qui a bien répondu à toutes les questions, puis `TeamWon` pour chaque équipe gagnante, puis `GuestInvited` pour chaque invité |

Un joueur qui se joint de nouveau, et une question déjà fermée, ne disent rien. Les écrans suivent ces gestes par les
[mises à jour en direct](live-updates.md).


Une session accepte jusqu'à 1 000 joueurs. Beaucoup de joueurs répondent en même temps : chaque réponse est une
nouvelle version de la session, et `LiveQuizzes` relit la session et refait le changement quand une autre réponse a été
enregistrée avant, jusqu'à huit fois ; voir [Stockage](storing.md).

## Les invités

Un invité joue en direct seulement, sous un pseudonyme de 1 à 24 caractères, sans espaces aux extrémités, sans
caractères de contrôle ni invisibles, pris une seule fois par session quelle que soit sa casse. L'application donne à
l'invité un identifiant opaque ; le pseudonyme ne quitte jamais la session, et aucun événement ne le porte.


Une fois la session terminée, `GuestInvited` dit à l'application de proposer à l'invité de devenir membre. S'il accepte,
`LiveQuizzes::claimGuest()` donne la place, l'équipe et les réponses de l'invité au membre, sauf si le membre a déjà joué la
session ; la session oublie alors le pseudonyme.

### Filtrer les pseudonymes

Un `Contract\PseudonymFilter` donné à `LiveQuizzes` refuse les pseudonymes que l'application ne veut pas ; sans filtre,
tout pseudonyme de la bonne forme est permis. La bibliothèque en donne deux :

| Filtre | Refuse |
|---|---|
| `Pseudonym\WordListFilter::defaults()` | Une courte liste à la bibliothèque, en anglais et en français : les noms qui feraient passer un invité pour l'animateur ou le personnel (`admin`, `host`, `animateur`, `moderator`…) et les insultes courantes |
| `new Pseudonym\WordListFilter($words, $anywhere)` | La seule liste de l'application |
| `Pseudonym\ChainFilter` | Ce que refuse l'un quelconque de ses filtres, interrogés dans l'ordre |

Une liste de mots met le pseudonyme et ses mots sous une forme commune avant de les comparer : minuscules, accents
écartés, substitutions courantes de chiffres et de symboles lues comme des lettres (`4dm1n` est `admin`), tout ce qui
n'est pas une lettre écarté. Un mot de `words` est refusé comme mot entier du pseudonyme, ou comme pseudonyme entier une
fois ses mots joints (`Ad Min`) ; un mot de `anywhere` est refusé où qu'il se trouve, pour les termes qu'aucun mot
courant ne contient. `Scunthorpe`, `Cassandra` et `Salopette` passent. Une liste contient jusqu'à 10 000 mots d'au plus
64 caractères ; un texte de plus de 64 caractères est refusé.

La liste de la bibliothèque est courte exprès et n'est pas de la modération : une application ajoute ses propres mots,
ou un service de modération, et garde la liste de la bibliothèque devant :

```php
use Tilttek\Quiz\Pseudonym\{ChainFilter, WordListFilter};

$filter = new ChainFilter(
    WordListFilter::defaults()->with($applicationWords, $applicationTermsAnywhere),
    $moderationService,          // a Contract\PseudonymFilter of the application
);
```

## Les équipes

Une session peut se jouer par équipes : `open($session, $quiz, ['red', 'blue', 'green'])` déclare de 2 à 50 équipes, par
identifiant ; l'application garde leurs noms et leurs couleurs. Un joueur se joint à l'équipe donnée, `join($pin,
$player, 'blue')`, ou, sans équipe, à celle qui a le moins de joueurs, la première déclarée en cas d'égalité ; un joueur
qui se joint de nouveau reste dans son équipe. Une session sans équipes refuse une équipe.

```php
$session = $live->open('night-12', 'lore', ['red', 'blue'], TeamScoring::Average);
$live->join($session->pin, Player::member('member:44'), 'red');
$live->join($session->pin, Player::guest('guest-91', 'Pixel'));          // the smaller team
$live->session('night-12')->teamLeaderboard()->top(1);                    // list<Team\TeamStanding>
```

Chaque joueur répond toujours pour lui-même, et garde sa propre place dans `leaderboard()`. `teamLeaderboard()` classe les
équipes selon le `Team\TeamScoring` de la session :

| `TeamScoring` | Le score et le temps d'une équipe |
|---|---|
| `Total`, la valeur par défaut | La somme des scores et des temps de ses joueurs : une équipe plus nombreuse peut marquer davantage |
| `Average` | Leur moyenne, arrondie à l'entier le plus proche, la moitié vers le haut : les équipes de tailles différentes rivalisent à armes égales ; une équipe sans joueurs marque 0 |

Le score le plus élevé vient d'abord, puis le moins de millisecondes ; les équipes égales sur les deux partagent un rang
(1, 1, 3). Un `Team\TeamStanding` contient le rang, l'équipe, son score, les réponses de ses joueurs entièrement
bonnes, son temps et son nombre de joueurs. `members($team)` énumère les joueurs d'une équipe, `teamOf($player)` l'équipe
d'un joueur. À la fin, `TeamWon` est envoyé pour chaque équipe au premier rang avec un score supérieur à 0.


## Les classements

`LiveSession::leaderboard()` classe les joueurs : le score le plus élevé d'abord ; à scores égaux, le joueur dont les
réponses qui comptent ont pris le moins de millisecondes ; les joueurs égaux sur les deux partagent un rang, et le rang
suivant saute les places qu'ils occupent (1, 1, 3). Une mauvaise réponse, ou aucune, ne compte jamais dans le temps.
`top(3)` garde les rangs jusqu'à 3, égalités à la limite comprises ; `standingOf()` lit la place d'un joueur.
