<!-- phil-dev source-sha256=cb26d9f5dc0a8e73d5dc6e2610feea7824e4338031c26af753f14b9134ef9ad6 -->
# Commandes

## `tilttek:leaderboards:snapshot`

```sh
bin/console tilttek:leaderboards:snapshot
bin/console tilttek:leaderboards:snapshot games-month 2026-10
```

Sans arguments, elle prend l'instantané de la période que chaque classement a terminée en dernier, quand il n'en a pas
encore ; elle s'exécute à partir d'un planificateur peu après minuit. Avec un classement et une période, elle prend
l'instantané de cette période. Elle énumère les instantanés pris, avec le nombre de membres classés et gardés. Un
classement sans sa période, ou une période sans son classement, est refusé ; une période non terminée, un classement ou
une période inconnus échouent avec le message de la bibliothèque.

## `tilttek:leaderboards:settle`

```sh
bin/console tilttek:leaderboards:settle
bin/console tilttek:leaderboards:settle weekly-xp 2026-w45
```

Sans arguments, elle règle chaque semaine terminée qu'un classement doté d'une ligue n'a pas réglée, la plus ancienne
d'abord ; elle s'exécute à partir d'un planificateur peu après la fin de la semaine. Avec un classement et une semaine,
elle règle cette semaine. Elle énumère les semaines réglées, avec le nombre de membres qui y ont pris part et le nombre
qui sont montés et descendus ; une semaine déjà réglée n'est pas réglée de nouveau. Un classement sans sa semaine, ou
une semaine sans son classement, est refusé ; une semaine non terminée, un classement sans ligue, un classement ou une
semaine inconnus échouent avec le message de la bibliothèque.

## `tilttek:leaderboards:slide`

```sh
bin/console tilttek:leaderboards:slide
```

Retire de chaque classement sur une fenêtre glissante les gestes plus anciens que son premier jour, et recompte les
scores qu'ils avaient faits ; elle s'exécute à partir d'un planificateur peu après minuit. Elle dit combien de
contributions ont quitté leur fenêtre.

## `tilttek:leaderboards:show`

```sh
bin/console tilttek:leaderboards:show games-month 2026-10 --limit=20
```

Affiche un classement dans une période (celle qui est en cours par défaut) tel que le public le voit : le rang, la
référence du membre pour celui qui a choisi de paraître sous son nom, le pseudonyme pour celui qui en a choisi un, un
tiret pour les autres, et le score.
