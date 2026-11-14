<!-- phil-dev source-sha256=bac400327066047eff26aad5209e7c958a31ecd52329761f1c0f6209db3dda2e -->
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

Un événement est réparti au moins une fois : quand un écouteur échoue, l'attribution garde son avis dû, et l'événement
est réparti de nouveau quand l'entrée est remise à la projection — la requête répétée, ou le rattrapage suivant —, avant
la prochaine activité du membre, ou par `BadgeProjection::deliver($member)`. Un écouteur doit donc être idempotent : il
reconnaît une attribution à son badge, son membre et son numéro.

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
