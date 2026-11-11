# Templates

## Twig functions

| Function | Returns |
|---|---|
| `tilttek_collections_album('member:44')` | The member's `Album` |
| `tilttek_collections_today()` | The `DailyPick` of today |
| `tilttek_collections_item('pirate')` | An `Item` of the catalog |
| `tilttek_collections_odds('booster')` | The `Odds` a pack publishes |
| `tilttek_collections_text(item.name, 'fr')` | A name or a description in a locale |
| `tilttek_collections_domain()` | The translation domain of the templates |

## The templates

```twig
{{ include('@TilttekCollections/album.html.twig', {album: tilttek_collections_album('member:44'), locale: 'fr'}) }}
{{ include('@TilttekCollections/daily.html.twig', {pick: tilttek_collections_today(), locale: 'fr'}) }}
{{ include('@TilttekCollections/odds.html.twig', {odds: tilttek_collections_odds('booster'), locale: 'fr'}) }}
```

- **`album.html.twig`**: each set with a native `<progress>` and *2 of 3* — the complete ones marked —, then every item,
  its image and name when owned with its doubles, *To find* otherwise. Each element carries a BEM class, the item's
  rarity among them, and `data-set` or `data-item`.
- **`daily.html.twig`**: the item of the day in a `<figure>`, with its image, name and description.
- **`odds.html.twig`**: a table of the chance of each item, with its rarity.

The locale defaults to English. The texts are in the `tilttek_collections` domain, in English and French; the
templates ship no CSS.
