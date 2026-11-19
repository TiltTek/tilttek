<!-- phil-dev source-sha256=175512cd2fcf4d567f92495bd69c4106dd18ee1170d03245868efb5d935c1f51 -->
# Configuration

```yaml
tilttek_table_safety:
    store: doctrine                   # or memory, for tests
    connection: default               # the Doctrine DBAL connection
    table_access: App\TableSafety\Tables
    layout: base.html.twig            # the template the screens extend, block "body"
    defaults:                         # what a table that chose nothing uses
        tools: [lines-and-veils, consent-checklist, session-zero, x-card, script-change, open-door, check-in, stars-and-wishes]
        named_signals: false
        signals_seen_by: table        # or game-master: the discreet signal
        template: campaign            # one-shot, campaign, convention or online
    mercure:
        hub: mercure.hub.default      # the service of the hub; none by default
        hub_name: null                # the hub whose authorization cookie the viewers are given
        topic_prefix: 'urn:tilttek:table-safety:'
    retention: 30 days                # how long a signal is kept before the purge
    feedback_threshold: 3             # the stars and wishes of a session are shown in groups of this many participants
    kit_subdirectory: null            # where the kit's recipes are; the Toolkit's subdirectory when null
    privacy:
        participant_kind: member      # the kind of reference a data subject of phil/privacy-bundle stands for
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `doctrine` | Où les réglages, les réponses et les signaux sont gardés. |
| `connection` | `default` | La connexion Doctrine DBAL des stockages. |
| `table_access` | aucune | Le service qui implémente `TableAccess` ; sans lui, chaque écran est refusé. |
| `layout` | `base.html.twig` | Le gabarit que les écrans étendent. |
| `defaults.tools` | tous les outils | Les outils d'une table qui n'a rien choisi ; un nom inconnu est refusé. |
| `defaults.named_signals` | `false` | Si un signal dit qui l'a levé. |
| `defaults.signals_seen_by` | `table` | Qui voit la carte X et Script Change. |
| `defaults.template` | `campaign` | Le gabarit intégré de la séance zéro. |
| `mercure.hub` | aucun | Le hub où chaque signal est diffusé ; sans lui, les écrans montrent les signaux au chargement. |
| `mercure.hub_name` | aucun | Le hub du témoin d'autorisation. |
| `mercure.topic_prefix` | `urn:tilttek:table-safety:` | Le préfixe des sujets. |
| `retention` | `30 days` | Un nombre et une unité — minutes, heures, jours, semaines ou mois. |
| `feedback_threshold` | `3` | Le nombre de participants dont un sommaire attend les étoiles et les souhaits, et qu'il rassemble par groupes entiers ; au moins 1. Une table ne le choisit jamais. |
| `kit_subdirectory` | aucun | Le sous-répertoire des recettes du kit sous le répertoire des composants. |
| `privacy.participant_kind` | `member` | Le genre de la référence de participant d'une personne concernée. |

Le catalogue de thèmes est le service `Catalogue` de `phil/content-warnings-bundle`, que l'application complète ou
remplace dans sa propre configuration ; sans ce bundle, la liste de base de `phil/content-warnings`.
