<!-- phil-dev source-sha256=14c187635675e0886ae0f245bb2b9f54277e2c490f4355cd04dd09f842199267 -->
# Open Badges

Le badge d'un membre est délivré comme une attestation Open Badges 3.0 par `phil/open-badges-bundle` : ce bundle tient
le profil de l'émetteur, sa clé et la façon dont ses attestations sont sécurisées ; ce bundle-ci lui fournit le badge.

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

`{code}` dans l'URL de la réalisation représente le code du badge. L'émetteur, sa clé — un germe Ed25519 ou une clé RSA
en PEM, lue dans un secret —, la sécurisation, les routes du profil et des clés de l'émetteur, et la page de
vérification se configurent comme le documente `phil/open-badges-bundle`. Activer Open Badges sans que
`PhilOpenBadgesBundle` soit enregistré, ou sans l'URL de la réalisation, échoue à la construction du conteneur.

```php
use Phil\OpenBadges\Recipient;
use Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials;

$credential = $credentials->issue($member, 'commenter.gold', Recipient::email($emailTheMemberGave, bin2hex(random_bytes(8))), locale: 'fr');
$credential->content;   // the compact token, or the JSON of the document
$credential->mediaType; // application/vc+jwt, or application/vc
$credential->filename(); // credential.jwt, or credential.json
```

`BadgeCredentials::issue()` prend l'attribution du badge au numéro le plus élevé — `NotEarned` quand le membre n'en a
aucune. Pour un badge répétable, c'est la dernière attribution que le membre a gagnée, quels que soient les moments des
attributions : deux attributions peuvent partager un moment, et un badge attribué à la main peut porter un moment
antérieur à celui d'une attribution de l'activité. Il traduit le nom et la description du badge dans la langue du
membre quand le composant Translation est installé. Il utilise la description comme récit des critères (le nom quand il n'y en a pas), l'image du badge quand c'est une URL
absolue, un nouvel `urn:uuid:` comme identifiant, et le moment de l'attribution comme début de validité. Il renvoie
l'attestation sécurisée comme l'indique `phil_open_badges.securing`, sous la forme d'un
`Phil\OpenBadgesBundle\Application\SecuredCredential` : un JSON Web Token compact, ou le JSON du document avec sa
preuve `eddsa-rdfc-2022`, avec son type de média et un nom de fichier, pour un téléchargement. Le membre demande
l'attestation ; l'application la lui remet, et peut l'intégrer à l'image du badge avec `phil/open-badges`.

`Phil\OpenBadges\CredentialIssuer` et `Phil\OpenBadgesBundle\Application\Securing`, enregistrés par
`phil/open-badges-bundle`, servent une application qui construit ses propres réalisations.
