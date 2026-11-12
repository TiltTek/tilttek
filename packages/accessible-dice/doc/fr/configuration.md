<!-- phil-dev source-sha256=fbcbed2005603d6c33d1fdf3ab01c7614918e067436c7bbb9542ba3dc3f3cc5e -->
# Configuration

```yaml
# config/packages/tilttek_accessible_dice.yaml
tilttek_accessible_dice:
    sides: [4, 6, 8, 10, 12, 20, 100]
    custom_dice: ~
    politeness: polite
    dice_translation_domain: tilttek_dice
```

| Clé | Valeur par défaut | Ce qu'elle règle |
|---|---|---|
| `sides` | `[4, 6, 8, 10, 12, 20, 100]` | Les dés standards offerts, par leur nombre de faces, chacun d'au moins 2 |
| `custom_dice` | `null` | Les dés personnalisés de `tilttek_dice` offerts, par nom ; tous si la valeur est nulle |
| `politeness` | `polite` | Comment un lecteur d'écran annonce un résultat : `polite`, quand il a fini ce qu'il dit, ou `assertive`, tout de suite |
| `dice_translation_domain` | `tilttek_dice` | Le domaine de traduction des libellés des faces : celui de `tilttek_dice` |

Le nombre maximal de dés qu'un seul choix lance est la limite `dice` de `tilttek_dice`. Un dé personnalisé nommé dans
`custom_dice` que `tilttek_dice` ne déclare pas échoue quand le service est construit.

Les services ne gardent aucun état entre les requêtes : ils sont sûrs dans un *worker*.
