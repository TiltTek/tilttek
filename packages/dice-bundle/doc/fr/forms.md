<!-- phil-dev source-sha256=eafcf5216d56c2dd47add126a04e0c24e362826c62b1a819b48bef8e0db1ef99 -->
# Formulaires et validation

## Le champ

```php
use Tilttek\DiceBundle\Infrastructure\Form\DiceExpressionType;

$builder->add('expression', DiceExpressionType::class, ['locale' => $request->getLocale()]);
```

`DiceExpressionType` est un champ texte dont la donnée est le texte tel que saisi. La contrainte `DiceExpression` le
vérifie dans la notation de `locale`, ou dans la notation canonique si elle est nulle. Le correcteur orthographique est
désactivé.

## La contrainte

```php
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpression;

#[DiceExpression(locale: 'de')]
public string $expression;
```

La valeur passe quand elle s'analyse dans les limites ; `null` et la chaîne vide passent, comme avec toute contrainte
sauf `NotBlank`. Le message de la violation est `This is not a dice expression: {{ reason }}`, la raison étant le
message de la bibliothèque avec la position de l'erreur ; son code est `DiceExpression::INVALID`. `message` remplace le
texte.
