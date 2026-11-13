<!-- phil-dev source-sha256=8e8ad9370df8192c621f28782200fb795b8fb59388d37c5856caed8546d7f0f2 -->
# Définitions

`SeasonReader` lit des saisons depuis des tableaux simples — une configuration PHP, un fichier JSON ou YAML — et les
réécrit :

```php
[
    'id' => 'winter-festival',
    'timezone' => 'America/Toronto',
    'yearly' => ['from' => '2026-12-15', 'days' => 23],     // or 'once' => ['from' => '2027-03-20', 'through' => '2027-04-03']
    'quests' => ['quest:winter-hunt'],
    'items' => ['item:snow-hat'],
    'decorations' => ['decoration:snowflakes'],
    'events' => [
        ['id' => 'gift-week', 'from_day' => 8, 'days' => 7, 'announce_days' => 7,
         'items' => ['item:wrapped-die'], 'multiplier' => ['ledger' => 'points', 'percent' => 200]],
    ],
]
```

| Clé | Contient |
|---|---|
| `id`, `timezone` | L'identifiant et le fuseau horaire de la saison, obligatoires. |
| `once` | `from` et `through`, les deux jours inclus. |
| `yearly` | `from`, `days` et `rule`, `FREQ=YEARLY` par défaut. |
| `quests`, `items`, `decorations` | Des références, `kind:identifier`. |
| `events` | Une liste d'événements : `id`, `from_day` (1 par défaut), `days` (jusqu'à la fin de la saison si omis), `announce_days` (0 par défaut), les trois listes de contenus, et `multiplier` avec `ledger` et `percent`. |

Une saison vient `once` ou `yearly`, l'un des deux. Une clé que le lecteur ne connaît pas est refusée, afin qu'une clé
mal orthographiée ne passe pas pour une clé absente. `SeasonReader::all()` lit une liste d'au plus 100 saisons ;
`season()` en lit une ; `write()` redonne le tableau que `season()` lit. Chaque refus lance `InvalidSeason` avec la
raison.

`resources/sample-seasons.php` contient des exemples de saisons d'une communauté de jeux — un festival d'hiver, une
saison de l'épouvante, une fête des récoltes le quatrième jeudi de novembre, un grand ménage de printemps qui ne vient
qu'une fois — pour une démonstration et la première configuration d'un site. Chaque référence est un exemple que le
site remplace par les siennes.
