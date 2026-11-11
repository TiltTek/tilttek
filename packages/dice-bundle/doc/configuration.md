# Configuration

```yaml
tilttek_dice:
    limits:
        dice: 100          # dice a pool rolls, lists or draws
        sides: 1000        # sides of a die
        explosions: 100    # explosions of a roll
        expressions: 10    # instructions of a pool
        length: 256        # characters of an input
        rerolls: 100       # rerolls of a roll, repeated, once or unique
        repetitions: 100   # repetitions of repeat()
        text: 4096         # characters of a text a sentence gives
    custom_dice:
        chromatic:
            faces:
                - { id: red, label: 'dice.red', color: '#d32f2f', shape: circle }
                - { id: star, label: 'dice.star', image: '/images/star.svg' }
    notations:
        fi: { die: n }                     # advantage: adv, disadvantage: dis by default
    appearance: null                       # a service implementing DiceAppearance
    translation_domain: tilttek_dice
    store: doctrine                        # or memory
    entity_manager: default
```

| Option | Default | Meaning |
|---|---|---|
| `limits.*` | as above | Each at least 1; an expression or a roll past one is refused |
| `custom_dice` | none | The application's dice, by name; each face has an `id` and a `label`, and a `value`, a `color`, a `shape` or an `image` |
| `notations` | none | The letters of more languages, by locale, added to the built-in German, Danish, Norwegian and Swedish |
| `appearance` | `null` | The service that dresses a member's dice; plain dice when null |
| `translation_domain` | `tilttek_dice` | The domain of the words of a roll and of the faces' labels |
| `store` | `doctrine` | Where the journal of replayable rolls and the macros are kept: `doctrine` or `memory` |
| `entity_manager` | `default` | The entity manager of the journal and the macros |

A shape is one of `circle`, `square`, `triangle`, `diamond`, `pentagon`, `hexagon`, `star`, `cross`, `heart`,
`crescent` and `blank`. The custom dice and the notations are built when the container is compiled: a face told by its
colour alone, two faces with one identifier or a die letter taken by a modifier fails then, with the library's message.

The services keep no state between requests: they are safe in a worker. The journal and the macros in memory empty
themselves when the kernel resets.
