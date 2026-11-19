<!-- phil-dev source-sha256=d6c82f68d2ceebd276297418990c0c835b9779dd4f59d63a8508ae19f6ae1e5f -->
# Formulaires

Quand `symfony/form` est installé, trois types de formulaires sont enregistrés. Leurs données sont de simples
tableaux ; l'application ajoute sa propre clé et appelle le service.

| Type | Champs | Données |
|---|---|---|
| `RedeemRewardType` | `reward`, un choix parmi les récompenses du catalogue affichées avec leur prix, ou celles de l'option `rewards` ; `quantity`, 1 par défaut | `['reward' => 'seat', 'quantity' => 1]` |
| `TransferPointsType` | `recipient`, tel que l'application nomme ses membres ; `amount` | `['recipient' => 'ada', 'amount' => 50]` |
| `OfferType` | `offered` et `requested`, des cases à cocher parmi les options `offered_choices` et `requested_choices` (des libellés associés à des références d'objets) ; `offered_points` et `requested_points`, 0 par défaut | `['offered' => ['card:12'], 'requested' => ['card:31'], 'offered_points' => 0, 'requested_points' => 0]` |

```php
$form = $this->createForm(OfferType::class, null, [
    'offered_choices' => $collections->choicesOf($member),
    'requested_choices' => $collections->choicesOf($other),
]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $data = $form->getData();
    $trades->offer('trade:'.$uuid, $member, $other, array_map(ResourceReference::fromString(...), $data['offered']), array_map(ResourceReference::fromString(...), $data['requested']), $data['offered_points'], $data['requested_points']);
}
```

Les libellés sont en anglais ; l'application les remplace par les options du formulaire ou par ses traductions.
