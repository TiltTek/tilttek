<!-- phil-dev source-sha256=c205040dc97768426da7eae8f49e6348a511a1087909a5ac2d100e8709507387 -->
# La règle de couleur

On ne pose jamais métal sur métal, ni couleur sur couleur. Une fourrure se pose sur l'un ou l'autre, et rien ne se pose
sur son propre émail.

```php
use Tilttek\Heraldry\TinctureRule;

$rule = new TinctureRule();
$rule->respects($coat);                // true or false
foreach ($rule->violations($coat) as $violation) {
    $violation->describe();            // "The ordinary lies or on argent: metal on metal."
}
```

## Où la règle lie

| Quoi | Se pose sur |
|---|---|
| La pièce, bordure comprise | un champ uni |
| Les meubles sur le champ, à côté d'une pièce ou seuls | un champ uni |
| Les meubles sur la pièce | la pièce |

## Où elle ne lie pas

- Tout ce qui se pose sur un **champ divisé** touche ses deux émaux : un champ de métal et de couleur accepte tout.
- Une **brisure** se pose sur tout et n'est pas vérifiée.

`Violation` nomme la couche (`piece` ou `charges`), son émail et le fond sur lequel elle se pose.
`Tincture::contrastsWith()` est la règle pour une paire.
