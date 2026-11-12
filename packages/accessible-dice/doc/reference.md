# Reference

| Type | Role |
|---|---|
| `TilttekAccessibleDiceBundle` | The bundle: its configuration and its services |
| `Application\AccessibleDice` | The dice offered and the expression of a choice |
| `Application\DieChoice` | A die offered: its name, its notation, its label and its faces |
| `Application\FaceDescriber` | The descriptions of the faces of the custom dice, and their labels |
| `Application\FaceDescription` | A face in words: its label, its description, its shape and its value |
| `Application\RollAnnouncer` | A roll in the sentence a screen reader announces |
| `Application\Words` | The words, through the translator or in English |
| `Application\Politeness` | `polite` or `assertive` |
| `Application\Exception\InvalidChoice` | A choice the form does not offer |
| `Infrastructure\Twig\AccessibleDiceExtension` | `accessible_dice()`, `accessible_dice_announcement()` and `accessible_dice_faces()` |
| `Infrastructure\DependencyInjection\AccessibleDiceServices` | Registers the services |

| Service | Public |
|---|---|
| `Tilttek\AccessibleDice\Application\Words` | no |
| `Tilttek\AccessibleDice\Application\FaceDescriber` | yes |
| `Tilttek\AccessibleDice\Application\RollAnnouncer` | yes |
| `Tilttek\AccessibleDice\Application\AccessibleDice` | yes |
| `Tilttek\AccessibleDice\Infrastructure\Twig\AccessibleDiceExtension` | yes, with Twig |

| Assets | What they are |
|---|---|
| `assets/src/dice_controller.js` | The controller `tilttek--accessible-dice--dice` |
| `assets/src/lib/dice.js` | The expression of a choice and the sentences of the rolls |
| `assets/styles/accessible-dice.css` | The stylesheet: the targets, the focus, the shapes and the motion |
