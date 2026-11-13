# The rule of tincture

Metal is never laid on metal, nor colour on colour. A fur lies on either, and nothing lies on its own tincture.

```php
use Tilttek\Heraldry\TinctureRule;

$rule = new TinctureRule();
$rule->respects($coat);                // true or false
foreach ($rule->violations($coat) as $violation) {
    $violation->describe();            // "The ordinary lies or on argent: metal on metal."
}
```

## Where the rule binds

| What | Lies on |
|---|---|
| The ordinary, a bordure included | a plain field |
| Charges on the field, beside an ordinary or alone | a plain field |
| Charges on the ordinary | the ordinary |

## Where it does not

- Whatever lies on a **divided field** touches both of its tinctures: a metal and a colour field takes anything.
- A **mark of difference** lies over everything and is not checked.

`Violation` names the layer (`piece` or `charges`), its tincture and the ground it lies on. `Tincture::contrastsWith()`
is the rule for one pair.
