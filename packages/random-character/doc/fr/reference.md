<!-- phil-dev source-sha256=d1b22257172fb7b573f6b1b1b558f1e3c85871ae2d8aa71e97fea4ae584926b4 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Règles

| Type | Rôle |
|---|---|
| `CreationRules` | Les règles d'un système : leur clé, la clé du système, les statistiques et les champs dans l'ordre. |
| `Stats` | Les attributs que les règles remplissent, la méthode, et le lancer ou les valeurs fixes. |
| `StatMethod` | Chacun dans l'ordre, tableau lancé, ou tableau fixe. |
| `Field` | Un champ tiré d'un générateur, nommé avec des marqueurs, ou lancé avec des dés ; une valeur ou jusqu'à 16. |
| `FieldKind` | Tiré ou lancé. |
| `RulesCatalogue` | Les règles d'une application, par clé et par système. |

## Tirage

| Type | Rôle |
|---|---|
| `CharacterRoller` | Le service : vérifie les règles contre les systèmes et les générateurs, et tire des personnages à partir d'une graine donnée ou tirée. |
| `RandomCharacter` | Un personnage : règles, système, graine, statistiques, champs, attributions et valeurs modifiées à la main. |
| `Contract\CharacterObserver` | Informé de chaque personnage tiré. |
| `Event\CharacterRolled` | Un tirage, par les clés des règles et du système, et si la graine a été donnée. |

## Format

| Type | Rôle |
|---|---|
| `Format\RulesReader` | Lit un document YAML ou JSON du format `tilttek-random-character/1` en un catalogue. |
| `Format\Syntax` | YAML ou JSON, et la syntaxe d'un fichier selon son extension. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\RandomCharacterException` | L'interface que chaque exception de la bibliothèque implémente. |
| `Exception\InvalidRules` | Des règles qui ne peuvent être ni construites ni tirées. |
| `Exception\InvalidDocument` | Un document qui ne peut pas être lu, à son emplacement. |
| `Exception\InvalidSeed` | Une graine qui ne peut pas rejouer un personnage. |
| `Exception\InvalidEdit` | Un changement qu'un personnage ne peut pas accepter. |
| `Exception\UnknownRules` | Aucun jeu de règles de cette clé. |
| `Exception\RollFailed` | Un tirage qui n'a pas pu donner de personnage. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Check` | Les vérifications des clés, des clés de champs et des notations de dés. |
| `Internal\Fields` | Un dictionnaire d'un document, lu champ par champ. |
