# Accessibility

## Readable numbers

The contrast of a skin is that of its ink on its faces, computed as WCAG 2.2 defines it: the relative luminance of
each sRGB colour, then `(L1 + 0.05) / (L2 + 0.05)`, never rounded. A skin keeps its numbers at 4.5:1 at least, the
level AA for text; `Skin` refuses a look under it, for the skin as for each of its types. A die of a set has only a
colour: its ink is black or white, whichever stands out more — 4.58:1 at least on any colour.

`DieAppearance::toArray()` gives the ratio, rounded to two decimals, for a page that shows it.

## High contrast

`appearance($member, $sides, Accessibility::HighContrast)` gives a look for a viewer who needs more contrast: a
setting of the member, or the browser's `prefers-contrast: more` read by the page. It keeps:

1. the look as it is, when its numbers stand out at 7:1 at least, the level AAA;
2. otherwise, its faces with the black or white ink, when that reaches 7:1;
3. otherwise, black numbers on white faces, 21:1.

In every case the faces are `solid` and lose their texture, which would blur the numbers, and the appearance carries
`data-dice-contrast="high"`. The name of the die and where it comes from do not change.

## What the renderer keeps

The appearance only dresses the dice. The renderer keeps:

- the value of each die in text — the number on the face, and the result announced as `tilttek/accessible-dice`
  announces it —, never in its colour alone;
- the system colours under `forced-colors: active`, where the custom properties give way to `Canvas` and `CanvasText`;
- the stillness of the dice under `prefers-reduced-motion: reduce`, whatever the material.
