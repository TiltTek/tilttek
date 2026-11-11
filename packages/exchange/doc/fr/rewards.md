<!-- phil-dev source-sha256=27a493b5c4dddbea13ebb766fff4820ac1dc354c28ad1ff48805532238fa1ebf -->
# Récompenses

## Le catalogue

Une `Reward` est ce qu'un membre obtient contre des points. Elle est nommée par un code de 1 à 100 lettres minuscules,
chiffres, `_`, `.` ou `-` :

| Champ | Sens |
|---|---|
| `price` | Points par unité, de 1 au maximum que déplace un mouvement du grand livre. |
| `currency` | La devise du prix ; la devise par défaut de `tilttek/points` si elle est nulle. |
| `stock` | Toutes les unités que la récompense aura jamais ; illimité s'il est nul. Les unités réservées et livrées l'épuisent, les unités d'une réservation annulée ou expirée reviennent. |
| `availableFrom`, `availableUntil` | La période de disponibilité, début inclus, fin exclue ; ouverte du côté laissé nul. |
| `perMember` | Combien d'unités un membre peut détenir, réservées ou livrées ; illimité s'il est nul. |
| `requires` | Des conditions que le membre remplit, toutes (`badge:helper`, `level:5`), vérifiées par les `Requirements` de l'application. |

Un `Catalog` donne une récompense par son code et toutes les récompenses triées par code. `InMemoryCatalog` se
construit à partir de récompenses ou d'une configuration, et un `WritableCatalog` enregistre et retire aussi des
récompenses ; retirer une récompense laisse ses réservations telles quelles.

```php
$catalog = InMemoryCatalog::fromArray([
    'discount-10' => ['price' => 100],
    'seat' => ['price' => 250, 'stock' => 30, 'per_member' => 1, 'available_until' => '2026-12-01', 'requires' => ['badge:helper']],
]);
```

Les conditions sont des noms que l'application choisit. Elle y répond à partir de ses badges, de ses niveaux ou de
toute autre chose, par `Requirements::meets($member, $requirement)`. Sans cela, `NoRequirements` n'en remplit aucune :
personne n'obtient une récompense dotée de conditions tant que l'application ne fournit pas les siennes.

## Réserver, confirmer, annuler

`Redemptions::reserve($member, $code, $key, $quantity)` vérifie la période et les conditions, puis écrit la
réservation ; le stockage vérifie ses unités selon le stock et la limite du membre dans la même étape. Elle dépense
ensuite les points dans le grand livre sous `exchange:reservation:` et la clé, vers le compte de rachat `reward:` et le
code. Quand les points ne peuvent pas être payés, la réservation est supprimée, ses unités redeviennent libres et
`InsufficientPoints` parvient à l'appelant.

L'application livre ensuite, à partir de l'événement `RewardReserved` ou tout de suite, et appelle `confirm($key)` :
les points restent dépensés. Ou elle appelle `cancel($key)` : les points reviennent au membre par un remboursement du
grand livre, et les unités au stock. Une réservation confirmée ne peut pas être annulée, ni une réservation annulée
confirmée.

```php
$reservation = $redemptions->reserve($member, 'seat', 'order:2026-0193:seat');

try {
    $tickets->issue($reservation);
    $redemptions->confirm('order:2026-0193:seat');
} catch (TicketingDown) {
    $redemptions->cancel('order:2026-0193:seat');
}
```

## Le même appel deux fois

La clé permet d'exécuter chaque étape deux fois sans danger. La même clé ne réserve qu'une fois et retourne la
réservation, même si sa récompense a quitté le catalogue depuis ; une réservation interrompue avant l'écriture de sa
dépense est payée quand on la redemande. La même clé pour un autre membre, une autre récompense ou une autre quantité
est un `ExchangeConflict`. Une réservation confirmée confirmée de nouveau, une réservation annulée annulée de nouveau,
sont retournées telles quelles.

## Expiration

Une réservation attend sa confirmation pendant sa durée de vie, 30 minutes à moins que `Redemptions` ne reçoive une
autre durée ISO 8601. `expireDue()` fait expirer les réservations qui ont dépassé leur durée de vie et rend leurs
points : c'est la tâche qu'exécute un planificateur. Une confirmation ou une annulation qui arrive après la durée de
vie fait d'abord expirer la réservation, puis est refusée avec `ExchangeClosed`.

## Avant de réserver

`refusal($member, $code, $quantity)` dit pourquoi un membre ne peut pas réserver maintenant (la période, une condition,
le stock, la limite), ou null, le solde mis à part ; `remaining($code)` donne les unités restantes, ou null pour une
récompense illimitée. Une page de récompenses les affiche ; la réservation vérifie de nouveau au moment d'écrire.
