<!-- phil-dev source-sha256=ba955f35af81eed4c5a6ac2213c0e78b227a3cbfc18ac8cf1ba242f6311100e6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/random-character` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `CreationRules`, `Stats`, `StatMethod`, `Field` et `FieldKind` : les règles de création d'un système, avec jusqu'à 32
  attributs remplis chacun par un jet, par une série tirée ou par une série fixe distribuée au hasard, et jusqu'à 64
  champs tirés d'un générateur de `tilttek/generator` ou lancés avec `tilttek/dice`, jusqu'à 16 valeurs chacun, uniques
  sur demande.
- Des générateurs nommés par les champs précédents : `names.{lineage}` et `names.{lineage.key}`, vérifiés quand les
  règles sont construites et quand le personnage est tiré.
- `RulesCatalogue` : jusqu'à 1 000 règles, par clé et par système.
- `CharacterRoller` : des règles vérifiées par rapport aux systèmes de `tilttek/rpg-systems` et aux générateurs, chaque
  problème listé ; des personnages tirés dans les bornes des attributs du système, à partir d'un germe donné ou tiré,
  chaque partie à partir d'un germe qui lui est propre.
- `RandomCharacter` : le personnage avec son germe, ses attributions chacune une fois, et ses modifications marquées par
  valeur.
- `Contract\CharacterObserver` et `Event\CharacterRolled` : chaque tirage annoncé par les clés des règles et du système.
- `Format\RulesReader` et `Format\Syntax` : les documents YAML et JSON du format `tilttek-random-character/1`, chaque
  refus à sa place.
- Des exceptions sous une seule interface `RandomCharacterException`.
- L'exemple `examples/lanterns.yaml` et ses tables, une cible de fuzzing du lecteur et des bancs d'essai d'un tirage.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
