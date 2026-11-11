<!-- phil-dev source-sha256=f3d6358e27221ca11d7ff974e568450bcfe2b3bf3768474d18b55fc02d1bb91a -->
# Commandes

Enregistrées quand `symfony/console` est installé.

## `tilttek:points:expire`

```sh
bin/console tilttek:points:expire
```

Fait expirer les crédits dont la durée de vie est terminée, pour chaque membre et dans chaque devise qui a une durée de
vie, et affiche combien de points ont expiré. Planifiez-la une fois par jour ; de toute façon, une dépense et un
transfert font d'abord expirer les crédits du payeur.

## `tilttek:points:history`

```sh
bin/console tilttek:points:history member:44
bin/console tilttek:points:history member:44 --currency=stars --after=120 --limit=100
```

Affiche le solde du membre dans la devise (celle par défaut quand aucune n'est nommée) et ses mouvements après une
position, au plus 1 000 : position, moment, genre, points signés, devise et clé. Une référence de membre invalide, une
devise inconnue ou une limite au-dessus de 1 000 affiche une erreur et échoue.
