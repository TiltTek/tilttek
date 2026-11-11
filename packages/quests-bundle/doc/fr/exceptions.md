<!-- phil-dev source-sha256=89cbe5037724b577c432c98581960425a7339d1e201e5bd35070197c943b34f2 -->
# Exceptions

Le bundle lance les exceptions de la bibliothèque (consultez sa documentation), ainsi que :

| Lancée | Quand |
|---|---|
| `InvalidConfigurationException` | Une option sort de ses valeurs : un dépôt ou un catalogue inconnu, un nom vide, une durée de vie d'offre qui n'est pas une durée ISO 8601 non nulle, des quêtes configurées avec `catalog: doctrine` |
| `InvalidQuest` à la compilation | Une quête configurée, une récurrence ou le fuseau horaire est refusé, avec le message de la bibliothèque |
| `InvalidQuest` venant de `DbalCatalog` | Une définition de la table n'est pas du JSON ou pas une liste de valeurs, ou un enregistrement ou un retrait briserait une chaîne |
| `UnexpectedValueException` | Une ligne contient une valeur du mauvais type |
