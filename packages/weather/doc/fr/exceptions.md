<!-- phil-dev source-sha256=55a5ac1b9c84f3d0780287ba1dbf87153e0a8002236e87fa16e30259828a644e -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Weather\Exception\WeatherException`. Une prévision laisse aussi
passer les exceptions de `tilttek/generator`, sous sa propre `GeneratorException` : une limite dépassée
(`LimitExceeded`), ou une prévision de moins d'un jour (`InvalidDefinition`).

| Exception | Lancée quand | Message |
|---|---|---|
| `InvalidClimate` | Des climats sont inutilisables : un document qui ne s'analyse pas ou de plus de 1 Mio, une valeur du mauvais genre ou hors de sa plage, un identifiant, un libellé manquant pour une langue, un voisin, une saison ou une condition non déclaré, un lancer quotidien que `tilttek/dice` ne lit pas. | `zones.arid.seasons.hot.high: 20 is not a whole number from 25 to 80` |
| `UnknownClimate` | Un état, une recherche ou un `Weather` nomme une zone, une saison d'une zone, une condition ou une langue que les climats ne déclarent pas. | `The zone "polar" is not declared; the climates know "temperate", "boreal", "mediterranean", "arid", "tropical".` |
| `InvalidState` | Un état est illisible : ni zone ni saison, une condition qui n'est pas un texte, une température qui n'est pas un nombre entier de degrés de -200 à 200. | `The state of the weather holds its temperature as a whole number of degrees.` |
