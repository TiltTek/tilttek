<!-- phil-dev source-sha256=c13abcc8b1bf562d484abb8236e73fbe3fc607f570af7b996c2186c89987bb23 -->
# Effacement

## L'exportation

`Leaderboards::export($member)` retourne ce que les classements contiennent au sujet d'un membre :

```php
[
    'appearance' => ['visibility' => 'pseudonym', 'pseudonym' => 'Le Nain Gris'],
    'scores' => [['board' => 'games-month', 'period' => '2026-11', 'value' => 7, 'reached_at' => '2026-11-03T10:00:00+00:00']],
    'placings' => [['board' => 'games-month', 'period' => '2026-10', 'rank' => 2, 'value' => 7, 'reached_at' => '2026-10-02T10:00:00+00:00']],
    'leagues' => [
        'tiers' => ['weekly-xp' => 'silver'],
        'memberships' => [['board' => 'weekly-xp', 'week' => '2026-w45', 'tier' => 'bronze', 'division' => 3, 'joined_at' => '2026-11-03T10:00:00+00:00', 'movement' => 'promoted']],
    ],
]
```

## L'effacement

`Leaderboards::erase($member)` retire les contributions et les scores du membre, ses lignes dans chaque instantané, son
choix d'apparence, et ses paliers et ses appartenances dans les ligues, puis retourne un `Erasure` qui les compte. Les
autres membres gardent leurs rangs dans les instantanés et leurs divisions ; les classements en cours remontent, puisque
le membre n'a plus de score.

`leagues` est vide quand `Leaderboards` ne reçoit aucun `Leagues`.

Le journal d'activité est effacé par `tilttek/activity` : une reconstruction après un effacement ne trouve plus rien du
membre.

Deux choses restent à l'application. Les instantanés et les règlements déjà remis à un observateur (`snapshotTaken`,
`leagueSettled`) gardent la référence du membre là où l'application les a gardés. Et une projection ou un
`settlePeriod()` qui tourne pendant l'effacement peut réécrire un score, un palier ou une appartenance : effacez une fois
l'activité du membre effacée et aucun règlement en cours, ou effacez de nouveau après.
