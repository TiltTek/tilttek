<!-- phil-dev source-sha256=c72f3d7e73bf3b542014958c7393c7b3af88c36972be2f32e6f85bcee366d296 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\RpgSystems\Exception\RpgSystemsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidRecord` | Une clé, une locale, un texte, une provenance, les bornes d'un attribut, une expression de dés, un système ou un profil est refusé à sa construction | `The attribute "grit" has a minimum, 7, above its maximum, 6.` |
| `InvalidDocument` | Un document, un extrait ou un paquet de données ne peut pas être lu — un document hors du paquet ou modifié après son manifeste, un paquet installé sans répertoire ou dont le manifeste déclare un autre nom y compris — : ni YAML ni JSON, trop long, un champ inconnu ou du mauvais type, une licence refusée ; le message nomme l'endroit | `attributes[0].minimum: a whole number is expected.` |
| `UnknownRecord` | Un système, un attribut, une expression de dés ou un terme est demandé par une clé qu'aucun n'a, une localisation en nomme un, ou un paquet de données qui n'est pas installé est chargé | `The system "lanterns" has no term with the key "range.far".` |

`Package\DataPackageWriter::write()` lève l'`UnexpectedValueException` propre à PHP quand son répertoire n'existe pas
ou qu'un fichier ne peut pas être écrit.

Un enregistrement refusé pendant la lecture d'un document est rendu comme une `InvalidDocument` avec son endroit,
l'`InvalidRecord` en étant l'exception précédente.
