# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Templates

Override the template as for any bundle, at `templates/bundles/TilttekAccessibleDiceBundle/dice.html.twig`. An override
keeps the fieldset and its legend, the labels of the fields and of the buttons, the descriptions of the faces, the
live region and the values and targets of the controller.

## Styles

The stylesheet, `assets/styles/accessible-dice.css`, sets the size of the targets through
`--tilttek-accessible-dice-target`, 3rem by default, and the colour of a face through
`--tilttek-accessible-dice-face-color`. Disable it in the application's `controllers.json` to style the classes
yourself:

| Class | Element |
|---|---|
| `tilttek-accessible-dice` | The form; `is-rolling` while it is sent, without a request for reduced motion |
| `tilttek-accessible-dice__dice`, `tilttek-accessible-dice__die` | The dice, a die |
| `tilttek-accessible-dice__faces`, `tilttek-accessible-dice__face`, `tilttek-accessible-dice__shape--<shape>` | The faces of a custom die, a face, its shape |
| `tilttek-accessible-dice__number` | How many, the modifier |
| `tilttek-accessible-dice__expression`, `tilttek-accessible-dice__speak`, `tilttek-accessible-dice__roll` | The expression, the box, the roll |
| `tilttek-accessible-dice__result` | The live region |

## Translations

The words are in the `tilttek_accessible_dice` domain, in English and French; add a file
`translations/tilttek_accessible_dice.<locale>.yaml` for another language, with the keys of the bundle's files. The
labels of the faces and the names of the custom dice go through `dice_translation_domain`.

## Other dice on the page

The controller announces any `tilttek--dice-tower--tower:drop` event that reaches the window. Another source of rolls
dispatches the same event, with a `view` — `{name, results: [{expression, total, text, dice: [{label, kept}]}]}` —, an
`entry` as the dice tower pushes it, or a `text`:

```js
window.dispatchEvent(new CustomEvent('tilttek--dice-tower--tower:drop', { detail: { text: 'Brom rolled 17.' } }));
```
