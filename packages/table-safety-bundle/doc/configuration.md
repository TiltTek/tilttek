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

| Option | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | Where the settings, answers and signals are kept. |
| `connection` | `default` | The Doctrine DBAL connection of the stores. |
| `table_access` | none | The service implementing `TableAccess`; every screen is refused without it. |
| `layout` | `base.html.twig` | The template the screens extend. |
| `defaults.tools` | every tool | The tools of a table that chose nothing; an unknown name is refused. |
| `defaults.named_signals` | `false` | Whether a signal says who raised it. |
| `defaults.signals_seen_by` | `table` | Who sees the X-card and Script Change. |
| `defaults.template` | `campaign` | The built-in template of the session zero. |
| `mercure.hub` | none | The hub each signal is pushed to; without it, the screens show the signals when they load. |
| `mercure.hub_name` | none | The hub of the authorization cookie. |
| `mercure.topic_prefix` | `urn:tilttek:table-safety:` | The prefix of the topics. |
| `retention` | `30 days` | A number and a unit — minutes, hours, days, weeks or months. |
| `feedback_threshold` | `3` | How many participants' stars and wishes a summary waits for, and gathers in whole groups of; at least 1. A table never chooses it. |
| `kit_subdirectory` | none | The subdirectory of the kit's recipes under the components directory. |
| `privacy.participant_kind` | `member` | The kind of the participant reference of a data subject. |

The catalogue of themes is the `Catalogue` service of `phil/content-warnings-bundle`, which the application completes
or replaces in its own configuration; without that bundle, the base list of `phil/content-warnings`.
