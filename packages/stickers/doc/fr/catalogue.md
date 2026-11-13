<!-- phil-dev source-sha256=2da1bc521d02162a4df6ee5ab12f66ab6d09684f956283bfa8aa957c3244e17c -->
# Le catalogue

## Autocollants

Un `Sticker` contient :

| Champ | Forme |
|---|---|
| `code` | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre |
| `image` | Une référence à l'image, de 1 à 2 048 octets sans espace : un chemin de ressource, une clé de la médiathèque, une adresse |
| `alt` | L'alternative textuelle, obligatoire : de 1 à 250 caractères, sans espaces en périphérie, sans caractère de contrôle |
| `licence` | Facultative : de 1 à 100 caractères, comme un identifiant SPDX, `CC-BY-4.0` |
| `attribution` | Facultative : de 1 à 500 caractères, le crédit que demande la licence |
| `retired` | Un autocollant retiré reste chez ses propriétaires et là où il est placé, mais n'est plus placé |

La bibliothèque ne lit, ne stocke ni ne vérifie jamais l'image : l'application résout la référence et sert le fichier.
Une référence qui commence par `javascript:`, `vbscript:`, `data:` ou `file:` est refusée, de sorte qu'une référence
d'image ne devienne jamais un script, des données en ligne ou un fichier local dans un gabarit.

Écrivez l'alternative textuelle pour quelqu'un qui ne voit pas l'image : ce qu'elle montre, pas son nom de fichier —
*Un dragon rouge endormi, roulé en boule*, jamais *dragon.webp*.

`Sticker::retire()` donne le même autocollant, retiré ; le catalogue le garde pour que ses placements soient toujours
dessinés.

## Le port du catalogue

`Contract\StickerCatalog` donne un autocollant par son code et chaque autocollant dans l'ordre du catalogue.
`Store\InMemoryStickerCatalog` les prend tous d'un bloc, depuis la configuration de l'application, et refuse un code
donné deux fois.

## Accorder et reprendre

`Stickers::grant($holder, $code)` donne un autocollant du catalogue à un détenteur et répartit
`Event\StickerGranted` ; elle retourne faux quand il le possède déjà, et refuse un autocollant qui n'est pas dans le
catalogue. Un détenteur possède chaque autocollant une seule fois.

`Stickers::revoke($holder, $code)` le reprend, supprime tous les placements que le détenteur en a faits, et répartit
`Event\StickerRevoked` avec le nombre supprimé. `Stickers::owned($holder)` liste ce qu'un détenteur possède, dans
l'ordre où cela lui a été donné, sans les autocollants sortis du catalogue.

Un détenteur est un `ResourceReference` à identifiant stable — `member:1042` — : un identifiant contenant `@`, brut ou
encodé en pourcentage, est refusé, de sorte qu'une adresse courriel ne devienne jamais la clé de ce qu'un membre
possède.
