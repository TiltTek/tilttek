# Templates

| Twig | Gives |
|---|---|
| `expression\|dice_notation(locale)` | The expression in the notation of a language; an invalid one as it is |
| `expression\|dice_dialect('roll20')` | The commands of a table's dialect, or null when the dialect cannot write it |
| `dice_roll(expression, locale)` | A `PoolResult` |
| `dice_roll_detail(result, member, locale)` | The detail of a roll, as HTML |
| `dice_text(message)` | A word of the detail, through the translator |

## The detail of a roll

`dice_roll_detail()` renders `@TilttekDice/roll_detail.html.twig`: for each instruction, its notation, its total in an
`<output>`, the text or the list it gave in an `<output class="tilttek-dice-text">` — each text translated in the
configured domain, as a translation key, and escaped as any text —, the faces counted for custom dice — `2 × Star` —,
and each set of dice as a list with every die: the sets inside functions, branches and groups, the dice of a count or
sides rolled first, lists of values and the dice judged again by `@` included.

Each die shows its face as text: the number, or the label of a custom face. A face with a shape shows the shape
(`tilttek-dice-shape tilttek-dice-shape-star`, hidden from screen readers) next to its label, and its colour in the CSS
variable `--tilttek-dice-face-color`: never the colour alone. The states of a die are words, in parentheses, as well as
classes:

| Class | Word |
|---|---|
| `is-dropped` | dropped |
| `is-rerolled` | rerolled |
| `is-exploded` | exploded |
| `is-success`, `is-failure` | success, failure |
| `is-from-reroll`, `is-from-explosion` | — |

With a member, each set carries the appearance of their dice:

| Hook | Holds |
|---|---|
| `data-dice-appearance` | The name of the set |
| `data-dice-material` | The material, when there is one |
| `data-dice-texture` | The texture key, when there is one: the application's stylesheet maps it to a picture |
| `--tilttek-dice-color` | The colour of the dice, when there is one |
| `--tilttek-dice-ink` | The colour of the numbers: the ink of the appearance, or, on a coloured die without one, black or white, whichever contrasts more with the colour — at least 4.5:1 |

```css
.tilttek-dice-set[data-dice-appearance] .tilttek-dice-die {
    background: var(--tilttek-dice-color, Canvas);
    color: var(--tilttek-dice-ink, CanvasText);
}
.tilttek-dice-set[data-dice-texture="dice/marble"] .tilttek-dice-die { background-image: url(/images/dice/marble.webp); }
```

The bundle ships no stylesheet; the classes are hooks for the application's. A texture drawn under the numbers keeps
them readable only when its colours stay close to `--tilttek-dice-color`.

The words — `dropped`, `rerolled`, `exploded`, `success`, `failure` — and the faces' labels are translated in the
configured domain when the translator is installed; untranslated, they show as written.

The template escapes its texts for HTML itself, inside an `autoescape 'html'` block, even where the application turns
autoescaping off: `dice_roll_detail()` says its output is safe HTML, and the texts of a roll come from what members
type. Override the template as any bundle's, in `templates/bundles/TilttekDiceBundle/roll_detail.html.twig`, and keep
that block.
