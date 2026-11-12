# The form

```twig
{{ accessible_dice(path('table_roll', {table: table.id}), token: {name: '_token', value: csrf_token('roll')}, result: result, name: app.user.displayName) }}
```

`accessible_dice(action, field, token, result, name, politeness, id, locale)` draws the form:

| Argument | Default | What it sets |
|---|---|---|
| `action` | | Where the form is sent, by `POST` |
| `field` | `dice` | The name the choice is sent under |
| `token` | `null` | A hidden field the application checks, such as its CSRF token: `{name, value}` |
| `result` | `null` | The roll the form was answered with, a `PoolResult`, announced when the page loads |
| `name` | `null` | The roller's name, said before that roll |
| `politeness` | the configuration | `polite` or `assertive` |
| `id` | `tilttek-accessible-dice` | What the identifiers of the form start with: distinct for each form of a page |
| `locale` | the request's | The language of the words and the labels |

## The choice sent

The form sends the choice, not an expression: `dice[die]` — `d6`, or the name of a custom die —, `dice[count]` and
`dice[modifier]`. The server builds the expression from it, so that the form rolls the same with or without its
script, and nothing else can be rolled through it:

```php
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\Exception\InvalidChoice;
use Tilttek\DiceBundle\Application\DiceRolls;

public function roll(Request $request, AccessibleDice $dice, DiceRolls $rolls): Response
{
    try {
        $result = $rolls->roll($dice->expression($request->request->all('dice')));
    } catch (InvalidChoice) {
        throw new BadRequestHttpException();
    }

    return $this->render('roll.html.twig', ['result' => $result]);
}
```

`expression()` accepts a die the configuration offers, a count from 1 to the dice limit and a modifier from -1000 to
1000, as whole numbers or their text, and raises `InvalidChoice` for anything else. With a dice tower, roll it in the
table's tower instead: `$tower->roll($table, $dice->expression(...), $member)`.

## Announcing the result

The sentence names every die — a face of a custom die by its label —, says a die that did not count, then the total
or what the roll gave: `Aelis rolled. 2d6+1: 4, 3. Total 8.` The form announces the `result` it is given;
`accessible_dice_announcement(result, name)` gives the same sentence elsewhere, and `RollAnnouncer` in PHP.

## The faces of a custom die

```twig
{% for face in accessible_dice_faces('coin') %}
    <p>{{ face.description }}</p> {# Heads: a circle, worth 1 #}
{% endfor %}
```

## With a dice tower

The controller listens to `tilttek--dice-tower--tower:drop` on the window: each roll of a dice tower on the same page
is announced in the form's live region, its faces named by the labels of the form, its roller by the name the tower's
page gives. Draw the tower with `announce: false`, so that each roll is announced once:

```twig
{{ dice_tower(table, names, announce: false) }}
{{ accessible_dice(path('table_roll', {table: table.id})) }}
```
