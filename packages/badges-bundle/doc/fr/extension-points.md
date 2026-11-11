<!-- phil-dev source-sha256=bde61f15f82362d3a8f3e3f138621e5085498483c686d153d53953453de9936c -->
# Points d'extension

| Service ou alias | Ce que c'est |
|---|---|
| `Tilttek\Badges\Badges` (public) | Ce qu'appelle l'application |
| `Tilttek\Badges\Catalog` (public) | Les badges de la configuration ; remplacez-le pour les lire ailleurs |
| `Tilttek\Badges\Store\ProgressStore`, `AwardStore`, `ProfileStore`, `GrantStore` (alias publics) | Les quatre stockages ; faites-en des alias de ceux de l'application |
| `Tilttek\Badges\Measures` | Défini par l'application quand un critère lit une mesure |
| `Tilttek\Badges\BadgeProjection` (public) | La projection, étiquetée `tilttek_activity.projection` |
| `Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials` (public) | Quand Open Badges est activé et que le composant Uid est installé ; construit sur `Phil\OpenBadges\CredentialIssuer` et `Phil\OpenBadgesBundle\Application\Securing`, qu'enregistre `phil/open-badges-bundle` et qu'une application peut remplacer |

| Événement | Quand |
|---|---|
| `Tilttek\BadgesBundle\Infrastructure\Event\BadgeAwarded` | L'activité fait gagner un badge |
| `Tilttek\BadgesBundle\Infrastructure\Event\BadgeRevoked` | Une annulation retire un badge révocable |

Les gabarits `@TilttekBadges/showcase.html.twig` et `@TilttekBadges/overview.html.twig` se remplacent comme tout
gabarit de bundle, sous `templates/bundles/TilttekBadgesBundle/`.
