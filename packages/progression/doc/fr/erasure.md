<!-- phil-dev source-sha256=ddfb3907d60868741a7b858888f72b3c97672d4fb891f6f549cb0c14bb2e7979 -->
# Effacement

`$progression->erase($member)` retire :

- les gains dont le sujet est le membre, et les gains que les gestes du membre ont apportés à un objet (l'expérience
  qu'un personnage a gagnée grâce à ses séances) ;
- les jalons qu'il a atteints, et ceux que ses gestes ont atteints pour un objet ;
- ses gestes de série, ses unités gelées et ses états.

Il retourne un `Erasure` avec les trois décomptes. Le journal d'activité est effacé par `tilttek/activity` ;
l'expérience d'un objet gagnée grâce aux gestes d'autres membres reste. `tilttek/progression-bundle` enregistre cet
effacement comme module de `phil/privacy-bundle`.
