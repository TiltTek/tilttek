<!-- phil-dev source-sha256=da762420c4de2816319c77a61f24e61d90a4c51ea02e5f4f69c78bb2c05e5a0c -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\Question` | Une sorte de question bien à elle, corrigée sur la réponse seule | [Questions et score](questions-and-scoring.md#les-questions) |
| `Contract\Answer` | La réponse que prend sa propre sorte de question | [Questions et score](questions-and-scoring.md#les-questions) |
| `Contract\QuestionSource` | Une banque de questions : une bibliothèque d'évaluation, un fichier, un éditeur | [Questions et score](questions-and-scoring.md#composer-un-jeu-questionnaire-à-partir-dune-banque) |
| `Contract\QuizCatalog` | Là où les jeux-questionnaires sont lus | [Stockage](storing.md) |
| `Contract\PlayStore`, `Contract\LiveSessionStore` | Là où les parties et les sessions sont gardées, à une version | [Stockage](storing.md) |
| `Contract\PseudonymFilter` | Les pseudonymes qu'un invité ne peut pas prendre : la liste de mots de la bibliothèque, sa propre liste, un service de modération | [Jouer](playing.md#filtrer-les-pseudonymes) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui apprend la fin de chaque partie | [Célébrations](celebrations.md#les-événements) |
| `Psr\Clock\ClockInterface` | Le temps que lit chaque minuterie | [Questions et score](questions-and-scoring.md#la-minuterie) |
| `Random\Randomizer` | La source des NIP | [Installation](installation.md#brancher-la-bibliothèque) |
| `Symfony\Component\Mercure\HubInterface` et un préfixe de sujet | Là où les gestes d'une session en direct sont poussés | [Mises à jour en direct](live-updates.md) |
| Les blocs `summary` et `celebration` | Les mots du résultat, et sa célébration | [Célébrations](celebrations.md#le-gabarit-du-résultat) |
| Les identifiants d'équipe et `Team\TeamScoring` | Les équipes d'une session, et la façon dont les scores de leurs joueurs s'additionnent | [Jouer](playing.md#les-équipes) |

Chaque point est passé à un constructeur ; aucun service de la bibliothèque ne garde d'état entre deux appels.
