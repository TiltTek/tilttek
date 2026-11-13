# Generating arms

`CoatGenerator::generate()` draws a coat from a `Generator\Seed`:

```php
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;

$seed = Seed::generate();              // 32 bytes from the system's secure source
$hex = $seed->hex();                   // 64 hexadecimal digits, to keep with the arms

$coat = new CoatGenerator()->generate(Seed::fromHex($hex));
```

## Reproducible

The seed keys a `Xoshiro256**` generator with its SHA-256, and every draw comes from it in a fixed order: the same seed
gives the same coat on any server and any PHP of the supported versions. The seed comes from the server: a client
that sends a seed only asks for arms already drawn. `Seed::fromHex()` takes 64 hexadecimal digits and throws
`Exception\InvalidSeed` for anything else.

## What it draws

| Part | How often |
|---|---|
| A plain field | 3 coats in 5; metals and colours twice as likely as furs |
| A divided field | 2 coats in 5, between a metal or a fur and a colour, in either order |
| An ordinary | a little more than half the coats |
| Charges with no ordinary | 85 % of those coats, one to three; three in ten groups of two or three take an arrangement |
| Charges with an ordinary | half of those coats, on it or beside it as the ordinary allows |
| A mark of difference | one coat in ten |

Every coat keeps [the rule of tincture](tincture-rule.md); on a divided field, the tincture of what lies on it is none
of the field's, and what lies side by side never shares a tincture. `new CoatGenerator(furs: false)` leaves ermine and
vair out of the fields.
