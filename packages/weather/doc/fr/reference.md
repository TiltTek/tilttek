<!-- phil-dev source-sha256=acd0461f52494e39ec1633d9d30bbabdf0d0329ab30a67c5947523b7c74cf14f -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Weather` | Fait la prévision des jours suivants à partir d'un état, dans une langue. |
| `WeatherState` | La zone, la saison, ainsi que la condition et la température que le dernier jour a laissées ; lu depuis un tableau de textes et écrit dans un tableau de textes. |
| `Forecast` | Les jours d'une prévision et l'état que laisse le dernier. |
| `Day` | La condition, le libellé, la température, la zone et la saison d'un jour ; la température en Fahrenheit. |
| `Climates` | Les langues, conditions, saisons et zones déclarées, chaque référence vérifiée ; les climats livrés. |
| `ClimateReader` | Lit des climats depuis du YAML, un fichier ou un document décodé, en nommant l'endroit de chaque problème. |
| `Condition` | Une condition météorologique : ses libellés, son décalage de température et ses voisins. |
| `Season` | Une saison et ses libellés. |
| `Zone` | Une zone climatique : ses libellés, ses saisons, sa persistance et son lancer quotidien. |
| `SeasonalClimate` | Ce qu'une saison apporte à une zone : sa plage de températures et les poids de ses conditions. |
| `WeatherTable` | Écrit des climats sous forme de table à mémoire de `tilttek/generator` : un catalogue, un document ou un fichier YAML ; les fichiers livrés. |
| `Exception\WeatherException` | L'interface de chaque exception de la bibliothèque. |
| `Exception\InvalidClimate` | Des climats inutilisables. |
| `Exception\UnknownClimate` | Un nom que les climats ne déclarent pas. |
| `Exception\InvalidState` | Un état illisible. |
