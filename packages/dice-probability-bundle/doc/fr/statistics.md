<!-- phil-dev source-sha256=18ebf9d1f6e131f1f6497906b6759c3aae59c30bda17fd8976face518f8793aa -->
# Statistiques en PHP

`Tilttek\DiceProbabilityBundle\Application\DiceStatistics` prend des expressions en texte et les lit avec le
`DiceRoller` de `TilttekDiceBundle` :

| Méthode | Ce qu'elle donne |
|---|---|
| `distribution($expression, $locale = null, $rounding = null)` | La `Distribution` d'une expression |
| `compare($first, $second, $locale = null, $rounding = null)` | Le `Versus` de deux expressions |
| `chance($event, $dice, $locale = null)` | La `Chance` d'un événement sur un ensemble de dés |
| `canonical($expression, $locale = null)` | La forme canonique, ou l'expression telle qu'écrite quand elle ne peut pas être lue |

La locale nomme la notation dans laquelle l'expression est écrite : `'de'` lit `2W6`. L'arrondi est un `Rounding` ;
s'il est null, c'est celui de la configuration. Une expression invalide lève l'`InvalidExpression` de `tilttek/dice`,
une limite dépassée son `LimitExceeded` (`max_dice` compris), une expression sans distribution le `CannotCompute` de
`tilttek/dice-probability`, des groupes trop longs à chercher le `Application\Exception\GroupsTooCostly` du bundle, avant que la bibliothèque
lance quoi que ce soit, et une distribution de plus de valeurs que `max_outcomes` le
`Application\Exception\TooManyOutcomes` du bundle. Les deux implémentent `Application\Exception\Refusal` : en plus de
leur message, la clé et les paramètres de leur raison dans le domaine de traduction `tilttek_dice_probability`.

```php
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;

final readonly class Odds
{
    public function __construct(private DiceStatistics $statistics)
    {
    }

    public function oneOrDouble(int $dice): string
    {
        return (string) $this->statistics->chance(new AnyOf(Count::atLeastOne(1), OfAKind::double()), $dice.'d6')->probability;
    }
}
```

`Tilttek\DiceProbability\Probabilities` est aussi un service, sur les mêmes dés personnalisés et les mêmes limites,
pour les expressions déjà analysées. Il ne pèse pas la recherche des groupes : une application qui lui donne des
expressions saisies par n'importe qui les vérifie d'abord avec `Application\GroupSearch`, comme le fait
`DiceStatistics`.

`GroupSearch` pèse les groupes d'une expression analysée : `work($expressions)` donne les étapes d'un échantillon, et
`check($pool, $expression)` lève `GroupsTooCostly` quand ces étapes, multipliées par les échantillons, dépassent sa
limite de travail. La [configuration](configuration.md#la-recherche-des-groupes) explique comment un ensemble est pesé.

`Rounding` nomme les modes d'arrondi de la configuration et des gabarits : `Floor`, `Ceil`, `Round`, `TowardsZero`,
`AwayFromZero` ; `mode()` donne le `RoundingMode` de PHP.
