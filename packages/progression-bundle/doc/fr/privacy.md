<!-- phil-dev source-sha256=ed53a49dede0b2f078f2aca2ae4dd10daa79e586e9693bcb3d916ff890c587d2 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, `ProgressionPrivacy` est enregistré comme exportateur et comme effaceur sous
le module `tilttek_progression`. La personne concernée est un membre, référencé sous `privacy.member_kind` (`member`).

**L'exportation** énumère les gains du membre et ceux que ses gestes ont apportés à un objet — un personnage — (piste,
règle, sujet, membre, activité, montant, moment, saison), les jalons qu'il a atteints et ceux que ses gestes ont
atteints pour un objet, et ses séries qui ont déjà compté au moins une unité (les séries en cours et meilleure) : ce
que l'effacement supprime. Elle est vide quand il n'y en a aucun.

**L'effacement** supprime les gains du membre et ceux que ses gestes ont apportés à un objet, ses jalons, ainsi que ses
gestes de série, ses unités gelées et ses états ; le rapport indique les cinq tables comme supprimées. Son journal
d'activité est effacé par le module propre à `tilttek/activity-bundle`.
