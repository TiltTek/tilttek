<!-- phil-dev source-sha256=d552ed2fdad302913e6c132ec6985f025d58ad9f75683f73e0f800177bbd3449 -->
# Dépenser et donner

Chaque opération prend la clé que lui donne l'application ; le même appel fait deux fois ne déplace les points qu'une
fois.

## Attributions et révocations

```php
$points->grant($member, 100, 'contest:2026-spring:44', source: 'contest:2026-spring');
$points->revoke($member, 20, 'fix:2026-11-07:44');
```

Une attribution émet des points à partir du compte d'émission d'une source (`manual` par défaut) ; une révocation les y
renvoie. Une révocation est une correction, pas un échange : elle est écrite telle quelle, même quand le solde passe
sous zéro.

## Dépenses et remboursements

```php
$points->spend($member, 300, 'order:2026-0193:redeem', 'order:2026-0193');
$points->refund('order:2026-0193:redeem');
```

Une dépense déplace des points vers un compte de récompense nommé par ce qu'ils paient. **Elle ne rend jamais un solde
négatif** : les crédits du membre qui doivent expirer expirent d'abord, puis le solde est vérifié et le mouvement écrit
en une seule étape, où aucun autre processus ne peut s'intercaler. Un solde qui ne couvre pas le montant lève
`InsufficientPoints`.

Un remboursement rend les points d'une dépense, une seule fois, sous forme d'un nouveau crédit dont la durée de vie
commence au remboursement. Une réservation est une dépense remboursée quand elle est annulée.

## Transferts

```php
$points->transfer($giver, $receiver, 10, 'gift:2026-11-07:44:12');
```

Un transfert d'un membre à un autre, quand l'application le permet, est vérifié comme une dépense. La bibliothèque ne
décide pas qui peut donner à qui, ni combien : l'application le décide, avant de l'appeler.

## Devises

Chaque opération prend le code d'une devise, celle par défaut quand aucune n'est nommée ; un code inconnu lève
`UnknownCurrency`. Les points d'une devise ne deviennent jamais des points d'une autre.
