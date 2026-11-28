<!-- phil-dev source-sha256=a3989087e565ff98b61c8e956b8fefc356bcd9ff5abc9f1754ab282b1ee52d8f -->
# Définir un calendrier

Un `Calendar` se construit à partir de ses parties, ou se lit dans un tableau avec `CalendarCodec`.

```php
$calendar = new Calendar(
    'vale',
    'Reckoning of the Vale',
    [
        new Month('frost', 'Frost', 30),
        new Month('thaw', 'Thaw', 30),
        new Month('midfeast', 'Midfeast', 0, leapDays: 1, inWeek: false),
        new Month('bloom', 'Bloom', 30),
        new Month('harvest', 'Harvest', 30, leapDays: 1),
    ],
    ['Moonday', 'Fireday', 'Wellday', 'Restday'],
    firstWeekday: 0,
    leapRule: new LeapRule([new LeapCycle(4), new LeapCycle(100, 0, false)]),
    moons: [Moon::ofDecimalCycle('silver', 'Silver', '29.5')],
    eras: [new Era('before', 'Before the Founding', null), new Era('founding', 'Founding', 1)],
    seasons: [new Season('cold', 'Cold', 1, 1), new Season('warm', 'Warm', 4, 1)],
    climateZone: 'temperate',
);
```

## Mois

Un `Month` a un identifiant, un nom, ses jours d'une année commune, les jours qu'il gagne une année bissextile, et
s'il compte ses jours dans la semaine. Un mois sans jours communs et avec un jour bissextile est un jour bissextile
isolé ; un mois hors de la semaine est une fête dont les jours n'ont pas de jour de la semaine et ne font pas avancer
la semaine.

## La semaine

La semaine est la liste des noms de ses jours, de 1 à 100. `firstWeekday` est le jour de la semaine, à partir de 0, du
jour 0 — le premier jour de l'année 1 — ; le jour de la semaine de chaque autre jour s'en déduit, en sautant les jours
hors de la semaine.

## Années bissextiles

Une `LeapRule` est une liste de `LeapCycle` : tous les tant d'années à partir d'un décalage, une année est bissextile
ou, pour une exception, ne l'est pas. Le dernier cycle auquel correspond une année décide. La règle grégorienne compte
trois cycles :

```php
new LeapRule([new LeapCycle(4), new LeapCycle(100, 0, false), new LeapCycle(400)]);
```

Le motif se répète à chaque période, le plus petit commun multiple des cycles, d'au plus 10 000 ans ; compter les années
bissextiles avant une année donnée est alors une division et une consultation de table. Sans cycles, aucune année n'est
bissextile.

## Lunes, ères et saisons

- Une `Moon` a un cycle à elle, comme fraction exacte de jours, et le numéro d'un jour où elle est nouvelle.
- Une `Era` numérote de nouveau les années à partir de sa première année ; une ère sans première année compte à rebours
  les années avant la première ère datée.
- Une `Season` commence un mois et un jour que toute année a, et dure jusqu'à la suivante.

Elles sont décrites dans [Lunes, ères et saisons](moons-eras-seasons.md).

## La forme en tableau

`CalendarCodec::toArray()` écrit un calendrier dans un tableau — pour du JSON ou du YAML — et
`CalendarCodec::fromArray()` le relit :

```json
{
    "id": "vale",
    "name": "Reckoning of the Vale",
    "months": [
        {"id": "frost", "name": "Frost", "days": 30},
        {"id": "midfeast", "name": "Midfeast", "days": 0, "leapDays": 1, "inWeek": false}
    ],
    "weekdays": ["Moonday", "Fireday", "Wellday", "Restday"],
    "firstWeekday": 0,
    "leap": [{"every": 4}, {"every": 100, "leap": false}],
    "moons": [{"id": "silver", "name": "Silver", "cycle": "29.5", "newMoon": 3}],
    "eras": [{"id": "before", "name": "Before the Founding", "start": null}, {"id": "founding", "name": "Founding", "start": 1}],
    "seasons": [{"id": "cold", "name": "Cold", "month": "frost", "day": 1}],
    "climateZone": "temperate"
}
```

- `leapDays`, `inWeek`, `firstWeekday`, `offset`, `leap`, `newMoon`, et les listes de cycles bissextiles, de lunes,
  d'ères et de saisons sont facultatifs.
- Un cycle de lune est un nombre décimal de jours sous forme de texte, avec au plus six décimales ; un nombre entier de
  jours ; ou `[numerator, denominator]` pour toute autre fraction.
- Une saison nomme son mois par son identifiant.
- Une clé que le codec ne connaît pas, une valeur d'un mauvais type ou hors de ses bornes est refusée avec
  `InvalidCalendar`, dont le message commence par l'emplacement de la valeur : `months.2.days: is a whole number.`

## Un modèle grégorien

`Template\Gregorian::definition()` donne le calendrier grégorien proleptique sous forme de tableau prêt à copier — douze
mois, une semaine commençant le lundi, les années bissextiles de 4, 100 et 400, la Lune, les ères avant et de l'ère
commune, et les saisons de l'hémisphère nord — ; `Template\Gregorian::calendar()` le lit. Son jour 0, le 1er janvier de
l'année 1, est un lundi, et son année 0 est 1 avant l'ère commune. Copiez la définition, renommez-la, et changez ce dont
le monde a besoin.

Aucun calendrier d'un monde sous licence n'est livré : une application qui en a besoin écrit sa définition selon la
licence qu'elle détient.

## Bornes

| Partie | Borne |
|---|---|
| Mois d'une année, jours d'une semaine | 1 à 100 |
| Jours d'un mois, jours bissextiles compris | 0 à 1 000 |
| Cycles bissextiles, lunes, saisons | 16 |
| Ères | 100 |
| La période des années bissextiles | 10 000 ans |
| Un cycle de lune | 1 à 10 000 jours, avec un dénominateur jusqu'à 1 000 000 |
| Identifiants | 1 à 64 lettres ASCII, chiffres, points, soulignés et traits d'union, en commençant par une lettre ou un chiffre |
| Noms | 1 à 200 caractères |
