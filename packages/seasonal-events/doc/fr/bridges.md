<!-- phil-dev source-sha256=fb615e2f58ca4277ff20b0af4110da524689998507f2560019136450faba6c27 -->
# Passerelles

La bibliothèque ne dépend d'aucun autre paquet de Tilttek. Les passerelles sous `Bridge\` ne servent que lorsque
l'application installe le paquet auquel elles mènent.

## Quêtes

`Bridge\Quests\SeasonQuestSchedule` est le calendrier d'une quête de `tilttek/quests` qui s'ouvre avec une saison, ou
avec l'un de ses événements. Chaque venue de la saison est une occurrence, dont le code est la date du premier jour de
la saison, de sorte que la quête s'accomplit une fois par année :

```php
use Tilttek\SeasonalEvents\Bridge\Quests\SeasonQuestSchedule;

$schedule = new SeasonQuestSchedule($winter);                              // while the season lasts
$giftWeek = new SeasonQuestSchedule($winter, $winter->event('gift-week')); // while the event runs
```

## Collections

`Bridge\Collections\SeasonalItems` liste les objets d'un catalogue de `tilttek/collections` qu'apportent les saisons à
un moment : chaque référence d'objet du genre donné, `item:snow-hat`, désigne l'objet de ce code ; une référence que le
catalogue ne contient pas est laissée de côté.

```php
$items = new SeasonalItems($seasons, $catalogProvider)->at();
```

Une saison qui vient chaque année apporte de nouveau ses objets : rien de ce qu'un membre collectionne n'est perdu pour
de bon.

## Un calendrier de l'avent pendant une saison

Un calendrier de `tilttek/advent-calendar` peut se dérouler pendant une saison sans que l'un des paquets connaisse
l'autre : l'application construit le calendrier de chaque année à partir de la prochaine venue de la saison.

```php
use Tilttek\AdventCalendar\Calendar;

$period = $seasons->next('winter-festival');
$calendar = Calendar::of('advent-'.substr($period->code, 0, 4), 'America/Toronto', $period->code, $surprises);
```

L'identifiant du calendrier porte l'année, afin que les ouvertures d'une année ne soient pas celles de la suivante.
