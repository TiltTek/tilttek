<!-- phil-dev source-sha256=b2d654f9cfbaf733074bd80d4e50a41ec7679f34855a6d1192423d5884c21e9c -->
# Expiration

Une devise créée avec une durée de vie (`new Currency('stars', 'P1Y')`) fait expirer chaque crédit à un membre ce temps
après son moment : les points gagnés, attribués, remboursés ou reçus. Une devise sans durée de vie n'expire jamais.

## Premiers entrés, premiers sortis

`Lots` suit ce qui reste de chaque crédit, les mouvements étant appliqués dans l'ordre où le registre les a écrits :

- un débit qui nomme un crédit (le renversement d'un gain, l'expiration d'un crédit) prend d'abord dans ce crédit ;
- chaque débit prend ensuite dans les plus anciens crédits encore vivants à son moment, puis dans les plus anciens des
  autres ;
- ce qu'aucun crédit ne couvre est une dette, que les crédits suivants paient avant d'ouvrir leur propre lot.

## Faire expirer

```php
$points->expire($member);          // the default currency
$points->expire($member, 'stars');
$points->expireAll();              // every member, every currency with a lifetime
```

Chaque crédit dont la durée de vie est terminée et à qui il reste des points expire par un mouvement
`#expire:<credit key>` vers `expiry:expired`, daté à la fin de sa durée de vie. Faire expirer de nouveau ne trouve plus
rien à faire expirer. `expireAll()` est la tâche que lance un planificateur ; elle examine un par un les membres
crédités avant la date limite de chaque devise. Une dépense et un transfert font d'abord expirer les crédits du payeur :
un membre ne dépense ainsi jamais des points déjà expirés.

## Dépenses au même moment

Chaque expiration est écrite par `Ledger::appendAfter()` : seulement si le compte n'a aucune entrée plus récente que
celles d'où ses lots ont été lus, en une seule étape qu'une dépense du même compte ne peut pas interrompre. Une dépense
glissée entre les deux (une dépense qui a pris des points à un crédit une seconde avant son expiration) renvoie
l'expiration relire les lots : les mêmes points ne sont jamais pris deux fois, et une expiration ne fait jamais passer
le solde sous zéro. Après `Points::EXPIRY_ATTEMPTS` tentatives qui ont chacune trouvé le compte modifié,
`LedgerContention` parvient à l'appelant, et l'expiration peut être redemandée.

## Points de reprise

L'expiration ne relit pas chaque fois tout l'historique du compte. Une fois les entrées appliquées, elle garde un
`LotsCheckpoint` dans le registre (les lots auxquels il reste des points, la dette, et la position où ils en sont) ; la
prochaine expiration, dont celle de chaque dépense et de chaque transfert, ne lit que les entrées écrites après cette
position. La fin de chaque lot gardé est recalculée à partir de la durée de vie de la devise. Quand les lots ne
correspondent plus au solde du compte (un mouvement antérieur à la position est apparu en retard, ou les mouvements
dérivés ont été reconstruits), l'expiration relit tout l'historique et garde un nouveau point de reprise. Une
reconstruction et l'effacement d'un membre suppriment les points de reprise.
