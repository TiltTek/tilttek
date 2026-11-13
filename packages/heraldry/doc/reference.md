# Reference

| Type | Role |
|---|---|
| `Coat` | A coat of arms: field, ordinary, charges and mark of difference, checked together |
| `Field` | A plain field, or a field divided between two tinctures |
| `Partition` | How a field is divided: per pale, per fess, per bend, per bend sinister, quarterly, per saltire |
| `Tincture` | The metals, colours and furs, with their kind and their contrast |
| `TinctureKind` | `Metal`, `Colour` or `Fur` |
| `Ordinary` | The honourable ordinaries, with the charges each carries and surrounds |
| `Piece` | An ordinary in its tincture |
| `Charge` | The charges: mullet, crescent, roundel, annulet, lozenge, mascle, billet, fleur-de-lis |
| `ChargeGroup` | One to three charges of a kind and a tincture, on the field or on the ordinary |
| `Arrangement` | `Usual`, `InFess` or `InPale` |
| `Brisure` | A mark of difference in its tincture |
| `BrisureMark` | `Label` or `Bendlet` |
| `ShieldShape` | `Heater`, `French`, `Iberian` or `Lozenge` |
| `Language` | `English` or `French` |
| `TinctureRule` | The rule of tincture, checked on a coat |
| `Violation` | Where a coat breaks the rule, in a sentence |
| `Generator\CoatGenerator` | Arms drawn from a seed, keeping the rule |
| `Generator\Seed` | The 32 bytes arms are drawn from, written in hexadecimal |
| `Blazon\Blazonry` | The blazon in the language asked for |
| `Blazon\EnglishBlazon` | The blazon in English |
| `Blazon\FrenchBlazon` | The blazon in French |
| `Contract\BlazonWriter` | A writer of blazons |
| `Svg\SvgRenderer` | The accessible SVG image of a coat |
| `Svg\Palette` | The colours of the tinctures and outlines |
| `Exception\HeraldryException` | The interface of every exception of the library |
| `Exception\InvalidCoat` | A coat the model cannot hold |
| `Exception\InvalidSeed` | A seed that is not 64 hexadecimal digits |
| `Exception\InvalidPalette` | A palette the renderer refuses |
| `Exception\InvalidDrawing` | A prefix of ids the renderer refuses |

`Svg\Shapes` is internal: the paths and the places of the drawing.
