<!-- phil-dev source-sha256=396ab0b204d79f0f8411eba70fd269518e7e868e6f63e37ec9ac6f8f9ed07dc4 -->
# Dés personnalisés

Un dé personnalisé est un dé dont les faces sont des symboles plutôt que des nombres. L'application le déclare sous
forme de données ; la bibliothèque n'en fournit aucun, car les dés d'un éditeur sont des données de son site.

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

Une `Face` a un identifiant (de 1 à 32 lettres minuscules, chiffres, `_` ou `-`), un libellé — une clé de traduction ou
un texte, les mots que prononce un lecteur d'écran —, et une façon d'être affichée : une couleur écrite `#rrggbb`, une
`Shape`, une image, et une valeur quand la face compte.

**Une face ne se reconnaît jamais à sa seule couleur.** Une face qui a une couleur a aussi une forme ou une image, pour
qu'un dé de couleurs se lise sans voir les couleurs, comme un dé gravé se lit au toucher. Le libellé est obligatoire,
pour que chaque face ait des mots.

Une `DieDefinition` a un nom (de 1 à 32 lettres minuscules, chiffres, `_` ou `-`, en commençant par une lettre) et au
moins deux faces aux identifiants distincts, qui portent toutes une valeur ou n'en portent aucune.

## Lancer des dés personnalisés

Chaque dé tombe sur une face. `DiceResult::tally()` compte les faces gardées par identifiant, dans l'ordre où elles
sont tombées pour la première fois — deux étoiles et un cercle. Quand les faces portent des valeurs, les dés sont
numériques : ils s'additionnent et peuvent prendre part à l'arithmétique (`2d{coin}+1`). Quand elles n'en portent pas,
le total de l'expression est null et l'arithmétique avec eux est refusée. Les dés personnalisés n'acceptent aucun
modificateur.
