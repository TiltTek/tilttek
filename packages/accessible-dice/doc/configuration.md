# Configuration

```yaml
# config/packages/tilttek_accessible_dice.yaml
tilttek_accessible_dice:
    sides: [4, 6, 8, 10, 12, 20, 100]
    custom_dice: ~
    politeness: polite
    dice_translation_domain: tilttek_dice
```

| Key | Default | What it sets |
|---|---|---|
| `sides` | `[4, 6, 8, 10, 12, 20, 100]` | The standard dice offered, by their number of sides, each at least 2 |
| `custom_dice` | `null` | The custom dice of `tilttek_dice` offered, by name; every one when null |
| `politeness` | `polite` | How a screen reader announces a result: `polite`, when it has finished what it is saying, or `assertive`, at once |
| `dice_translation_domain` | `tilttek_dice` | The translation domain of the labels of the faces: that of `tilttek_dice` |

How many dice one choice rolls at most is the `dice` limit of `tilttek_dice`. A custom die named in `custom_dice` that
`tilttek_dice` does not declare fails when the service is built.

The services keep no state between requests: they are safe in a worker.
