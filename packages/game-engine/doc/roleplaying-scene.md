# The role-playing scene

`Scene\RoleplayingScene` is a ready game for the phases of a role-playing scene, with no rules of any system: it
tracks who acts, not what the actions do.

| Phase | Turn order | Transitions out |
|---|---|---|
| `exploration`, the first | Free | `start-encounter` |
| `encounter` | Initiative | `end-encounter` to `aftermath`, `explore` |
| `aftermath` | Free | `start-encounter`, `explore` |

| Move | Payload | When |
|---|---|---|
| `scene.act` | `{summary}`, optional | An action, in the seat's turn during an encounter, at any time otherwise |
| `scene.note` | `{text}`, required | A note, at any time |
| `scene.end` | — | Ends the scene, without winners |

A table seats 1 to 32. The first seat leads the scene: it alone changes the phase, sets the initiative and ends the
scene, unless the table's option `leader` is turned off. Every action may be taken back, since none draws at random.
The data counts the actions of each seat, the notes and the encounters; every seat sees it all. When the scene ends,
its outcome reports them as statistics: `encounters` and `notes` of the table, `actions` of each seat.

```php
$tables->open('roleplaying-scene', 'scene:12', ['seat:gm', 'seat:ann', 'seat:bob']);
$tables->play('scene:12', Move::initiative(['seat:ann' => 17, 'seat:bob' => 11, 'seat:gm' => 8], 'seat:gm'));
$tables->play('scene:12', Move::transition('start-encounter', 'seat:gm'));
$tables->play('scene:12', new Move('scene.act', 'seat:ann', ['summary' => 'Charges the gate.']));
$tables->play('scene:12', Move::endTurn('seat:ann'));
$tables->play('scene:12', Move::delay('seat:bob', 'seat:gm'));
```

The rolls of a scene are `tilttek/dice`'s, kept by its own journal: the scene records who acts and in which phase, and
a system's rules live with the tools that read `tilttek/rpg-systems`.
