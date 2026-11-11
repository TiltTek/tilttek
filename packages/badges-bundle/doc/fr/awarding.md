<!-- phil-dev source-sha256=7768397b23d9e17d0fff796e305507b717eeb261f9064f2dc51d508592e7614b -->
# Attribuer à partir de l'activité

Le bundle enregistre `Tilttek\Badges\BadgeProjection` avec l'étiquette `tilttek_activity.projection` :
`tilttek/activity-bundle` lui remet chaque entrée du journal, en direct quand ses projections en direct sont activées,
ou par ses commandes.

```php
$journal->record(Activity::of('comment:981', 'member:44', 'news.commented', $now, 'comment:981'));
// the projection brings it to the badges whose criteria read news.commented, and awards those reached
```

## Événements

Quand l'activité fait gagner un badge, le répartiteur d'événements reçoit `BadgeAwarded`. Quand une annulation retire
un badge révocable, il reçoit `BadgeRevoked`. Les deux portent l'`Award` et le `Badge`.

```php
#[AsEventListener]
final readonly class CongratulateMember
{
    public function __invoke(BadgeAwarded $event): void
    {
        // notify $event->award->member of $event->badge
    }
}
```

Une attribution faite à la main ne répartit rien : l'application qui la fait est au courant.

## Reconstructions

Quand le critère d'un badge change, reconstruisez la projection avec la commande de `tilttek/activity-bundle` sur la
période qui a changé : les contributions et les attributions qui en découlent sont oubliées et le journal est rejoué.
Les attributions gagnées de nouveau ne répartissent aucun événement ; les attributions faites à la main sont gardées.

## Mesures

Un critère `measure` lit une valeur que garde l'application — un solde de points — par le service
`Tilttek\Badges\Measures`, que définit l'application. Sans lui, un catalogue qui contient une mesure échoue à la
construction de l'évaluateur.
