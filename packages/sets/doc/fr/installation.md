<!-- phil-dev source-sha256=b79590fc1df029bb85bad23b783d68b0090a65a515228ab74bfd3ed6d70dcd3b -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource` 0.1, dont `ResourceReference` nomme un détenteur.
- `psr/clock` et `psr/event-dispatcher` : l'horloge des complétions et le répartiteur des événements, tous deux
  facultatifs.
- Suggérés : `symfony/console`, `symfony/messenger`, `symfony/scheduler` et `symfony/lock`, 8.2 ou une version plus
  récente, pour l'intégration qui reprend les remises ; voir
  [Reprendre les remises avec Symfony](#reprendre-les-remises-avec-symfony).

## Installer

```sh
composer require tilttek/sets
```

## Câbler la bibliothèque

```php
use Tilttek\Sets\Sets;

$sets = new Sets(
    $definitions,   // Contract\DefinitionStore: every version of every set
    $completions,   // Contract\CompletionStore: the completions, one per set, tier and holder
    $clock,         // a PSR-20 clock; UtcClock, the system's in UTC, by default
    $dispatcher,    // a PSR-14 dispatcher, told of each completion; null to dispatch nothing
);
```

`Sets` ne garde rien entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker*.
`InMemoryDefinitionStore` convient aux ensembles déclarés dans la configuration de l'application et définis au
démarrage ; `InMemoryCompletionStore` garde tout dans le processus, pour les tests et les scripts.

## Reprendre les remises avec Symfony

Une complétion dont le gestionnaire d'événement a levé une exception reste non remise jusqu'à ce qu'elle soit annoncée
de nouveau (voir [Remise](progress-and-completions.md#remise)). Le paquet fournit une intégration Symfony facultative,
sous `Tilttek\Sets\Symfony`, qui annonce de nouveau ces complétions sans attendre que les possessions de leurs
détenteurs changent. Elle n'exige aucun bundle : l'application enregistre comme services les classes qu'elle veut.
Chacune utilise un paquet suggéré :

| Classe | Ce que c'est | Exige |
|---|---|---|
| `Symfony\DeliverCommand` | La commande `tilttek:sets:deliver` | `symfony/console` |
| `Symfony\DeliverPendingCompletions`, `Symfony\DeliverPendingCompletionsHandler` | Un message qui porte seulement une limite, et son *handler* | `symfony/messenger` |
| `Symfony\DeliverySchedule` | Le planificateur `tilttek_sets`, qui envoie ce message à intervalles | `symfony/scheduler`, et `symfony/lock` pour son verrou |

```sh
composer require symfony/console symfony/messenger symfony/scheduler
```

Chacune appelle `Sets::deliverPending()` : le stockage de complétions implémente donc aussi
[`Contract\PendingDeliveries`](extension-points.md#implémenter-un-stockage) ; `Store\InMemoryCompletionStore` le fait.

### Enregistrer les services

Avec l'autoconfiguration, les attributs des classes font le reste : `#[AsCommand]` enregistre la commande,
`#[AsMessageHandler]` le *handler* et `#[AsSchedule]` le planificateur.

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Tilttek\Sets\Sets:
        arguments:
            $definitions: '@App\Sets\DefinitionStore'   # the application's Contract\DefinitionStore
            $completions: '@App\Sets\CompletionStore'   # its Contract\CompletionStore and Contract\PendingDeliveries
            $clock: '@clock'
            $events: '@event_dispatcher'

    Tilttek\Sets\Symfony\DeliverCommand: ~
    Tilttek\Sets\Symfony\DeliverPendingCompletionsHandler: ~
    Tilttek\Sets\Symfony\DeliverySchedule: ~
```

### La commande

```sh
bin/console tilttek:sets:deliver                      # the 100 holders waiting the longest
bin/console tilttek:sets:deliver --limit=1000         # up to 1 000 holders, at most 10 000
bin/console tilttek:sets:deliver --holder=member:1042 # every undelivered completion of one holder
```

Elle affiche combien de complétions ont été remises. Quand un gestionnaire lève une exception, les autres détenteurs
sont informés quand même et la commande se termine par un échec qui dit combien de complétions ont été remises et
quelle exception a été levée en premier, pour qu'une tâche *cron* ou une surveillance le voie. `--limit` ne s'applique
pas avec `--holder`.

### Le planificateur

`DeliverySchedule` envoie un message `DeliverPendingCompletions` toutes les cinq minutes, pour les 100 détenteurs qui
attendent depuis le plus longtemps. Un *worker* consomme le planificateur par son transport, `scheduler_tilttek_sets` :

```sh
bin/console messenger:consume scheduler_tilttek_sets
```

L'intervalle, la limite et un verrou sont les arguments du constructeur du planificateur :

```yaml
# config/services.yaml
services:
    Tilttek\Sets\Symfony\DeliverySchedule:
        arguments:
            $frequency: '15 minutes'   # a relative format, a number of seconds, or a DateInterval
            $limit: 500                # 1 to 10 000 holders a run
            $lock: '@tilttek_sets.delivery_lock'

    tilttek_sets.delivery_lock:
        class: Symfony\Component\Lock\LockInterface
        factory: ['@lock.factory', 'createLock']
        arguments: ['tilttek_sets_delivery']
```

Une exécution manquée pendant qu'aucun *worker* ne consommait le planificateur est exécutée une seule fois, et non une
fois par intervalle. Avec le verrou, deux *workers* qui consomment le planificateur n'envoient pas tous deux le
message ; sans lui, ils le peuvent, ce que les gestionnaires idempotents absorbent. Une exécution dont un gestionnaire
a levé une exception se termine par `DeliveryFailed`, que le *worker* journalise ; l'exécution suivante annonce le reste
de nouveau.

Le message est traité par le *worker* qui consomme le planificateur. Pour le traiter ailleurs, acheminez-le vers un
autre transport :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Tilttek\Sets\Symfony\DeliverPendingCompletions: async
```

Sans le planificateur, une tâche *cron* qui exécute la commande fait la même chose.
