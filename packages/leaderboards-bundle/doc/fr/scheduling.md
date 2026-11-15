<!-- phil-dev source-sha256=864f08577ebec667d5c51030d9ffe88f46315bac3b76fc0e2ad1ad6d0972d801 -->
# Planification

Trois tâches attendent l'horloge : l'instantané de chaque classement à la fin de sa période, le règlement de chaque
semaine d'une ligue, et les gestes qui sortent de chaque fenêtre glissante. Chacune est idempotente (faite une seule
fois, peu importe qui la demande en premier) et trouve seule ce qui est dû, chaque classement dans son fuseau horaire.
Elles s'exécutent donc à une fréquence régulière plutôt qu'au minuit d'un classement.

## Symfony Scheduler

```yaml
tilttek_leaderboards:
    schedule:
        enabled: true
        frequency: '1 hour'
```

Avec le calendrier activé, le bundle enregistre le calendrier `tilttek_leaderboards`, qui envoie un message `TakeDue` à
cette fréquence, et `TakeDueHandler`, qui prend les instantanés dus, règle les semaines dues et fait glisser les
fenêtres. Un *worker* le consomme :

```sh
bin/console messenger:consume scheduler_tilttek_leaderboards
```

`TakeDue` ne porte rien : ce qui est dû se lit dans les stockages quand le message est traité. Activer le calendrier
sans `symfony/scheduler` échoue à la compilation du conteneur.

## Un cron

Sans Symfony Scheduler, les commandes font la même chose :

```sh
5 * * * * bin/console tilttek:leaderboards:snapshot && bin/console tilttek:leaderboards:settle && bin/console tilttek:leaderboards:slide
```

## Quand le planificateur est en retard

Un membre qui entre dans une division d'une nouvelle semaine règle d'abord, dans la requête, les semaines encore
ouvertes avant celle-ci : son palier est juste même quand aucun *worker* ne s'est exécuté. Les instantanés attendent la
prochaine exécution.
