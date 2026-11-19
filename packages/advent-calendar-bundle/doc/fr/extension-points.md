<!-- phil-dev source-sha256=6c7ff3e2287ace36d627ba3bea1656123c1568c0f67073a2f15cf14b727a3088 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Dire qui regarde un calendrier | Implémentez `MemberResolver` et nommez-le dans `member_resolver` |
| Accorder les surprises | Écoutez `Tilttek\AdventCalendar\Event\BoxOpened` |
| Prendre les calendriers ailleurs | Implémentez `CalendarCatalogue` et faites-en un alias, ou décorez `Calendars` |
| Garder les ouvertures ailleurs | Faites de `Tilttek\AdventCalendar\Contract\OpeningStore` et de `MemberOpenings` des alias vers votre service |
| Changer l'écran | Remplacez un gabarit sous `templates/bundles/TilttekAdventCalendarBundle/` |
| Montrer les surprises avec vos propres mots | Remplacez `_surprise.html.twig` |
| Changer les textes | Remplacez les traductions du domaine `tilttek_advent_calendar` |
| Modifier les calendriers dans votre interface d'administration | Utilisez `CalendarType` sur un `CalendarRecord` |
