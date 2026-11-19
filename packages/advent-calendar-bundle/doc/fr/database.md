<!-- phil-dev source-sha256=d33018da12ee32d0e810dd5a1995e3b870918d5b611516d6da44c328c838422e -->
# Base de données

## `tilttek_advent_calendar_opening`

Les ouvertures, par Doctrine DBAL. La table est ajoutée au schéma que l'ORM génère pour la connexion configurée par
`OpeningsSchemaListener`, de sorte que `doctrine:migrations:diff` écrit sa migration et que `doctrine:schema:validate`
ne demande pas de la supprimer. Sans l'ORM, créez-la à partir de `AdventCalendarTables::configure()`.

| Colonne | Type | Contient |
|---|---|---|
| `calendar` | `string(64)` | L'identifiant du calendrier. |
| `member` | `string(320)` | La référence du membre, `member:44`. |
| `day` | `smallint` | La case. |
| `opened_at` | `datetime_immutable` | Quand elle a été ouverte pour la première fois, en UTC, à la seconde. |
| `delivered` | `boolean`, faux par défaut | Si son `BoxOpened` a été envoyé sans exception. |

La clé primaire `(calendar, member, day)` est la garde qui fait qu'une ouverture n'arrive qu'une fois : deux requêtes
qui ouvrent la même case en même temps insèrent toutes deux, l'une gagne, et l'autre apprend que la case était déjà
ouverte. L'insertion s'exécute dans une transaction à elle, imbriquée dans celle de l'appelant, de sorte que
l'insertion refusée ne gâche jamais une transaction que PostgreSQL abandonnerait autrement. L'index
`tilttek_advent_calendar_opening_member` sert l'export et l'effacement d'un membre.

Une ouverture est insérée avec `delivered` faux. Une fois son `BoxOpened` envoyé sans exception, une seule mise à jour
conditionnelle le passe à vrai ; un écouteur qui a échoué le laisse faux, et la prochaine ouverture du calendrier par le
membre, ou `AdventCalendar::deliver()`, l'envoie de nouveau. Les écouteurs de `BoxOpened` sont donc idempotents.

## `tilttek_advent_calendar`

Les calendriers que le site modifie, associés par l'ORM à `CalendarRecord` : `id`, `title`, `timezone`, `first_date`,
`closing_date`, `policy`, `catch_up_days`, `published`, et `surprises` en JSON, une par case dans l'ordre des jours.
La table n'existe qu'avec l'ORM.
