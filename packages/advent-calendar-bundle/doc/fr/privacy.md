<!-- phil-dev source-sha256=466988af5e05de7372886dede1c0afd3b7968ab538283ad1d9325945bd06ebcb -->
# Vie privée

## Ce qui est gardé d'un membre

Pour chaque case qu'un membre ouvre, le bundle garde le calendrier, la référence du membre, le jour et le moment de la
première ouverture. La référence est l'identifiant stable de l'application, `member:44` : jamais un nom ni une adresse
courriel, et le bundle en refuse une dont l'identifiant contient `@`. Rien n'est gardé d'un visiteur. Aucun classement
de qui a ouvert en premier n'est fait.

Ce que les surprises ont accordé — points, badges, codes — vit dans l'application, qui l'exporte et l'efface avec le
reste des données du membre.

## Export et effacement

Avec `phil/privacy-bundle` installé, `AdventCalendarPrivacy` participe aux demandes d'accès et d'effacement d'un membre,
sous le module `tilttek_advent_calendar`. La personne concernée correspond à la référence du genre
`privacy.member_kind`, `member` par défaut.

- L'export contient les cases que le membre a ouvertes, dans chaque calendrier — celles d'un calendrier retiré depuis
  aussi — avec le moment de chacune.
- L'effacement supprime ces ouvertures. Le membre peut rouvrir les cases tant que leurs fenêtres durent, et
  l'application en est informée de nouveau par `BoxOpened` : une application qui garde ses octrois sous la clé
  `idempotencyKey()` n'accorde rien deux fois.

## Mode *worker*

Aucun service ne garde les données d'un membre d'une requête à l'autre : les premières ouvertures gardées pour la
célébration et les ouvertures en mémoire sont vidées après chaque requête.
