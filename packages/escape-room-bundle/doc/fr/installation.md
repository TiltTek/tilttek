<!-- phil-dev source-sha256=7a33de274f91272518faf6c10f768b0fb2edf137a0958bd456c7cf72751fb52c -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `sodium`.
- `tilttek/escape-room` 0.1 et `tilttek/puzzle-builder` 0.1.
- Doctrine DBAL 4.5, Twig 3.21, et le conteneur, le noyau HTTP, le routage et la protection CSRF de Symfony 8.2.
- Facultatifs : l'ORM Doctrine, pour les tables du schéma généré ; Symfony Console, Scheduler, Mercure et Rate Limiter ;
  les composants Twig et le kit PHIL UX, pour les écrans ; `phil/reservation`, pour les réservations ;
  `phil/privacy-bundle`, pour l'export et l'effacement d'un membre.

## Installer

```sh
composer require tilttek/escape-room-bundle
```

Avec Symfony Flex, la recette enregistre le bundle et copie `config/packages/tilttek_escape_room.yaml` et
`config/routes/tilttek_escape_room.yaml`. Sans elle, enregistrez le bundle :

```php
// config/bundles.php
return [
    // …
    Tilttek\EscapeRoomBundle\TilttekEscapeRoomBundle::class => ['all' => true],
];
```

et importez les routes :

```yaml
# config/routes/tilttek_escape_room.yaml
tilttek_escape_room:
    resource: '@TilttekEscapeRoomBundle/config/routes.php'
```

## Les tables

Avec l'ORM, les cinq tables sont ajoutées au schéma qu'il génère pour la connexion de la configuration :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez-les avec `EscapeRoomTables::configure()`, comme le décrit [la base de données](database.md).

## L'accès

Chaque écran sauf le classement demande à l'application qui est le spectateur. Implémentez
`Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess` à partir de vos propres membres et de votre personnel,
et nommez-le :

```php
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\StoredGame;

final readonly class Access implements EscapeRoomAccess
{
    public function __construct(private Security $security, private Staff $staff, private Teams $teams)
    {
    }

    public function host(StoredGame $game): ?ResourceReference
    {
        $user = $this->security->getUser();

        return $user instanceof Member && $this->staff->runs($user, $game->room->id) ? ResourceReference::of('member', (string) $user->id) : null;
    }

    public function watches(StoredGame $game): bool
    {
        return $this->security->isGranted('ROLE_ROOM_SCREEN') || null !== $this->host($game) || $this->speaksForTeam($game);
    }

    public function speaksForTeam(StoredGame $game): bool
    {
        $user = $this->security->getUser();

        return $user instanceof Member && $this->teams->isMember($user, $game->game->team);
    }
}
```

```yaml
tilttek_escape_room:
    access: App\EscapeRoom\Access
```

Sans lui, `NoEscapeRoomAccess` refuse toute console, tout écran et tout consentement.

## Les écrans

Les gabarits sont construits avec les recettes `Card`, `Badge`, `Button`, `Alert` et `Celebration` du kit PHIL UX, que
l'application installe par le Symfony UX Toolkit :

```sh
php bin/console ux:install card badge button alert celebration --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les contrôleurs Stimulus sont dans `assets/` du bundle, sous le nom `@tilttek/escape-room` ; avec StimulusBundle et
AssetMapper, ils s'activent dans `assets/controllers.json` comme ceux de tout paquet Symfony UX.

## L'horaire

Pour mettre fin aux parties dont la minuterie a atteint zéro, activez l'horaire et lancez un *worker*, comme le décrit la
[planification](scheduling.md) :

```yaml
tilttek_escape_room:
    schedule: { enabled: true }
```
