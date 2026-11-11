<!-- phil-dev source-sha256=235af91e833126c20c7442a32d2c59da997652e7c646efa63458b249497dc619 -->
# Effacement

`ExchangeErasure::erase($member)` efface les échanges d'un membre :

- ses **réservations** sont supprimées ;
- ses **offres ouvertes** sont retirées, ce qui libère les objets qu'elles retenaient ;
- `erased:member` prend sa place dans les **offres** et les **transferts** qu'il a eus avec d'autres membres ; ceux-ci
  restent les échanges et les points de ces membres.

Elle retourne un `Erasure` : les réservations supprimées, les transferts et les offres modifiés. Elle efface dans les
stockages qu'on lui donne : une application sans transferts ni échanges ne donne que les réservations. Les points
eux-mêmes (les dépenses des réservations, les transferts du grand livre) sont effacés par `tilttek/points`.
