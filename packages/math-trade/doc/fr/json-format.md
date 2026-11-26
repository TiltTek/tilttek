<!-- phil-dev source-sha256=542b0c82928df1ab76362233083193ae70ef523ac549b74ef9291bb86a671644 -->
# Le format JSON

`TradeListJson` écrit une `TradeList` sous forme de document JSON et la relit. Le format s'appelle
`tilttek/math-trade`, version 1.

```json
{
    "format": "tilttek/math-trade",
    "version": 1,
    "participants": [
        {
            "id": "alice",
            "offers": [
                {
                    "id": "alice-azul",
                    "game": "azul",
                    "wants": [
                        "bob-catan",
                        ["carol-wingspan", "dave-wingspan"]
                    ]
                }
            ]
        },
        {
            "id": "bob",
            "offers": [
                {
                    "id": "bob-catan",
                    "wants": ["alice-azul"]
                }
            ]
        }
    ]
}
```

## Le document

| Clé | Type | Contenu |
|---|---|---|
| `format` | chaîne | `tilttek/math-trade` |
| `version` | entier | `1` |
| `participants` | tableau | Les participants, chacun un objet ; il peut être vide |

## Un participant

| Clé | Type | Contenu |
|---|---|---|
| `id` | chaîne | L'identifiant du participant, unique dans le document |
| `offers` | tableau | Ses offres, une au moins, chacune un objet |

## Une offre

| Clé | Type | Contenu |
|---|---|---|
| `id` | chaîne | L'identifiant de l'offre, unique dans le document |
| `game` | chaîne ou null, facultatif | Le jeu dont l'exemplaire est la copie, partagé avec ses autres exemplaires ; omis quand l'offre n'en a pas |
| `wants` | tableau | Les souhaits, du plus souhaité au moins souhaité ; il peut être vide |

Un souhait est l'identifiant d'une offre, ou, pour un groupe, un tableau de deux identifiants ou plus : n'importe
lequel d'entre eux, à une seule priorité.

## Lecture

```php
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\TradeListJson;

$list = new TradeListJson()->decode($json);
$list = new TradeListJson()->decode($json, new Limits(offers: 3_000, jsonBytes: 2_097_152));
```

Le lecteur refuse, par une `InvalidTradeList` :

- un document qui n'est pas du JSON, ou qui est imbriqué plus profondément que ne l'est le format ;
- une valeur d'un autre type que ceux des tableaux, comme un nombre pour un identifiant ;
- une clé que le format n'a pas, et une clé qu'il exige et qui manque ;
- un autre format ou une autre version, et une version écrite comme une chaîne ;
- chaque refus d'une liste d'échange : voir [Mener un échange mathématique](math-trade.md#ce-quune-liste-refuse).

Il refuse un document plus long que `Limits::$jsonBytes`, 8 Mio par défaut, par une `LimitExceeded`, avant de le
décoder, et toute liste plus grande que ses limites une fois décodée.

## Écriture

```php
$json = new TradeListJson()->encode($list);
```

L'écrivain garde l'ordre des participants, de leurs offres et de leurs souhaits, omet `game` quand une offre n'en a
pas, et écrit le texte tel quel, barres obliques et accents sans échappement, indenté de quatre espaces. Un document lu
puis écrit de nouveau est le même document.
