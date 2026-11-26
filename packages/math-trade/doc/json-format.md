# The JSON format

`TradeListJson` writes a `TradeList` as a JSON document and reads it back. The format is named `tilttek/math-trade`,
version 1.

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

## The document

| Key | Type | Content |
|---|---|---|
| `format` | string | `tilttek/math-trade` |
| `version` | integer | `1` |
| `participants` | array | The participants, each an object; it may be empty |

## A participant

| Key | Type | Content |
|---|---|---|
| `id` | string | The identifier of the participant, unique in the document |
| `offers` | array | Their offers, one at least, each an object |

## An offer

| Key | Type | Content |
|---|---|---|
| `id` | string | The identifier of the offer, unique in the document |
| `game` | string or null, optional | The game the copy is, shared by its other copies; left out when the offer has none |
| `wants` | array | The wishes, most wished first; it may be empty |

A wish is the identifier of an offer, or an array of two identifiers or more for a group: any one of them, at one
priority.

## Reading

```php
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\TradeListJson;

$list = new TradeListJson()->decode($json);
$list = new TradeListJson()->decode($json, new Limits(offers: 3_000, jsonBytes: 2_097_152));
```

The reader refuses, with an `InvalidTradeList`:

- a document that is not JSON, or nests deeper than the format does;
- a value of another type than the tables say, such as a number for an identifier;
- a key the format does not have, and a key it requires that is missing;
- another format or another version, and a version written as a string;
- every refusal of a trade list: see [Running a math trade](math-trade.md#what-a-list-refuses).

It refuses a document longer than `Limits::$jsonBytes`, 8 MiB by default, with a `LimitExceeded`, before decoding it,
and every list larger than its limits once decoded.

## Writing

```php
$json = new TradeListJson()->encode($list);
```

The writer keeps the order of the participants, of their offers and of their wishes, leaves `game` out when an offer
has none, and writes the text as it is, slashes and accents unescaped, indented by four spaces. A document read and
written again is the same document.
