<!-- phil-dev source-sha256=95fdbaf4d90bb5ca30723e50f44f0929219a5a5d2f25a92fa2fdb097801ab0b8 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, `ProgressionPrivacy` est enregistré comme exportateur et comme effaceur sous
le module `tilttek_progression`. La personne concernée est un membre, référencé sous `privacy.member_kind` (`member`).

**L'exportation** énumère les gains du membre (piste, règle, sujet, membre, activité, montant, moment, saison), les
jalons qu'il a atteints et ses séries qui ont déjà compté au moins une unité (les séries en cours et meilleure) ; elle
est vide quand il n'y en a aucun.

**L'effacement** supprime les gains du membre et ceux que ses gestes ont apportés à un objet, ses jalons, ainsi que ses
gestes de série, ses unités gelées et ses états ; le rapport indique les cinq tables comme supprimées. Son journal
d'activité est effacé par le module propre à `tilttek/activity-bundle`.
