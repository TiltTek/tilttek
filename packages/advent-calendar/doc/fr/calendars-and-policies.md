<!-- phil-dev source-sha256=75fde2956cc53f1566e89ae6e5c3207987115cf9c8c7ed7947c5a69ae315a49d -->
# Calendriers et politiques

## Un calendrier

`Calendar::of()` prend un identifiant, un fuseau horaire, la date de la case 1 et une surprise par case, dans l'ordre
des jours :

```php
$calendar = Calendar::of('advent-2026', 'Europe/Paris', '2026-12-01', $surprises);

$calendar->days();          // 24
$calendar->dateOf(24);      // '2026-12-24'
$calendar->surprise(3);     // the surprise of box 3
$calendar->dayAt($moment);  // the box whose day the moment falls on, or null
```

| Argument | Accepté |
|---|---|
| `$id` | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre |
| `$timezone` | Tout fuseau horaire que connaît PHP, `America/Toronto` |
| `$firstDate` | Une date écrite `YYYY-MM-DD` |
| `$surprises` | Une liste de 1 à 92 `Surprise`, une par jour |
| `$policy` | Une `OpeningPolicy` ; `OpeningPolicy::untilClosing()` si null |
| `$closingDate` | La dernière date où une case peut s'ouvrir, écrite `YYYY-MM-DD` : la date de la dernière case si null, ou jusqu'à 366 jours après elle |

Une case est numérotée à partir de 1, la première date. Un jour commence à minuit dans le fuseau horaire du calendrier.
Le jour où l'horloge recule, une case dure 25 heures ; le jour où elle avance, 23 ; là où un changement d'heure saute
minuit lui-même, le jour commence à son premier moment, 01:00. Le calendrier ferme au début du jour qui suit sa date de
fermeture.

## Surprises

Une surprise est une référence, jamais la récompense elle-même :

| Fabrique | Genre | Référence | Quantité |
|---|---|---|---|
| `Surprise::points(50)` | `points` | Le registre, `points` sauf si un autre est donné | Le montant |
| `Surprise::badge('early-bird')` | `badge` | L'insigne | 1 |
| `Surprise::content(ResourceReference::of('page', '42'))` | `content` | La ressource, `page:42` | 1 |
| `Surprise::code('winter-discount')` | `code` | La réserve où l'application tire un code | 1 |
| `Surprise::of($kind, $reference, $quantity)` | Tout | De 1 à 255 caractères ASCII imprimables | De 1 à 1 000 000 |

Un code reste dans la réserve de l'application jusqu'à l'ouverture d'une case : le calendrier tient le nom de la
réserve, de sorte qu'une page qui montre le calendrier ne peut jamais divulguer un code. `toArray()` donne le genre, la
référence et la quantité, pour un écouteur ou un journal.

## Politiques d'ouverture

Chaque calendrier choisit combien de temps une case reste ouvrable une fois son jour venu. Aucune politique n'ouvre une
case avant son jour.

| Politique | Une case est ouvrable | Manquée |
|---|---|---|
| `OpeningPolicy::untilClosing()`, la valeur par défaut | De son jour jusqu'à la fermeture du calendrier | Seulement une fois le calendrier fermé |
| `OpeningPolicy::sameDay()` | Le jour même seulement | Dès minuit après son jour |
| `OpeningPolicy::catchUp(3)` | Le jour même et les 3 jours suivants, de 1 à 31 | Dès minuit après le dernier jour de rattrapage, ou à la fermeture si elle vient d'abord |

`$calendar->window($day)` donne l'`OpeningWindow` de la case : `from`, inclus, et `until`, exclu, avec `hasStarted()`,
`hasEnded()` et `contains()` pour un moment.

La valeur par défaut évite qu'un jour manqué soit une perte : un membre absent pendant une semaine trouve les cases de la
semaine qui l'attendent. Les deux autres politiques existent pour le site qui veut une case liée à son jour ; la page du
calendrier le dit alors, et une date de fermeture plus tardive laisse de la place aux dernières cases.
