<!-- phil-dev source-sha256=937bfc055366d82e8f67a7ab5069645510ae8964a2fe1e65769a382f22630526 -->
# Formats et règles

## Un format

Un `Format` déclare les règles selon lesquelles un paquet est construit. L'application donne ses formats ; la
bibliothèque ne fait que les appliquer.

| Règle | Valeur | Par défaut |
|---|---|---|
| `code`, `name` | Le code du format et le nom montré aux gens | Obligatoires |
| `minSize` | Le plus petit paquet, de 0 à 100 000 cartes | 1 |
| `maxSize` | Le plus grand paquet, du plus petit (au moins 1) à 100 000 cartes, ou null pour aucune limite | null |
| `copies` | Les exemplaires permis de chaque carte qui n'en fixe pas elle-même, de 1 à 999 | 999 |
| `banned` | Les cartes que le format bannit, chacune une fois, jusqu'à 10 000 | Aucune |
| `restricted` | Les cartes que le format restreint, chacune avec les exemplaires permis, de 1 à 999, jusqu'à 10 000 | Aucune |
| `tabooLists` | Des listes tabou datées, chaque code et chaque jour une fois, jusqu'à 100 | Aucune |

Une `Card` peut fixer son propre nombre d'exemplaires, qui remplace celui du format : `new Card('path', 'Forest Path',
copyLimit: 20)` pour une carte de base dont on joue n'importe quel nombre, `copyLimit: 1` pour une carte unique.

| Valeur | Forme |
|---|---|
| Le code d'un format ou d'une liste tabou | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre |
| L'identifiant d'une carte ou d'un paquet | De 1 à 255 lettres ASCII, chiffres, `_`, `.`, `:`, `/` ou `-`, commençant par une lettre ou un chiffre |
| Un nom | De 1 à 200 caractères UTF-8, sans caractères de contrôle ni espaces en bordure |

## Listes tabou

Une `TabooList` bannit ou restreint des cartes à partir d'un jour : le jour où elle prend effet, puis ses propres
cartes bannies et restreintes. Un jour donné, la liste tabou qui s'applique est la plus récente dont le jour est ce
jour-là ou avant ; elle remplace les listes tabou qui la précèdent, et s'ajoute aux listes propres du format. Une carte
que la liste tabou restreint prend le nombre de la liste tabou à la place de la restriction du format pour la même
carte.

Le jour d'une liste tabou est une date, gardée à minuit UTC. Le jour où un paquet est vérifié se prend dans le fuseau
horaire du moment donné : 22 h le 30 septembre à Montréal est encore le 30 septembre, quelle que soit l'heure en UTC.

## Les règles d'un jour

`Format::rulingOn($day)` donne la `Ruling` de ce jour : le format et la liste tabou qui s'applique, ou aucune. Pour
chaque carte, elle dit :

- `bannedBy($card)` : ce qui la bannit — `the format "league"`, `the taboo list "autumn" from 2026-10-01` —, ou null ;
- `copyLimit($card)` : les exemplaires permis — aucun quand la carte est bannie, sinon le nombre propre à la carte ou
  celui du format, abaissé par une restriction ; une restriction ne relève jamais une limite ;
- `restrictedBy($card)` : ce qui la restreint en deçà de son propre nombre ou de celui du format, ou null.

## Lire un format

`Format::fromArray()` lit le tableau qu'écrit `toArray()`, et `fromJson()` lit du JSON d'au plus 1 Mio, imbriqué sur au
plus 15 niveaux. Les deux refusent une clé inconnue, une clé manquante et une valeur du mauvais type avec `InvalidDeck`,
jamais avec une autre exception.

```json
{
    "code": "league",
    "name": "League",
    "min_size": 40,
    "max_size": 60,
    "copies": 3,
    "banned": ["old-relic"],
    "restricted": {"storm-call": 1},
    "taboo_lists": [
        {"code": "autumn", "effective_from": "2026-10-01", "banned": ["night-owl"], "restricted": {"ember-fox": 2}}
    ]
}
```
