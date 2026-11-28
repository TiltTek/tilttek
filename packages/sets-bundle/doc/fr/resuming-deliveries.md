<!-- phil-dev source-sha256=b80797f31e69c3f5e4d843850cc7932b73f621b9a729f2f8399ee8c1415beac9 -->
# Reprendre les remises

Une complétion dont le gestionnaire d'événement a levé une exception reste non remise jusqu'à ce qu'elle soit annoncée
de nouveau. La commande, le message et le planificateur appellent `Sets::deliverPending()`, qui annonce les complétions
des détenteurs qui attendent depuis le plus longtemps ; un détenteur dont la remise a échoué est passé jusqu'à la fin du
délai du recul, et les échecs répétés sont annoncés par un événement `Tilttek\Sets\Event\DeliveryKeepsFailing`.

## La commande

```sh
bin/console tilttek:sets:deliver                      # the 100 holders waiting the longest
bin/console tilttek:sets:deliver --limit=1000         # up to 1 000 holders, at most 10 000
bin/console tilttek:sets:deliver --holder=member:1042 # every undelivered completion of one holder
```

Elle affiche combien de complétions ont été remises. Quand un gestionnaire lève une exception, les autres détenteurs
sont informés quand même et la commande se termine en échec en disant combien de complétions ont été remises et quelle
exception a été levée en premier, pour qu'une tâche cron ou une surveillance le voie. `--limit` ne s'applique pas avec
`--holder`, qui informe le détenteur tout de suite, repoussé ou non.

## Le planificateur

Le planificateur `tilttek_sets` envoie un message `DeliverPendingCompletions` toutes les cinq minutes, pour les 100
détenteurs qui attendent depuis le plus longtemps. Un *worker* consomme le planificateur par son transport,
`scheduler_tilttek_sets` :

```sh
bin/console messenger:consume scheduler_tilttek_sets
```

Une exécution manquée pendant qu'aucun *worker* ne consommait le planificateur est exécutée une fois, pas une fois par
intervalle. Avec un verrou, deux *workers* qui consomment le planificateur n'envoient pas tous deux le message ; sans
verrou, tous deux le peuvent, ce que les gestionnaires idempotents absorbent. Une exécution dont un gestionnaire a levé
une exception se termine par `DeliveryFailed`, que le *worker* journalise ; l'exécution suivante informe de nouveau le
reste.

```yaml
# config/services.yaml
services:
    app.sets_delivery_lock:
        class: Symfony\Component\Lock\LockInterface
        factory: ['@lock.factory', 'createLock']
        arguments: ['tilttek_sets_delivery']
```

## Router le message

Le message est traité par le *worker* qui consomme le planificateur. Pour le traiter ailleurs, routez-le vers un autre
transport :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletions: async
```

Sans le Scheduler, une tâche cron qui exécute la commande fait de même.
