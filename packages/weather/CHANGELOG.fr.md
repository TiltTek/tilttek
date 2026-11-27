<!-- phil-dev source-sha256=c69ad42ec595cced95c70ae14007010356bc65855d48f37ed5dd9238916640a3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/weather` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.4-alpha - 2026-11-24

### Modifié

- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.3-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 et `tilttek/generator` 0.2 requis : la 0.1 de chacun n'est plus acceptée.

### Corrigé

- `WeatherState::fromArray()` ne lit une température qu'à partir d'un nombre entier ou d'un texte qui en contient un :
  `true` était lu comme 1 degré, et `1.0` comme 1 ; les deux sont refusés avec `InvalidState`.

## 0.1.2-alpha - 2026-11-17

### Modifié

- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.1.1-alpha - 2026-11-14

### Modifié

- `tilttek/dice` et `tilttek/generator` 0.2 acceptés, tout comme 0.1.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- Les climats déclarés : `Condition` avec ses libellés, son écart de température de -20 à 20 et ses voisines ;
  `Season` ; `SeasonalClimate` avec sa plage de -80 à 80 °C et les poids de ses conditions de 1 à 1000 ; `Zone` avec ses
  saisons dans l'ordre de l'année, sa persistance de 1 à 20 et son lancer quotidien de `tilttek/dice`, lu et lancé une
  fois pour vérifier qu'il donne un seul total entier ; `Climates` avec ses 1 à 8 langues et ses 1 à 64 conditions,
  saisons et zones, un libellé dans chaque langue, chaque voisine, saison et condition déclarée, les voisines dans les
  deux sens, et au plus 20 000 passages entre conditions dans leurs tables météo (`Climates::TRANSITIONS`).
- `ClimateReader` : des climats depuis du YAML, un fichier ou un document décodé d'au plus 1 Mio, les clés inconnues
  refusées, et chaque problème avec son emplacement.
- Les climats fournis dans `data/climates.yaml`, `Climates::default()` : seize conditions, huit saisons, et cinq zones —
  tempérée, boréale, méditerranéenne, aride et tropicale — en anglais et en français.
- `WeatherTable` : la météo comme table avec mémoire de `tilttek/generator`, sous forme de catalogue, de document ou de
  fichier YAML sous un nom au choix de l'appelant ; une condition qui reste selon son poids multiplié par la persistance
  de la zone ou se change en une voisine qu'apporte la saison, et une condition que la saison n'apporte pas qui se
  change en une voisine qu'elle apporte ou en l'une de ses conditions ; une température qui bouge selon le lancer de la
  zone et d'un degré vers le milieu de la plage déplacée par la condition, dans la plage, et qui revient de deux degrés
  par jour quand elle en sort ; la zone et la saison laissées à l'application. Les fichiers de générateurs écrits à
  partir des climats fournis dans `generators/en` et `generators/fr`.
- `Weather` : des prévisions d'un ou de plusieurs jours à partir d'un `WeatherState`, dans une langue des climats, les
  mêmes jours pour le même état et le même germe, dans les limites de `tilttek/generator` ; `Forecast` avec ses jours et
  l'état que laisse le dernier ; `Day` avec sa condition, son libellé, sa température, sa zone et sa saison, et sa
  température en Fahrenheit ; `WeatherState` lu depuis et écrit vers une table de textes, avec une nouvelle saison ou
  zone.
- Des exceptions sous `WeatherException` : `InvalidClimate`, `UnknownClimate` et `InvalidState`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
