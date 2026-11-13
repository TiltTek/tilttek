<!-- phil-dev source-sha256=ec928aafa5e41ae5d8c17dd2e3cc7b9ea9875dfd49752fec23c900dc533688ee -->
# Le dessin

`Svg\SvgRenderer::render()` écrit un écu comme un élément `<svg>` :

```php
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\ShieldShape;
use Tilttek\Heraldry\Svg\SvgRenderer;

$svg = new SvgRenderer()->render($coat, ShieldShape::French, Language::French, title: 'Armoiries de la maison Corbin', id: 'corbin');
```

## Ce que l'image contient

Des formes dessinées pour le paquet sur un écu de 200 sur 240 unités : quatre contours — `Heater`, `French`, `Iberian`,
`Lozenge` —, les partitions, les pièces, les meubles, les brisures, et des motifs pour l'hermine et le vair. Il n'y a
aucune image matricielle, aucune police, aucun texte hors le titre et la description, aucun script, aucune feuille de
style et aucune référence hors de l'image. Le champ et tout ce qui s'y trouve sont découpés selon le contour, puis le
contour est dessiné.

## Accessibilité

La racine porte `role="img"` et `aria-labelledby`, qui nomment son `<title>` et sa `<desc>`. Le titre est celui qu'on a
donné, ou « Coat of arms » / « Armoiries » ; la description est le blason dans la langue demandée. Le titre est
échappé, et les caractères de contrôle autres que la tabulation et les sauts de ligne sont omis.

## Identifiants

Chaque `id` commence par le préfixe donné — une lettre, puis jusqu'à 47 lettres, chiffres, `-` ou `_` —, ou par `arms-`
et 12 chiffres hexadécimaux dérivés des armes et de la forme. Plusieurs images partagent une page sans se heurter ;
donnez à chacune son préfixe quand les mêmes armes paraissent deux fois. Tout autre préfixe lance
`Exception\InvalidDrawing`.

## Palette

`Svg\Palette` donne la couleur de chaque métal et de chaque couleur, en `#rrggbb`, et des contours ; une clé omise garde
sa valeur par défaut. L'hermine est dessinée en mouchetures de sable sur argent, le vair en clochettes d'argent et
d'azur, dans les couleurs de la palette.

```php
$renderer = new SvgRenderer(new Palette(['azure' => '#2255aa'], outline: '#222222'));
```

## Assainir

L'image passe `phil/svg-sanitizer` intacte sous une politique qui garde les identifiants tels qu'écrits et permet les
deux attributs d'accessibilité, ce que les tests vérifient sur des centaines d'écus générés :

```php
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

$sanitizer = new SvgSanitizer(new Policy()->withIdPrefix(null)->withAttributes('role', 'aria-labelledby'));
$sanitizer->sanitize($svg)->isClean();   // true
```

Sous la politique par défaut, l'assainisseur retire `role` et `aria-labelledby` et préfixe les identifiants ; l'image
se dessine toujours, et son `<title>` la nomme toujours.
