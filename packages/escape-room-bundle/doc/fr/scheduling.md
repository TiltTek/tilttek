<!-- phil-dev source-sha256=495f467bc528724c7b7ac6339325af4d4c491b810fdbc47fa167f67e31f5e749 -->
# Planification

Une partie dont la minuterie a atteint zéro prend fin quand `GameDesk::expire()` est appelée : par le prochain coup
refusé, ou par l'horaire.

## Symfony Scheduler

```yaml
tilttek_escape_room:
    schedule:
        enabled: true
        frequency: 15 seconds   # how often the games due are looked for
        batch: 100              # how many one run ends at most
```

L'horaire `tilttek_escape_room` envoie un message `ExpireDue` à la fréquence donnée ; `ExpireDueHandler` met fin aux
parties échues, les plus anciennes d'abord. Le message ne transporte rien : les parties sont lues dans le magasin quand
il est traité. Lancez son *worker* :

```sh
php bin/console messenger:consume scheduler_tilttek_escape_room
```

Une partie déjà terminée, ou dont le temps n'est plus écoulé, est laissée telle quelle : un message traité deux fois, ou
par deux *workers* l'un après l'autre, met fin à chaque partie une seule fois. La partie prend fin au moment où sa
minuterie a atteint zéro, non au moment où l'horaire l'a trouvée, de sorte que le temps final est le même quelle que
soit la fréquence.

## La commande

```sh
php bin/console tilttek:escape-room:expire --limit=100
```

met fin aux parties échues, comme le fait l'horaire, depuis un *cron* ou à la main.
