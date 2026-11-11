# Open Badges

A member's badge is issued as an Open Badges 3.0 credential by `phil/open-badges-bundle`: that bundle holds the
issuer's profile, its key and how its credentials are secured; this bundle gives it the badge.

```sh
composer require phil/open-badges-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpenBadgesBundle\PhilOpenBadgesBundle::class => ['all' => true],
    Tilttek\BadgesBundle\TilttekBadgesBundle::class => ['all' => true],
];
```

```yaml
phil_open_badges:
    issuer:
        id: https://example.org/open-badges/issuer
        name: Example
        url: https://example.org
    key:
        algorithm: EdDSA
        secret: '%env(base64:OPEN_BADGES_SEED)%'
    securing: jwt                # or data_integrity

tilttek_badges:
    open_badges:
        enabled: true
        achievement_url: https://example.org/badges/{code}
```

`{code}` in the achievement URL stands for the badge's code. The issuer, its key — an Ed25519 seed or an RSA key in
PEM, read from a secret —, the securing, the routes of the issuer's profile and keys, and the verification page are
configured as `phil/open-badges-bundle` documents them. Enabling Open Badges without `PhilOpenBadgesBundle` registered,
or without the achievement URL, fails when the container is built.

```php
use Phil\OpenBadges\Recipient;
use Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials;

$credential = $credentials->issue($member, 'commenter.gold', Recipient::email($emailTheMemberGave, bin2hex(random_bytes(8))), locale: 'fr');
$mediaType = $credentials->mediaType(); // application/vc+jwt, or application/vc
```

`BadgeCredentials::issue()` takes the latest award of the badge — `NotEarned` when the member has none —, translates the
badge's name and description in the member's language when the Translation component is installed, uses the
description as the narrative of the criteria (the name when there is none), the badge's image when it is an absolute
URL, a new `urn:uuid:` as identifier, and the award's moment as the start of validity. It returns the credential
secured as `phil_open_badges.securing` says: a compact JSON Web Token, or the document with its `eddsa-rdfc-2022`
proof. `mediaType()` names what it returns, for a download. The member asks for the credential; the application gives
it to them, and may bake it into the badge's image with `phil/open-badges`.

`Phil\OpenBadges\CredentialIssuer` and `Phil\OpenBadgesBundle\Application\Securing`, registered by
`phil/open-badges-bundle`, serve an application that builds its own achievements.
