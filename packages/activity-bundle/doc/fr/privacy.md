<!-- phil-dev source-sha256=5d804f8e51166c49ee9596ac7f31375ba4cea5a6416538650983a7fcb727a547 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, `ActivityPrivacy` est enregistré comme son exportateur et son effaceur,
module `tilttek_activity`. La personne concernée est un membre : la personne `44` est la référence `member:44`, la
sorte venant de `tilttek_activity.privacy.member_kind`.

- **L'export** énumère chaque entrée des gestes du membre — position, sorte, moment de l'écriture, et l'activité : clé,
  verbe, moment, objet, quantité, contexte —, lues par lots de `batch_size`. Un membre sans activité n'exporte rien.
- **L'effacement** supprime les entrées des gestes du membre et oublie le membre comme objet des gestes des autres
  membres, qui restent. Son rapport nomme `tilttek_activity_entry` (supprimée) et `tilttek_activity_entry.object`
  (caviardée). Il peut s'exécuter de nouveau sur un membre déjà effacé.

Ce que d'autres paquets ont dérivé de l'activité — points, badges, progression — est effacé par leurs propres
effaceurs.
