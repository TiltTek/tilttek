<!-- phil-dev source-sha256=bc4912273aee326be42285f200ace3766f32a79560324957db242260d5bf6edf -->
# Vie privée

Quand `phil/privacy-bundle` est installé, `ExchangePrivacy` répond à ses demandes d'accès et d'effacement sous le
module `tilttek_exchange`. Une personne concernée `44` est le membre `member:44`, ou du type que nomme
`privacy.member_kind`.

- **Exportation** : les réservations du membre et, quand ils sont activés, les transferts qu'il a donnés ou reçus et les
  offres qu'il a faites ou reçues, en valeurs simples ; une partie vide est omise.
- **Effacement** : les réservations sont supprimées ; les offres ouvertes sont retirées, ce qui libère leurs objets ;
  `erased:member` prend la place du membre dans les transferts et les offres qu'il avait avec d'autres membres, qui
  restent ceux de ces membres. Le rapport indique `tilttek_exchange_reservation` comme supprimée et les colonnes de
  membre des transferts et des offres comme caviardées.

Les points (les dépenses des réservations, les transferts du registre) sont effacés par le module de
`tilttek/points-bundle`.
