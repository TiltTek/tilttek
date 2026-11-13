# Drawing

`Svg\SvgRenderer::render()` writes a coat as an `<svg>` element:

```php
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\ShieldShape;
use Tilttek\Heraldry\Svg\SvgRenderer;

$svg = new SvgRenderer()->render($coat, ShieldShape::French, Language::French, title: 'Armoiries de la maison Corbin', id: 'corbin');
```

## What the image holds

Shapes drawn for the package on a shield of 200 by 240 units: four outlines — `Heater`, `French`, `Iberian`,
`Lozenge` —, the partitions, the ordinaries, the charges, the marks of difference, and patterns for ermine and vair.
There is no raster image, no font, no text but the title and the description, no script, no style sheet and no
reference outside the image. The field and everything on it are clipped to the outline, then the outline is drawn.

## Accessibility

The root carries `role="img"` and `aria-labelledby` naming its `<title>` and its `<desc>`. The title is the one given,
or « Coat of arms » / « Armoiries »; the description is the blazon in the language asked for. The title is escaped,
and control characters other than tab and line breaks are left out.

## Ids

Every `id` starts with the prefix given — a letter, then up to 47 letters, digits, `-` or `_` —, or with `arms-` and
12 hexadecimal digits derived from the arms and the shape. Several images share a page without clashing; give each
its own prefix when the same arms appear twice. Any other prefix throws `Exception\InvalidDrawing`.

## Palette

`Svg\Palette` gives the colour of each metal and colour, as `#rrggbb`, and of the outlines; a key left out keeps its
default. Ermine is drawn as sable spots on argent, vair as argent and azure bells, in the palette's colours.

```php
$renderer = new SvgRenderer(new Palette(['azure' => '#2255aa'], outline: '#222222'));
```

## Sanitizing

The image passes `phil/svg-sanitizer` untouched under a policy that keeps the ids as written and allows the two
accessibility attributes, which the tests check on hundreds of generated coats:

```php
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

$sanitizer = new SvgSanitizer(new Policy()->withIdPrefix(null)->withAttributes('role', 'aria-labelledby'));
$sanitizer->sanitize($svg)->isClean();   // true
```

Under the default policy, the sanitizer removes `role` and `aria-labelledby` and prefixes the ids; the image still
draws, its `<title>` still names it.
