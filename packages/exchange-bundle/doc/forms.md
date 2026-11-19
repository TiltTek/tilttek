# Forms

When `symfony/form` is installed, three form types are registered. Their data are plain arrays; the application adds
its own key and calls the service.

| Type | Fields | Data |
|---|---|---|
| `RedeemRewardType` | `reward`, a choice among the catalogue's rewards shown with their price — or those of the `rewards` option —; `quantity`, 1 by default | `['reward' => 'seat', 'quantity' => 1]` |
| `TransferPointsType` | `recipient`, as the application names its members; `amount` | `['recipient' => 'ada', 'amount' => 50]` |
| `OfferType` | `offered` and `requested`, checkboxes among the `offered_choices` and `requested_choices` options — labels to item references —; `offered_points` and `requested_points`, 0 by default | `['offered' => ['card:12'], 'requested' => ['card:31'], 'offered_points' => 0, 'requested_points' => 0]` |

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

The labels are in English; the application overrides them through the form's options or its translations.
