<!-- phil-dev source-sha256=16117f4029ddf6503446b34a3cea61723444662370d58c70f37f8a13e8330491 -->
# tilttek/sets-bundle

**Utilisez tilttek/sets dans Symfony : stockages choisis par la configuration, et complétions dont l'événement a été
perdu renvoyées par une commande ou un planificateur.**

tilttek/sets-bundle enregistre le service `Sets` de `tilttek/sets` depuis sa configuration : les stockages des
définitions et des complétions que l'application nomme — la mémoire, pour les tests, quand elle n'en nomme aucun —,
son horloge, son répartiteur d'événements et le recul d'un détenteur dont la remise a échoué. Il ajoute la commande
`tilttek:sets:deliver`, le message `DeliverPendingCompletions` et son *handler*, et le planificateur `tilttek_sets`,
chacun quand son composant Symfony est installé, pour qu'une complétion dont le gestionnaire a levé une exception soit
annoncée de nouveau sans attendre que les possessions de son détenteur changent.

## État

| Partie | État |
|---|---|
| Configuration : stockages, horloge, recul et planificateur | Implémenté et testé |
| `Sets` sur le répartiteur d'événements de l'application | Implémenté et testé |
| La commande `tilttek:sets:deliver`, avec `--limit` et `--holder` | Implémenté et testé |
| Le message `DeliverPendingCompletions` et son *handler* | Implémenté et testé |
| Le planificateur `tilttek_sets`, avec un intervalle, une limite et un verrou | Implémenté et testé |
| Des stockages Doctrine des définitions et des complétions | Pas commencé : l'application nomme les siens |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, les paquets facultatifs et l'enregistrement du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, sa valeur par défaut et ce qu'elle règle. |
| [Reprendre les remises](doc/fr/resuming-deliveries.md) | La commande, le planificateur, son *worker* et son verrou, et le routage du message. |
| [Points d'extension](doc/fr/extension-points.md) | Les stockages, l'horloge et les écouteurs des événements. |
| [Tests](doc/fr/testing.md) | Les tests du bundle. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Tilttek\SetsBundle\TilttekSetsBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_sets.yaml
tilttek_sets:
    definition_store: App\Sets\DefinitionStore
    completion_store: App\Sets\CompletionStore
    clock: clock
```

```sh
bin/console messenger:consume scheduler_tilttek_sets
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `tilttek/sets` ; `symfony/console` pour la commande, `symfony/messenger` pour
le *handler*, `symfony/scheduler` pour le planificateur et `symfony/lock` pour son verrou.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
