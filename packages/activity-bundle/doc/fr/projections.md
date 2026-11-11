<!-- phil-dev source-sha256=743569d41395a821009de58c60597107bd762a71ce74252a578c920651baf706 -->
# Projections et commandes

## Écrire une projection

Un service qui implémente `Tilttek\Activity\Projection\Projection` reçoit l'étiquette `tilttek_activity.projection`
par configuration automatique et rejoint `Projections`. Son point de reprise est gardé dans
`tilttek_activity_checkpoint` sous son nom. La projection garde son propre état où elle veut — sa propre table, un
cache — et doit tolérer de voir une entrée deux fois après un plantage (consultez la documentation de la bibliothèque).

## Projections en direct

Avec `projections: live`, la valeur par défaut, chaque nouvelle entrée fait rattraper, dans la même requête, chaque
projection dont le filtre la lit. Une projection qui échoue laisse passer son exception une fois l'entrée écrite, et
son point de reprise reste avant l'entrée.

## Projections par Messenger

Avec `projections: messenger`, chaque nouvelle entrée envoie un message `CatchUpProjection` sur le `projection_bus`,
pour chaque projection dont le filtre la lit ; `CatchUpProjectionHandler` fait rattraper cette projection. La requête
ne fait qu'écrire l'entrée et envoyer le message : acheminez le message vers un transport asynchrone, consommé par un
seul *worker*, pour qu'une projection ne s'exécute jamais deux fois en même temps.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            projections: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection: projections
```

```sh
bin/console messenger:consume projections --limit=1000
```

Le bundle n'achemine rien lui-même : le transport, ses nouvelles tentatives et le nombre de ses consommateurs sont le
choix de l'application, écrit dans son propre `framework.messenger.routing`. Sans route, Messenger traite le message
sur le bus dès son envoi, dans la requête, comme le font les projections en direct.

Le message ne porte que des identifiants : le nom de la projection et la position de l'entrée. L'activité reste dans le
journal, que lit le *handler*.

- **Le journal est la boîte d'envoi.** L'entrée est écrite avant l'envoi du message. Un message perdu en route est
  compensé par le message suivant de la projection, qui la fait rattraper au-delà de chaque entrée qu'elle n'a pas vue,
  ou par `tilttek:activity:catch-up`.
- **Les points de reprise sont la boîte de réception.** Un message livré deux fois, ou après un message plus récent,
  trouve la projection déjà au-delà de son entrée et n'applique rien. Un message pour une projection que l'application
  n'a plus est abandonné.

Sans `symfony/messenger`, `projections: messenger` échoue à la compilation du conteneur.

## Projections manuelles

Avec `projections: manual`, rien ne suit le journal à mesure qu'il s'écrit : lancez `tilttek:activity:catch-up` depuis
une tâche planifiée.

## Commandes

| Commande | Fait |
|---|---|
| `tilttek:activity:catch-up [projection…]` | Remet à chaque projection, ou à celles nommées, les entrées qu'elle n'a pas vues, et indique combien chacune en a appliqué |
| `tilttek:activity:rebuild <projection> [--since=2026-11-01]` | Remet la projection à zéro à partir de la date — en entier sans elle — et rejoue cette période du journal |
| `tilttek:activity:status` | Énumère les projections et le nombre d'entrées que chacune doit encore voir |

Une projection inconnue ou une date illisible termine la commande sur une erreur et ne change rien. Lancez un seul
rattrapage ou une seule reconstruction d'une projection à la fois.
