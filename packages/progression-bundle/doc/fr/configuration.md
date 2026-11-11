<!-- phil-dev source-sha256=1cbf88bf5294a7f5eb32457a1569b0f5ed6ffe281db71fdffc7948a43aa5def7 -->
# Configuration

```yaml
tilttek_progression:
    store: doctrine            # or memory, for tests
    connection: default
    timezone: America/Toronto  # caps, calendar seasons, and a member's day when nothing says theirs
    tracks:
        player:
            curve: { type: quadratic, base: 50, levels: 60 }
            subject: member    # or object: the gesture's object, such as a character
            seasons: { length: quarter }
            rules:
                games: { verbs: ['game.played'], experience: 20, cap: { amount: 100, period: day } }
                wins: { verbs: ['game.won'], experience: 5, per_quantity: false, context: { ranked: true } }
            milestones:
                veteran: { level: 10 }
                thousand: { experience: 1000 }
        summer-event:
            curve: { type: table, thresholds: [100, 300, 600] }
            seasons:
                named:
                    - { code: summer-2026, start: '2026-06-21', end: '2026-09-22' }
            rules: { games: { verbs: ['game.*'], experience: 10 } }
    streaks:
        daily-play: { verbs: ['game.played'], unit: day, grace: { allowed: 1, window: 7 } }
        weekly-writing: { verbs: ['page.written'], unit: week }
    timezone_context: timezone # the context key that carries the member's timezone; null for the configured one
    projection: progression
    translation_domain: tilttek_progression
    privacy:
        member_kind: member
```

| Option | Par défaut | Signification |
|---|---|---|
| `store` | `doctrine` | `doctrine` ou `memory` |
| `connection` | `default` | La connexion DBAL des cinq tables |
| `timezone` | `UTC` | Le fuseau horaire des plafonds, des saisons du calendrier et des journées des membres |
| `tracks.<name>.curve` | requis | `type` (`linear`, `quadratic`, `exponential`, `table`), `step`, `base`, `factor`, `levels` (2 à 1000, 100 par défaut), `thresholds` |
| `tracks.<name>.subject` | `member` | `member` ou `object` |
| `tracks.<name>.seasons` | aucune | `length` (`month`, `quarter`, `year`) ou `named` (une liste de `code`, `start`, `end`) |
| `tracks.<name>.rules.<name>` | — | `verbs` (requis), `experience` (requis, au moins 1), `per_quantity` (true), `cap` (`amount`, `period` : `day`, `week`, `month`, `year`, `ever`), `context` |
| `tracks.<name>.milestones.<code>` | — | `experience` (au moins 1) ou `level` (au moins 2) |
| `streaks.<name>` | — | `verbs` (requis), `unit` (`day` ou `week`), `grace` (`allowed` au moins 1, `window` au moins 2), `context` |
| `timezone_context` | null | La clé du contexte de l'activité qui contient le fuseau horaire du membre |
| `projection` | `progression` | Le nom sous lequel le point de reprise de la projection est gardé |
| `translation_domain` | `tilttek_progression` | Le domaine des textes des gabarits |
| `privacy.member_kind` | `member` | Le type de la référence de membre d'une personne concernée |

Les noms des pistes, des règles, des jalons et des séries sont gardés tels qu'écrits (`daily-play` reste
`daily-play`) ; les noms de contexte suivent la normalisation de Symfony, `the-role` devenant `the_role`. Une courbe à
qui il manque son palier, sa base, son facteur ou ses seuils, un niveau de jalon au-delà de la courbe, une grâce qui ne
couvre aucun trou, un fuseau horaire qui n'existe pas échouent à la compilation du conteneur, avec l'`InvalidProgression`
de la bibliothèque. Une valeur du mauvais genre ou hors limites échoue avec l'`InvalidConfigurationException` de
Symfony.

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Les magasins en mémoire,
pour les tests, se vident quand le noyau est réinitialisé, et la projection oublie ce que sa dernière reconstruction a
gardé pour le rejeu.
