<!-- phil-dev source-sha256=98b822a5dc00f8008655d24524637c37d4b7cfca4ca6b9e7911d6312487291d8 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, le bundle enregistre `CollectionsPrivacy`, le module `tilttek_collections`.
La personne concernée est un membre, référencé sous la sorte que nomme `privacy.member_kind` (`member` par défaut).

- **Export :** les exemplaires du membre, ses ensembles complétés, ses paquets ouverts avec leurs germes et leurs germes du client, et ses dés
  choisis ; rien quand il n'y en a pas.
- **Effacement :** le tout, signalé comme supprimé de `tilttek_collections_copy`, `tilttek_collections_completion`,
  `tilttek_collections_opening` et `tilttek_collections_choice`. Les exemplaires que le membre a échangés appartiennent
  à d'autres membres et restent les leurs, sans trace de leur provenance.
