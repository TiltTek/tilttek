<!-- phil-dev source-sha256=219f9560889b6dd5b33d432bcb7bfbff975db5777673afe478e5435bc2e56498 -->
# Points d'extension

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\OpeningStore` | Le stockage des ouvertures, à la place du magasin en mémoire | [Écrire un magasin](writing-a-store.md) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Le répartiteur par où passe `BoxOpened`, vers l'écouteur qui accorde la surprise et peut lancer une célébration | [Ouvrir les cases](opening-boxes.md#accorder-la-surprise) |
| `Psr\Clock\ClockInterface` | L'horloge où se lisent les jours : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Les calendriers sont des valeurs que l'application construit avec `Calendar::of()` ; leurs surprises sont des références
que l'application résout. Chaque point est passé au constructeur d'`AdventCalendar`, qui ne garde aucun état propre
entre deux appels.

Les types publics et le contrat du magasin sont stables au sein d'une version mineure de `0.x` ; un changement qui les
touche vient avec une nouvelle version mineure et une ligne dans le journal des modifications.
