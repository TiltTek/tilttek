<!-- phil-dev source-sha256=c0d4951af9ddda655285d402d29997914f552f24da823958e33d8490c0707471 -->
# Séries

Une `StreakRule` compte les jours (ou les semaines ISO, avec `StreakUnit::Week`) consécutifs où un membre fait l'un des
gestes qu'elle lit, avec les faits que doit contenir le contexte de l'activité.

```php
new StreakRule('daily-play', ['game.played'], StreakUnit::Day, new Grace(1, 7));
```

## La journée du membre

Une journée est celle du membre, pas celle du serveur. Un `TimezoneResolver` donne le fuseau horaire de chaque
activité : `FixedTimezone` pour un site dont les membres partagent le même, `ContextTimezone('timezone', $fallback)`
pour le lire dans le contexte de l'activité, que l'application remplit quand elle enregistre le geste, à partir du
profil du membre sur le serveur, jamais d'une valeur envoyée par le client : un client qui choisit le fuseau horaire de
chaque geste peut le placer dans une plage de 26 heures et garder une série quotidienne en vie en agissant un jour sur
deux. Gardé avec
l'activité, le fuseau horaire reste le même lors d'une reconstruction, même après un déménagement du membre. L'état
d'une série retient le fuseau horaire du dernier geste du membre, qu'utilisent le balayage et les lectures.

## Séries en cours

`StreakCalculator` parcourt les unités à partir de la première unité active du membre. Une série se poursuit à travers
chaque unité active, qui ajoute un ; à travers chaque unité gelée et chaque unité manquée que la grâce pardonne, qui
n'ajoutent rien ; et se brise sur toute autre unité manquée. L'unité en cours n'est pas terminée : la manquer ne brise
encore rien. Les unités où rien ne peut changer — avant qu'une série reprenne, et à partir de l'unité en cours — sont
sautées : le travail croît avec les unités actives, gelées et pardonnées, pas avec l'étendue du temps qui les sépare. `StreakStatus` donne la série en cours, `current` (0 une fois brisée), la meilleure série de tous les
temps, `best`, l'unité où la série a commencé et la dernière unité active, ainsi que `graceLeft`.

## Grâce

`new Grace(allowed, window)` pardonne `allowed` unités manquées dans toute fenêtre de `window` unités consécutives :
*un jour par semaine* s'écrit `new Grace(1, 7)`. Un jour pardonné garde la série en vie sans l'allonger.

## Jours gelés

`$progression->freeze($member, 'daily-play', $day)` gèle la journée du membre qui contient le moment, lue dans son
fuseau horaire : un jour gelé manqué garde la série en vie sans l'allonger. L'application décide qui peut geler et ce
que cela coûte (une récompense achetée avec des points, quelques-uns par mois), et `thaw()` l'annule, pour un
remboursement.

## Une série brisée

Une série se brise une seule fois, et `ProgressionObserver::streakBroken()` en est averti. Cela se produit au prochain
geste du membre, quand il trouve terminée la série en cours, ou par `$progression->sweep()`. L'application lance ce
balayage une fois par heure, puisque les membres vivent dans des fuseaux horaires différents ; il n'examine que les
séries encore en vie dont la dernière unité active est passée. Une annulation recompte une série sans annonce.
