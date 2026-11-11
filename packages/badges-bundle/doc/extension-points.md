# Extension points

| Service or alias | What it is |
|---|---|
| `Tilttek\Badges\Badges` (public) | What the application calls |
| `Tilttek\Badges\Catalog` (public) | The badges from the configuration; replace it to read them elsewhere |
| `Tilttek\Badges\Store\ProgressStore`, `AwardStore`, `ProfileStore`, `GrantStore` (public aliases) | The four stores; alias them to the application's own |
| `Tilttek\Badges\Measures` | Defined by the application when a criterion reads a measure |
| `Tilttek\Badges\BadgeProjection` (public) | The projection, tagged `tilttek_activity.projection` |
| `Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials` (public) | When Open Badges are enabled and the Uid component installed; built on `Phil\OpenBadges\CredentialIssuer` and `Phil\OpenBadgesBundle\Application\Securing`, which `phil/open-badges-bundle` registers and an application may replace |

| Event | When |
|---|---|
| `Tilttek\BadgesBundle\Infrastructure\Event\BadgeAwarded` | The activity earns a badge |
| `Tilttek\BadgesBundle\Infrastructure\Event\BadgeRevoked` | A cancellation withdraws a revocable badge |

The templates `@TilttekBadges/showcase.html.twig` and `@TilttekBadges/overview.html.twig` are overridden like any
bundle template, under `templates/bundles/TilttekBadgesBundle/`.
