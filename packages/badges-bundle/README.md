# tilttek/badges-bundle

**The badges of a Symfony application's members, earned from their activity, shown at their choice and issued as Open Badges.**

tilttek/badges-bundle integrates `tilttek/badges` with Symfony. It builds the badges and their criteria from the
configuration, checked when the container is compiled, and keeps the contributions of the activity, the awards, the
members' profiles and their consents in four tables on a Doctrine DBAL connection, added to the schema the ORM
generates so that the application's migrations create them. It registers the projection that awards the badges, which
`tilttek/activity-bundle` keeps up to date as the journal is written, catches up and rebuilds, and dispatches an event
when a badge is earned or withdrawn. `Badges` gives the application awards, progress, manual awards, the showcase, the
title, sharing and consents; Twig functions and two templates show the showcase and the progress; three commands
award, withdraw and list; a member's badge is issued as an Open Badges 3.0 credential through
`phil/open-badges-bundle`; a module answers the export
and erasure requests of `phil/privacy-bundle` for a member's badges.

It is for a Symfony application that recognises what its members do with badges, lets each member decide what is
public, and gives them credentials they can carry elsewhere.

It requires Symfony's DependencyInjection and Config, Doctrine DBAL, the PSR event dispatcher, `tilttek/badges` and
`phil/resource`. `tilttek/activity-bundle` is what feeds the criteria; without it, badges are awarded only by hand. The
ORM, the Console, Twig, the Translation contracts, the Uid component, `phil/open-badges-bundle` and
`phil/privacy-bundle` are optional: the schema listener, the commands, the Twig functions, the credentials and the
privacy module are registered only when they are installed.

The design follows the other bundles of the monorepo: an `AbstractBundle` with its configuration, services declared by
`BadgesServices`, DBAL stores and stores in memory for tests, tables added by a schema listener.

## Status

| Part | State |
|---|---|
| Configuration `tilttek_badges`: store, connection, timezone, badges, projection, showcase size, translation domain, Open Badges, member kind | Implemented and tested |
| `DbalProgressStore`, `DbalAwardStore`, `DbalProfileStore`, `DbalGrantStore`, with their four tables | Implemented and tested |
| The tables in the schema the ORM generates, for the migrations | Implemented and tested |
| The projection tagged for `tilttek/activity-bundle`, and the events `BadgeAwarded` and `BadgeRevoked` | Implemented and tested |
| Twig functions `tilttek_badges_showcase`, `tilttek_badges_overview`, `tilttek_badges_domain`, templates `showcase` and `overview` | Implemented and tested |
| Commands `tilttek:badges:award`, `tilttek:badges:revoke`, `tilttek:badges:list` | Implemented and tested |
| `BadgeCredentials`: a member's badge issued as an Open Badges 3.0 credential of `phil/open-badges`, secured as `phil/open-badges-bundle` is configured | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the migration. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_badges`, with its default, and the badges' criteria. |
| [The badges in the database](doc/database.md) | The four tables, the idempotent insertion per platform, dates. |
| [Awarding from the activity](doc/awarding.md) | The projection with `tilttek/activity-bundle`, the events, rebuilds, measures. |
| [Showing badges](doc/showing.md) | `Badges` in a controller, the Twig functions and templates, the showcase, the title, consents. |
| [Commands](doc/commands.md) | `tilttek:badges:award`, `tilttek:badges:revoke` and `tilttek:badges:list`. |
| [Open Badges](doc/open-badges.md) | Issuing a member's badge as a credential through `phil/open-badges-bundle`. |
| [Privacy](doc/privacy.md) | The export and the erasure of a member's badges. |
| [Extension points](doc/extension-points.md) | The services, aliases, tags and events the application uses. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests and how to test an application that awards badges. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/activity-bundle tilttek/badges-bundle
```

```yaml
# config/packages/tilttek_badges.yaml
tilttek_badges:
    timezone: America/Toronto
    badges:
        commenter.bronze:
            name: badges.commenter.bronze.name
            tier: { series: commenter, rank: 1 }
            criterion: { count: { verbs: ['news.commented'], times: 10 } }
        regular:
            name: badges.regular.name
            criterion: { streak: { verbs: ['site.visited'], days: 7 } }
        organiser: { name: badges.organiser.name, criterion: { manual: true } }
```

```twig
{{ include('@TilttekBadges/showcase.html.twig', {showcase: tilttek_badges_showcase('member:' ~ member.id)}) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
