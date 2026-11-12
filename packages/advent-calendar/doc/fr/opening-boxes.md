<!-- phil-dev source-sha256=10d7d785bdb6dd010a062142c04a8cf49ce3382ea708f21b83800fabba938ad1 -->
# Ouvrir les cases

## Ouvrir une fois

```php
$opened = $advent->open($calendar, $member, 3);
```

`open()` répond un `OpenedBox` : l'`Opening` enregistrée — le calendrier, le membre, le jour et le moment —, la
`Surprise` de la case, et `first`, vrai quand cet appel a ouvert la case.

- Une case déjà ouverte répond sa première ouverture, avec `first` faux, quel que soit le moment : même après la
  fermeture du calendrier, un membre voit ce que contenait sa case.
- Une case jamais ouverte s'ouvre quand sa fenêtre contient le moment de l'horloge. Avant son jour, `open()` lève
  `BoxNotOpenable` avec le moment où elle s'ouvre ; après sa fenêtre, `BoxNotOpenable` avec le moment où elle s'est
  fermée.
- Un jour dont le calendrier n'a pas de case lève `InvalidCalendar` avant que le magasin soit lu.

Le garde-fou est le `add()` du magasin, qui garde une seule ouverture par calendrier, membre et jour. Quand deux requêtes
ouvrent la même case en même temps, l'une garde son ouverture ; l'autre la trouve en place et la répond, avec `first`
faux.

## Accorder la surprise

Chaque première ouverture envoie `Event\BoxOpened`, une seule fois, avec l'identifiant du calendrier, le membre, le jour,
la surprise et le moment. L'écouteur de l'application accorde la surprise : il crédite les points, décerne l'insigne,
révèle le contenu ou tire un code dans la réserve.

```php
#[AsEventListener]
public function __invoke(BoxOpened $event): void
{
    match ($event->surprise->kind) {
        SurpriseKind::Points => $this->ledger->credit($event->member, $event->surprise->quantity, $event->idempotencyKey()),
        SurpriseKind::Badge => $this->badges->award($event->member, $event->surprise->reference),
        SurpriseKind::Content => $this->unlocks->unlock($event->member, ResourceReference::fromString($event->surprise->reference)),
        SurpriseKind::Code => $this->codes->handOut($event->member, $event->surprise->reference),
    };
}
```

`idempotencyKey()` — `advent:<calendar>:<member>:<day>` — permet à un registre de refuser un second crédit quand un
écouteur est relancé. L'événement ne porte que des références ; un écouteur qui confie le travail à une file de messages
les envoie telles quelles.

## Les cases qu'un membre voit

```php
foreach ($advent->boxes($calendar, $member) as $box) {
    $box->day; $box->date; $box->state; $box->window;
    $box->surprise;  // only when opened
    $box->openedAt;  // only when opened
}
```

`boxes()` répond chaque case par jour ; `box()` en répond une. Un `BoxState` est :

| État | Quand |
|---|---|
| `Locked` | Son jour n'est pas venu |
| `Openable` | Sa fenêtre contient le moment ; `isOpenable()` est vrai |
| `Opened` | Le membre l'a ouverte : sa surprise et son moment sont montrés |
| `Missed` | Sa fenêtre a pris fin avant que le membre l'ouvre |

Une case non ouverte ne porte jamais sa surprise, de sorte qu'une page ne peut pas révéler ce que réservent les jours à
venir.

## Célébrer une ouverture

L'ouverture d'une case est un moment qui mérite d'être souligné. La bibliothèque laisse la célébration à l'application :
un écouteur de `BoxOpened`, ou la page qui reçoit `first` vrai, lance l'animation qu'offre le kit d'interface du site —
confettis, feux d'artifice — et respecte le choix de mouvement réduit du membre. La bibliothèque n'ajoute ni animation ni
son.

## Effacement

`$advent->erase($member)` supprime les ouvertures du membre dans tous les calendriers et répond combien il y en avait. Ce
que l'écouteur a accordé vit dans l'application, qui l'efface avec le reste des données du membre.
