# Custom dice

A custom die is a die whose faces are symbols rather than numbers. The application declares it in data; the library
ships none, since a publisher's dice are its site's data.

```php
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\DiceRoller;

$chromatic = new DieDefinition('chromatic',
    new Face('red', 'dice.red', color: '#d32f2f', shape: Shape::Circle),
    new Face('blue', 'dice.blue', color: '#1976d2', shape: Shape::Square),
    new Face('star', 'dice.star', shape: Shape::Star),
);
$dice = new DiceRoller(customDice: new CustomDice($chromatic));
$dice->parse('3d{chromatic}');
```

## Faces

A `Face` has an identifier (1 to 32 lower-case letters, digits, `_` or `-`), a label — a translation key or a text, the
words a screen reader says —, and how it is shown: a colour written `#rrggbb`, a `Shape`, an image, and a value when the
face counts.

**A face is never told by its colour alone.** A face with a colour also has a shape or an image, so that a die of
colours reads without seeing the colours, as an engraved die reads by touch. The label is required, so that every face
has words.

A `DieDefinition` has a name (1 to 32 lower-case letters, digits, `_` or `-`, starting with a letter) and at least two
faces with distinct identifiers, which either all carry a value or none does.

## Rolling custom dice

Each die falls on one face. `DiceResult::tally()` counts the kept faces by identifier, in the order they first fell —
two stars and a circle. When the faces carry values, the dice are numeric: they sum, and they may take part in
arithmetic (`2d{coin}+1`). When they do not, the expression's total is null and arithmetic with them is refused. Custom
dice take no modifier.
