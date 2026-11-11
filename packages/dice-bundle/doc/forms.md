# Forms and validation

## The field

```php
use Tilttek\DiceBundle\Infrastructure\Form\DiceExpressionType;

$builder->add('expression', DiceExpressionType::class, ['locale' => $request->getLocale()]);
```

`DiceExpressionType` is a text field whose data is the text as typed, checked by the `DiceExpression` constraint in the
notation of `locale` — the canonical one when it is null —, with the spelling checker off.

## The constraint

```php
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpression;

#[DiceExpression(locale: 'de')]
public string $expression;
```

The value passes when it parses within the limits; `null` and the empty string pass, as with every constraint but
`NotBlank`. The violation's message is `This is not a dice expression: {{ reason }}`, the reason being the library's
message with the position of the error; its code is `DiceExpression::INVALID`. `message` replaces the text.
