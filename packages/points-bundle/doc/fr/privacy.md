<!-- phil-dev source-sha256=90ded094c231d9127c47d02645aeb10f81b314e99a172dd8aa2815d3abad85d9 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, le bundle enregistre `PointsPrivacy` comme exportateur et comme effaceur,
module `tilttek_points`. Une personne concernée `44` représente le membre `member:44`, ou le type que nomme
`privacy.member_kind`.

- **L'exportation** énumère chaque mouvement du compte du membre, dans chaque devise, en valeurs simples : position,
  clé, genre, devise, débit, crédit, montant, le moment où il compte, quand il a été écrit, sa cause et le mouvement
  qu'il défait. Elle est lue `batch_size` mouvements à la fois.
- **L'effacement** supprime les mouvements entre le membre et les comptes propres au registre, écrit `erased:member` à
  la place du membre dans les transferts avec d'autres membres (dont les points ne changent pas), et supprime les
  lignes de verrou du membre. Le rapport indique `tilttek_points_movement` comme supprimée et ses colonnes `debit` et
  `credit` comme caviardées.

L'activité du membre est exportée et effacée par le module propre à `tilttek/activity-bundle`. Construisez les clés des
mouvements à partir d'identifiants qui ne disent rien du membre : elles restent dans les transferts des autres membres.
