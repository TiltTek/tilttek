<!-- phil-dev source-sha256=f6a35ebdf60ec92e69c5279bf0a5bb6c3ee53d009ef75b7ac197e6f558cabf01 -->
# Calendriers

Un calendrier a une case pour chaque jour à partir de sa première date, une surprise par case, dans son fuseau horaire.
Ses jours commencent à minuit à l'horloge murale du fuseau horaire, malgré les changements d'heure. Aucune case ne
s'ouvre avant son jour.

## Politiques d'ouverture

Chaque calendrier choisit combien de temps une case reste ouvrable une fois son jour venu :

| Politique | Une case reste ouvrable |
|---|---|
| `until_closing`, la valeur par défaut | De son jour jusqu'à la fermeture du calendrier : un membre qui a manqué un jour ouvre sa case plus tard. |
| `same_day` | Le jour même seulement ; après, la case est manquée. |
| `catch_up` | Le jour même et pendant les 1 à 31 jours qui suivent, jamais au-delà de la fermeture. |

La fermeture est la date de la dernière case, sauf si le calendrier en nomme une plus tardive, jusqu'à 366 jours
après. L'écran énonce la politique en une phrase, afin qu'un membre sache si un jour manqué est perdu.

## Calendriers de la configuration

La configuration déclare les calendriers par identifiant, comme le montre [Configuration](configuration.md). Ils sont
construits une fois, à la construction du conteneur, et chacun est vérifié par la bibliothèque : un calendrier qui ne
tient pas ensemble arrête l'application à sa première requête plutôt que le jour d'une case. Un calendrier avec
`published: false` n'est pas montré.

## Calendriers de la base de données

Avec l'ORM, le bundle associe `CalendarRecord` à la table `tilttek_advent_calendar` : un site modifie ses calendriers
comme il modifie son autre contenu. Les surprises sont gardées dans l'ordre en JSON, puisqu'une case se lit avec son
calendrier, jamais seule.

`CalendarType` est le formulaire d'un calendrier, sur un `CalendarRecord` : son identifiant, son titre, son fuseau
horaire, ses dates de début et de fermeture, sa politique, ses jours de rattrapage, sa publication, et une collection de
`SurpriseType`, une par case. À la soumission, la bibliothèque vérifie le calendrier dans son ensemble et son refus
devient une erreur du formulaire. L'option `existing: true` fige l'identifiant d'un calendrier qui existe, puisque ses
ouvertures sont gardées sous lui.

```php
$record = $calendarId === null ? new CalendarRecord() : $entityManager->find(CalendarRecord::class, $calendarId);
$form = $this->createForm(CalendarType::class, $record, ['existing' => $calendarId !== null]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $entityManager->persist($record);
    $entityManager->flush();
}
```

Un calendrier de la base de données qui n'est pas publié n'est pas montré. Un calendrier dont les champs ne tiennent
plus ensemble — modifiés à la main, par exemple — n'est pas montré non plus, et est journalisé comme une erreur ;
`tilttek:advent-calendar:check` le nomme avec sa raison.

## Surprises

Une surprise est une référence à une récompense que l'application accorde :

| Genre | Référence | Quantité |
|---|---|---|
| `points` | Le registre, `points` par défaut | Combien de points |
| `badge` | Le badge | 1 |
| `content` | Le contenu révélé, `recipe:gingerbread` | 1 |
| `code` | La réserve dont un code est tiré | Combien de codes |

Une case non ouverte ne révèle jamais sa surprise : l'écran la montre une fois que le membre a ouvert la case, et la
commande `tilttek:advent-calendar:show` la montre aux personnes qui font fonctionner le site.

## Accorder les surprises

La bibliothèque distribue `BoxOpened` une seule fois, quand un membre ouvre une case pour la première fois, par le
répartiteur d'événements de l'application. Rouvrir la case répond avec la même ouverture et ne distribue rien.
L'événement ne porte que des références, et une clé que le registre de l'application peut refuser une deuxième fois :

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\SurpriseKind;

#[AsEventListener]
final readonly class GrantSurprise
{
    public function __invoke(BoxOpened $event): void
    {
        match ($event->surprise->kind) {
            SurpriseKind::Points => $this->points->credit($event->member, $event->surprise->reference, $event->surprise->quantity, $event->idempotencyKey()),
            SurpriseKind::Badge => $this->badges->award($event->member, $event->surprise->reference),
            default => null,
        };
    }
}
```

Un écouteur qui accorde par un service lent distribue un message de Messenger qui porte le calendrier, la référence du
membre et le jour, et accorde depuis le *handler*.
