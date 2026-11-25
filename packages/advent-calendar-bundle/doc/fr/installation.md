<!-- phil-dev source-sha256=affd9af9cd71212927dc571696058c2f5bc0704bcb09648ddab54f7f1d93eb18 -->
# Installation

```sh
composer require tilttek/advent-calendar-bundle
```

```php
// config/bundles.php
Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle::class => ['all' => true],
```

La recette de `recipe/0.1` enregistre le bundle et copie sa configuration et ses routes ; tant qu'elle n'est pas
publiée dans un dépôt de recettes, écrivez-les à la main :

```yaml
# config/routes/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    resource: '@TilttekAdventCalendarBundle/config/routes.php'
```

## Qui ouvre une case

Le bundle ne connaît pas les membres de l'application. Implémentez `MemberResolver` et nommez le service dans la
configuration ; sans lui, chaque spectateur est un visiteur, qui voit les cases et n'en ouvre aucune.

```php
use Phil\Resource\ResourceReference;
use Symfony\Bundle\SecurityBundle\Security;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;

final readonly class CurrentMember implements MemberResolver
{
    public function __construct(private Security $security)
    {
    }

    public function current(): ?ResourceReference
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? ResourceReference::of('member', (string) $user->id) : null;
    }
}
```

```yaml
tilttek_advent_calendar:
    member_resolver: App\Advent\CurrentMember
```

La référence est un identifiant stable — jamais une adresse courriel, qu'un membre peut changer et qui dit qui il est.
Le bundle refuse une référence dont l'identifiant contient `@`.

## Accorder les surprises

La bibliothèque distribue `Tilttek\AdventCalendar\Event\BoxOpened` une seule fois, quand un membre ouvre une case pour
la première fois. L'application l'écoute et accorde la surprise : crédite les points, décerne le badge, révèle le
contenu, remet un code. [Calendriers](calendars.md#granting-the-surprises) montre un écouteur.

## Les tables

Avec l'ORM, `doctrine:migrations:diff` écrit la migration des deux tables de [Base de données](database.md) ; sans lui,
créez la table des ouvertures à partir de `AdventCalendarTables::configure()` et déclarez les calendriers dans la
configuration.

## L'écran

L'écran étend la mise en page de l'application — `base.html.twig` sauf si la configuration en nomme une autre — et
remplit son bloc `body`. Il est construit avec les recettes `Card`, `Badge`, `Button` et `Alert` d'un kit de UX Toolkit,
comme le kit PHIL UX, et avec sa `Celebration` quand la célébration est activée :

```sh
php bin/console ux:install card badge button alert celebration --kit=phil
```

Le contrôleur Stimulus est fourni dans `assets/` ; avec le StimulusBundle de Symfony UX et AssetMapper ou Webpack
Encore, il est activé sous le nom `tilttek--advent-calendar--boxes`.

Le formulaire d'ouverture porte un jeton CSRF sous l'identifiant `tilttek_advent_calendar_open`. Avec la protection CSRF
sans état de FrameworkBundle, ajoutez-le à `framework.csrf_protection.stateless_token_ids`.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31, avec la protection CSRF
et les formulaires de FrameworkBundle. L'ORM, la console, les composants Twig et `phil/privacy-bundle` sont facultatifs.
