<!-- phil-dev source-sha256=ca087872c7a0f38040f2e1b155141f3823ad14ac9258f29c5fb3b859d25169aa -->
# Transferts et échanges

Les deux sont désactivés tant que la configuration ne les active pas.

## Transferts

```yaml
tilttek_exchange:
    member_since: App\Members\JoiningDates
    transfers:
        enabled: true
        allowance: { limit: 500, period: week }
        new_member_delay: P7D
```

`Transfers` devient un service public, la commande du journal apparaît, et les garde-fous sont construits à partir de
la configuration : aucun don à soi-même, des comptes qui ont au moins le délai des deux côtés, et au plus l'allocation
par période, comptée dans `timezone`.

## Échanges

```yaml
tilttek_exchange:
    trades:
        enabled: true
        inventory: App\Collections\CardInventory
        offer_lifetime: P3D
        allowance: { limit: 10, period: day }
```

`Trades` devient un service public. L'inventaire est le service de l'application qui implémente
`Tilttek\Exchange\Trade\Inventory` sur ses collections : qui possède un objet, s'il peut changer de mains, et les
déplacements d'un échange accepté. Gardez-le sur la connexion des échanges : une acceptation s'exécute dans une
`DbalTransaction`, et les objets, les points et l'offre sont alors validés ensemble.

## Les dates d'inscription

`member_since` nomme le service de l'application qui implémente `Tilttek\Exchange\MemberSince` et qui dit quand un
membre s'est inscrit. Il est exigé dès qu'un `new_member_delay` est réglé ; un membre qu'il ne connaît pas est refusé.
