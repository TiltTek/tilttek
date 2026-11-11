<!-- phil-dev source-sha256=20a789a5895b378af1bebe8847904421eee1bb6f4175f2f8dc4cc9e06796a5b7 -->
# Gabarits

## Fonctions Twig

| Fonction | Renvoie |
|---|---|
| `tilttek_collections_album('member:44')` | L'`Album` du membre |
| `tilttek_collections_today()` | Le `DailyPick` d'aujourd'hui |
| `tilttek_collections_item('pirate')` | Un `Item` du catalogue |
| `tilttek_collections_odds('booster')` | Les `Odds` que publie un paquet |
| `tilttek_collections_text(item.name, 'fr')` | Un nom ou une description dans une locale |
| `tilttek_collections_domain()` | Le domaine de traduction des gabarits |

## Les gabarits

```twig
{{ include('@TilttekCollections/album.html.twig', {album: tilttek_collections_album('member:44'), locale: 'fr'}) }}
{{ include('@TilttekCollections/daily.html.twig', {pick: tilttek_collections_today(), locale: 'fr'}) }}
{{ include('@TilttekCollections/odds.html.twig', {odds: tilttek_collections_odds('booster'), locale: 'fr'}) }}
```

- **`album.html.twig`** : chaque ensemble avec un `<progress>` natif et *2 sur 3* — les ensembles complets marqués —,
  puis chaque objet, avec son image et son nom quand il est possédé, et ses doubles, *À trouver* sinon. Chaque élément
  porte une classe BEM, dont la rareté de l'objet, et `data-set` ou `data-item`.
- **`daily.html.twig`** : l'objet du jour dans une `<figure>`, avec son image, son nom et sa description.
- **`odds.html.twig`** : un tableau de la chance de chaque objet, avec sa rareté.

La locale par défaut est l'anglais. Les textes sont dans le domaine `tilttek_collections`, en anglais et en français ;
les gabarits ne sont fournis avec aucune CSS.
