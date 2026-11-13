<!-- phil-dev source-sha256=2bdb0cf541980d7f493834416f193d3e344dae798be882e92d952f02b96504b3 -->
# La scène de jeu de rôle

`Scene\RoleplayingScene` est un jeu prêt à l'emploi pour les phases d'une scène de jeu de rôle, sans les règles
d'aucun système : il suit qui agit, pas ce que font les actions.

| Phase | Ordre de tour | Transitions de sortie |
|---|---|---|
| `exploration`, la première | Free | `start-encounter` |
| `encounter` | Initiative | `end-encounter` vers `aftermath`, `explore` |
| `aftermath` | Free | `start-encounter`, `explore` |

| Coup | Charge utile | Quand |
|---|---|---|
| `scene.act` | `{summary}`, facultatif | Une action, au tour du siège pendant une rencontre, à tout moment autrement |
| `scene.note` | `{text}`, obligatoire | Une note, à tout moment |
| `scene.end` | — | Termine la scène, sans gagnants |

Une table compte de 1 à 32 sièges. Le premier siège mène la scène : lui seul change la phase, fixe l'initiative et
termine la scène, sauf si l'option `leader` de la table est désactivée. Chaque action peut être reprise, puisqu'aucune
ne tire au hasard. Les données comptent les actions de chaque siège, les notes et les rencontres ; chaque siège voit
tout. Quand la scène se termine, son issue les rapporte comme statistiques : `encounters` et `notes` de la table,
`actions` de chaque siège.

```php
$tables->open('roleplaying-scene', 'scene:12', ['seat:gm', 'seat:ann', 'seat:bob']);
$tables->play('scene:12', Move::initiative(['seat:ann' => 17, 'seat:bob' => 11, 'seat:gm' => 8], 'seat:gm'));
$tables->play('scene:12', Move::transition('start-encounter', 'seat:gm'));
$tables->play('scene:12', new Move('scene.act', 'seat:ann', ['summary' => 'Charges the gate.']));
$tables->play('scene:12', Move::endTurn('seat:ann'));
$tables->play('scene:12', Move::delay('seat:bob', 'seat:gm'));
```

Les jets d'une scène sont ceux de `tilttek/dice`, conservés par son propre journal : la scène enregistre qui agit et
dans quelle phase, et les règles d'un système vivent avec les outils qui lisent `tilttek/rpg-systems`.
