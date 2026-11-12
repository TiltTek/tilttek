<!-- phil-dev source-sha256=0f65683d220674d9c0a13083b952dcf2fac4885aafa17cd34286e79c9d02f9e6 -->
# Climats

## Le document

```yaml
locales: [en, fr]

conditions:
  clear:
    labels: { en: clear skies, fr: ciel dégagé }
    temperature: 2
    next_to: [fair, frost]
  fair:
    labels: { en: fair, fr: beau temps }
  frost:
    labels: { en: hard frost, fr: gelée blanche }
    temperature: -4

seasons:
  summer: { labels: { en: summer, fr: été } }
  winter: { labels: { en: winter, fr: hiver } }

zones:
  highlands:
    labels: { en: highlands, fr: hautes terres }
    persistence: 3
    change: 1d5-3
    seasons:
      summer: { low: 8, high: 22, conditions: { clear: 4, fair: 5 } }
      winter: { low: -12, high: 3, conditions: { clear: 2, fair: 2, frost: 5 } }
```

| Clé | Ce qu'elle contient |
|---|---|
| `locales` | Les langues dans lesquelles chaque libellé est écrit : de 1 à 8 codes comme `en` ou `fr_CA`. |
| `conditions.<id>.labels` | Le libellé de la condition dans chaque langue, de 1 à 100 caractères. |
| `conditions.<id>.temperature` | De combien de degrés la condition déplace la température à partir du milieu de la plage de la saison, de -20 à 20 ; 0 par défaut. |
| `conditions.<id>.next_to` | Les conditions en lesquelles elle peut se changer d'un jour à l'autre ; un voisinage va dans les deux sens, donc chaque paire s'écrit une fois. |
| `seasons.<id>.labels` | Le libellé de la saison dans chaque langue. |
| `zones.<id>.labels` | Le libellé de la zone dans chaque langue. |
| `zones.<id>.persistence` | Combien de fois plus de poids une condition a pour rester que pour venir, de 1 à 20 ; 3 par défaut. |
| `zones.<id>.change` | Le lancer de `tilttek/dice` ajouté chaque jour à la température, `1d5-3` (de -2 à +2) par défaut ; un seul total entier. |
| `zones.<id>.seasons.<id>.low`, `high` | La plage des températures de la saison, en degrés Celsius, de -80 à 80. |
| `zones.<id>.seasons.<id>.conditions` | La fréquence de chaque condition dans la saison, en poids entiers de 1 à 1000. |

Un identifiant est une lettre minuscule, suivie d'au plus 31 lettres minuscules, chiffres, `_` ou `-`. Les climats
déclarent de 1 à 64 conditions, saisons et zones ; une zone a de 1 à 16 saisons, dans l'ordre de l'année, la première
étant celle où commence un état sans saison. Les tables météo de toutes les zones et saisons réunies tiennent au plus
20 000 passages d'une condition à une autre (`Climates::TRANSITIONS`), comptés avant toute construction : pour chaque
condition d'une saison, un passage vers chaque condition qu'elle peut devenir. Chaque condition, saison et zone a un libellé dans chaque langue ; chaque
voisin, saison et condition nommé est déclaré. `ClimateReader` lit un document d'au plus 1 Mio et nomme l'endroit de
chaque problème : `zones.highlands.seasons.winter.high: -20 is not a whole number from -12 to 80`. Les mêmes
vérifications s'appliquent aux climats construits en PHP avec `Condition`, `Season`, `SeasonalClimate` et `Zone`.

## Comment un jour suit le jour d'avant

Chaque jour :

1. la condition reste, avec le poids que la saison lui donne multiplié par la persistance de la zone, ou devient l'un
   de ses voisins que la saison apporte, avec le poids que la saison donne à ce voisin. Une condition que la saison
   n'apporte pas (après un changement de saison) devient l'un de ses voisins que la saison apporte ou, si elle n'en a
   aucun, n'importe quelle condition de la saison selon son poids ;
2. la température bouge du lancer de la zone, plus un degré vers sa cible (le milieu de la plage de la saison, décalé
   par la nouvelle condition), et reste dans la plage. Une température hors de la plage, après un changement de saison
   ou de zone, y revient plutôt de deux degrés par jour.

Les voisins sont ce qui garde la météo continue : écrivez-les comme le ciel bougerait (beau temps vers couvert, couvert
vers bruine, bruine vers pluie, pluie vers averse), et les poids d'une saison disent la fréquence de chacun. Une zone à
forte persistance garde sa météo des jours durant ; une zone à faible persistance la change souvent.

## Les climats livrés

`Climates::default()` lit `data/climates.yaml`, que nomme `Climates::file()`, à copier et à adapter. Ses seize
conditions (ciel dégagé, beau temps, chaud et moite, couvert, brouillard, bruine, pluie continue, averse, orage, grand
vent, grésil, chute de neige, blizzard, gelée blanche, canicule et tempête de sable) sont libellées en anglais et en
français, et ses zones sont :

| Zone | Saisons | Persistance | Lancer quotidien |
|---|---|---|---|
| `temperate` | printemps 3 à 16 °C, été 14 à 30, automne 4 à 17, hiver -8 à 6 | 3 | `1d5-3` |
| `boreal` | printemps -5 à 10 °C, été 8 à 24, automne -6 à 10, hiver -35 à -5 | 4 | `1d7-4` |
| `mediterranean` | printemps 10 à 22 °C, été 20 à 36, automne 12 à 25, hiver 2 à 15 | 4 | `1d5-3` |
| `arid` | saison chaude 25 à 45 °C, saison fraîche 5 à 25 | 5 | `1d5-3` |
| `tropical` | saison humide 23 à 32 °C, saison sèche 20 à 31 | 3 | `1d3-2` |

Elles décrivent des climats d'un monde de jeu, pas d'un lieu réel : un monde à soi déclare les siens.
