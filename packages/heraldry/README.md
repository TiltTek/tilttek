# tilttek/heraldry

**Coats of arms as a model first: generated from a seed, blazoned in English and French, drawn as SVG.**

A coat of arms is held as a structure — a field plain or divided, at most one honourable ordinary, at most one group
of charges on the field or on the ordinary, and at most one mark of difference — and everything else is written from
it. `CoatGenerator` draws arms from a seed of 32 bytes the server takes from its secure source: the same seed draws the
same arms on any server, and every coat it draws keeps the rule of tincture. `Blazonry` writes the blazon in English
(« Azure, a fess Or between three mullets argent ») and in French (« D’azur à la fasce d’or accompagnée de trois étoiles
d’argent »), each with its own order, articles and agreements. `SvgRenderer` draws the arms with shapes drawn for the
package — no raster image, no font —, an accessible image with a title and the blazon as its description, which a
strict SVG sanitizer keeps whole.

It is for a game that gives a character, a house or a guild its arms, a world generator that needs banners, or a book
that describes them. It is a plain PHP library without dependencies.

## Status

| Part | State |
|---|---|
| The model: `Coat`, `Field`, `Partition`, `Tincture`, `Ordinary`, `Piece`, `Charge`, `ChargeGroup`, `Arrangement`, `Brisure` | Implemented and tested |
| `TinctureRule` and `Violation`: the rule of tincture checked on any coat | Implemented and tested |
| `Generator\CoatGenerator` and `Generator\Seed`: seeded, reproducible arms that keep the rule | Implemented and tested |
| `Blazon\Blazonry`, `Blazon\EnglishBlazon`, `Blazon\FrenchBlazon`: the blazon written in English and French | Implemented and tested |
| `Svg\SvgRenderer`, `Svg\Palette`: the SVG drawing, four shield shapes and the furs | Implemented and tested |
| Reading a written blazon back into the model | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [The model](doc/model.md) | Fields, partitions, tinctures, ordinaries, charges, marks of difference, and what a coat may hold. |
| [The rule of tincture](doc/tincture-rule.md) | Metal, colour and fur; where the rule binds and where it does not. |
| [Generating arms](doc/generating.md) | Seeds, reproducibility, and what the generator draws how often. |
| [Blazon](doc/blazon.md) | The order and grammar of the English and French blazons. |
| [Drawing](doc/drawing.md) | The SVG image, its accessibility, its ids, the palette and the sanitizer. |
| [Extension points](doc/extension-points.md) | What an application may replace or add. |
| [Testing](doc/testing.md) | The package's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Svg\SvgRenderer;

$seed = Seed::generate();                          // keep $seed->hex() to draw the same arms again
$coat = new CoatGenerator()->generate($seed);

new Blazonry()->write($coat);                      // for example "Purpure, on a bend sinister Or a lozenge azure"
new Blazonry()->write($coat, Language::French);    // « De pourpre à la barre d’or chargée d’un losange d’azur »
$svg = new SvgRenderer()->render($coat);           // an accessible <svg> element
```

## Requirements

PHP 8.5 or later, with `mbstring` and `sodium`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
