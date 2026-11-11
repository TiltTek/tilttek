<!-- phil-dev source-sha256=76fd9f46d2fc1b6ce02f8f33b721ff944612df08edab55096702fccc9dc00190 -->
# Déplacer des points

`Tilttek\Points\Points` est un service public, injectable automatiquement :

```php
$points->balance($member);                                    // the default currency
$points->history($member, 'stars', after: 0, limit: 50);
$points->grant($member, 100, 'contest:2026:44', 'contest:2026');
$points->spend($member, 300, 'reward:12:44', 'reward:12');
$points->refund('reward:12:44');
$points->transfer($giver, $receiver, 10, 'gift:2026-11-07:44:12');
```

- **Les clés** appartiennent à l'application : le même appel fait deux fois ne déplace les points qu'une fois. Une clé
  qui commence par `#` est refusée, puisque les clés propres à la bibliothèque commencent ainsi.
- **Une dépense ou un transfert** fait expirer les crédits échus du payeur, puis vérifie le solde et écrit le mouvement
  sous verrou ; `InsufficientPoints` indique le solde et le montant.
- **Un remboursement** rend une dépense une seule fois, sous forme d'un nouveau crédit.
- **Qui peut donner à qui**, et combien coûte une récompense, sont des décisions de l'application, prises avant
  l'appel.

La documentation de la bibliothèque explique en entier le registre, les genres de mouvements et l'expiration.
