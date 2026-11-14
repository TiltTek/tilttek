<!-- phil-dev source-sha256=357ab4fe5fdd1a8f02a532038b5223be46e2b2f6668f4f32a99234b515cd3d6e -->
# Accessibilité

## Des chiffres lisibles

Le contraste d'un skin est celui de son encre sur ses faces, calculé comme le définit WCAG 2.2 : la luminance relative
de chaque couleur sRGB, puis `(L1 + 0.05) / (L2 + 0.05)`, jamais arrondi. Un skin garde ses chiffres à 4,5:1 au moins,
le niveau AA pour le texte ; `Skin` refuse une apparence qui n'y arrive pas, pour le skin comme pour chacun de ses
types. Un dé d'un ensemble n'a qu'une couleur : son encre est noire ou blanche, selon celle qui ressort le plus — 4,58:1
au moins sur n'importe quelle couleur.

`DieAppearance::toArray()` donne le ratio, arrondi à deux décimales, pour une page qui l'affiche.

## Contraste élevé

`appearance($member, $sides, Accessibility::HighContrast)` donne une apparence pour un lecteur qui a besoin de plus de
contraste : un réglage du membre, ou `prefers-contrast: more` du navigateur, lu par la page. Elle garde :

1. l'apparence telle quelle, quand ses chiffres ressortent à 7:1 au moins, le niveau AAA ;
2. sinon, ses faces avec l'encre noire ou blanche, quand celle-ci atteint 7:1 ;
3. sinon, des chiffres noirs sur des faces blanches, 21:1.

Dans tous les cas, les faces sont `solid` et perdent leur texture, qui brouillerait les chiffres, et l'apparence porte
`data-dice-contrast="high"`. Le nom du dé et son origine ne changent pas.

## Ce que garde le moteur de rendu

L'apparence ne fait qu'habiller les dés. Le moteur de rendu garde :

- la valeur de chaque dé en texte — le chiffre sur la face, et le résultat annoncé comme `tilttek/accessible-dice`
  l'annonce —, jamais par sa seule couleur ;
- les couleurs du système sous `forced-colors: active`, où les propriétés personnalisées cèdent la place à `Canvas` et
  `CanvasText` ;
- l'immobilité des dés sous `prefers-reduced-motion: reduce`, quelle que soit la matière.
