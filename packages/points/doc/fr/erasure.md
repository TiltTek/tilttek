<!-- phil-dev source-sha256=497bfde83979f62609fe231e31998dda04b404ed0f2536280acb7bf6802c7d7a -->
# Effacement

```php
$erasure = $points->erase($member);
$erasure->deleted;   // movements between the member and the ledger's own accounts
$erasure->detached;  // transfers with other members, kept
```

Effacer un membre supprime chaque mouvement entre le compte du membre et les comptes propres au registre : gains,
attributions, dépenses, expirations. Un transfert entre le membre et un autre membre est gardé, avec `erased:member`
écrit à la place du membre effacé : les points de l'autre membre ne changent pas.

Les clés des mouvements restent telles que l'application les a construites. Construisez-les à partir d'identifiants
qui ne disent rien du membre (un numéro de commande, un numéro de partie) plutôt qu'à partir d'un nom ou d'une adresse
de courriel.

Le journal d'activité est effacé par `tilttek/activity` ; les points qui en sont tirés sont effacés ici. Une
application Symfony obtient les deux par les modules de leurs bundles pour `phil/privacy-bundle`.
