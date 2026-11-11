<!-- phil-dev source-sha256=ca2125c69a366d77e05469dc932760107ffd76b663360667b61deb76c3c8e233 -->
# L'objet du jour

Un objet par jour, qu'un membre reçoit en passant ce jour-là.

## Le calendrier

`DailyCalendar` calcule l'objet de chaque jour, écrit `Y-m-d` dans son fuseau horaire :

1. l'objet gardé pour le jour — choisi par le site, ou généré à l'avance ;
2. sinon, l'objet de la première règle qui parle du jour : une `FixedDate`, chaque année à un mois et un jour (`10-31`)
   ou une seule fois à une date complète (`2026-12-25`), ou une `DateRule` propre à l'application —
   `tilttek/collections-bundle` en fournit une sur `phil/almanac`, pour Pâques ou le quatrième jeudi de novembre ;
3. sinon, un tirage pondéré par la rareté parmi les objets des ensembles du calendrier — tous les objets quand aucun
   n'est nommé —, dont le germe est le SHA-256 du sel du calendrier et du jour : le même jour donne toujours le même
   objet.

`pick(day)` lit le calendrier, `compute(day)` ce que donnent les règles et le tirage indépendamment de ce qui est gardé,
et `dayOf(moment)` le jour où tombe un moment.

## Générer et corriger

`generate(from, until, regenerate)` écrit dans le `DailyStore` les objets calculés de 3 660 jours au plus et les
renvoie, pour que le site puisse les revoir : un jour déjà généré garde son objet, sauf si `regenerate` est vrai ; un
jour choisi par le site le garde toujours. `choose(day, item)` place un objet sur un jour ; `release(day)` oublie ce qui
est gardé pour lui.

## Réclamer

`Daily::claim(member, moment)` remet au membre l'objet du jour où tombe le moment, une seule fois par membre et par
jour, sous une clé dérivée du jour et du membre ; `today()` le lit. `takeBack(member, moment)` le reprend si le membre
l'a encore et ne l'a jamais échangé.

## La projection

`DailyProjection` est une projection de `tilttek/activity` : la première activité d'un membre dans une journée parmi
les verbes que nomme le site — `page.viewed`, `member.signed_in` — remet l'objet de ce jour, au moment de l'activité.
L'annulation de cette activité reprend l'objet, sauf s'il a été échangé ; une autre activité du même jour le remet de
nouveau lors d'une reconstruction. Avant une reconstruction, les objets du jour remis dans la période et jamais
échangés sont oubliés, puis remis de nouveau à mesure que l'activité est rejouée, avec le calendrier tel qu'il est
maintenant ; un objet échangé reste avec son nouveau propriétaire.
