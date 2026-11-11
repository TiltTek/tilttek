<!-- phil-dev source-sha256=576abc8ea497a7491a284bc6fe34f2442dcc06e8dd75213f8862ea93cb4ce961 -->
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
