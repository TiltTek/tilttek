<!-- phil-dev source-sha256=58eda54633eb9f47d458b3f4fd5b21b1b559fc476e649e269b759fc865afb98a -->
# Stockage

La bibliothèque donne trois dépôts en mémoire, pour les tests et les scripts ; une application garde ses
jeux-questionnaires, ses parties et ses sessions dans son propre stockage en implémentant les contrats.

| Contrat | Ce qu'il garde |
|---|---|
| `Contract\QuizCatalog` | Les jeux-questionnaires, par identifiant ; en lecture seule |
| `Contract\PlayStore` | Les parties à son rythme, comptées par jeu-questionnaire et par membre, et effacées par membre |
| `Contract\LiveSessionStore` | Les sessions en direct, retrouvées par identifiant ou par le NIP d'une session non terminée |

## Les versions

Une partie et une session en direct comptent leurs changements : chaque changement leur donne leur version suivante, à
partir de 1. `add()` garde une nouvelle à la version 1, et refuse un identifiant pris avec `QuizConflict` ; `save()`
garde la version suivante, et refuse avec `QuizConflict` quand la version stockée n'est pas celle d'avant. Deux réponses
envoyées en même temps à partir de la même version ne comptent jamais toutes les deux : la seconde est refusée.

`LiveQuizzes` relit la session et refait le changement en cas de conflit, jusqu'à `LiveQuizzes::ATTEMPTS` fois, pour que
les réponses de beaucoup de joueurs soient toutes gardées ; `SelfPacedQuizzes` laisse passer le conflit d'une réponse, puisqu'un
membre répond à une question à la fois. Un dépôt adossé à une base de données écrit `save()` comme une mise à jour
conditionnée par la version.

## Les NIP

Un NIP est tenu par une seule session non terminée au plus. `LiveSessionStore::add()` le vérifie et garde la session en
une seule étape atomique : il renvoie false, et ne garde rien, quand une autre session non terminée tient le NIP, et
`LiveQuizzes::open()` en tire alors un autre, jusqu'à `LiveQuizzes::PIN_DRAWS` fois avant de refuser avec
`QuizRefused`. Une recherche par `findByPin()` suivie d'une écriture laisserait deux sessions ouvertes en même temps
prendre toutes deux le NIP qu'elles ont tiré.

Un dépôt adossé à une base de données tient cela par un index unique sur une colonne `active_pin` qui accepte `NULL` :
le NIP tant que la session n'est pas terminée, et `NULL` une fois que `save()` la garde terminée, ce qui libère le NIP.
`add()` insère la ligne et renvoie false sur une violation de cet index. SQLite, MySQL et PostgreSQL acceptent tous
plusieurs `NULL` dans un index unique, si bien que le même schéma marche sur les trois ; un index partiel
(`WHERE state <> 'finished'`) ferait de même sur SQLite et PostgreSQL, mais MySQL n'en a pas.

## Les tentatives

Une partie porte sa `attempt` au jeu-questionnaire, et `PlayStore::countOf($quiz, $player)` dit combien de parties du
jeu-questionnaire un membre a démarrées. `add()` refuse avec `QuizConflict` une partie dont le membre a déjà une partie
du même jeu-questionnaire à la même tentative : `SelfPacedQuizzes::start()` compte alors de nouveau et prend la
tentative suivante, jusqu'à `SelfPacedQuizzes::ATTEMPTS` fois, pour que deux parties démarrées en même temps ne
comptent jamais toutes deux comme la première. Un dépôt adossé à une base de données tient cela par un index unique sur
le jeu-questionnaire, le joueur et la tentative. `erasePlayer($player)` supprime toutes les parties d'un membre et
renvoie combien il y en avait.

## Ce que garde un dépôt

Une partie garde l'identifiant de son jeu-questionnaire, son joueur, sa tentative, le moment où chaque question a été montrée et le
résultat de chaque réponse. Une session garde son NIP, son état, ses équipes et leur notation, ses joueurs — dont le pseudonyme d'un invité — avec
l'équipe de chacun, le résultat de chaque réponse, et les événements de sa fin pas encore remis. Un dépôt qui garde le pseudonyme d'un invité le garde avec la session et le supprime avec
la session ; aucun événement ne le porte.
