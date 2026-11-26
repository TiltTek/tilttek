<!-- phil-dev source-sha256=100bd04d92f1c24db7db36718e68ec9803f887ae536ec72ff7e0066fb7e27a80 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\SessionPrepException` : attrapez-la pour les attraper toutes.

| Exception | Levée quand |
|---|---|
| `Exception\InvalidPreparation` | Une valeur sort de sa forme ou de ses bornes — un identifiant, une clé, un texte, une liste trop longue, une clé en double dans une liste, une version sous 0, une limite, un élément coché dans un gabarit —, une scène met en scène un personnage ou un lieu que sa préparation ne contient pas, une partie doit passer avant elle-même, ou aucune partie n'a la clé qu'un appel nomme. |
| `Exception\PreparationRefused` | Le service n'autorise pas le changement : une nouvelle préparation n'est pas un brouillon à la version 0 ou a l'identifiant d'une préparation gardée, le membre n'est pas le maître de jeu, un changement déplace une préparation dans une autre campagne, la préparation est fermée, un élément exigé n'est pas coché quand on la marque prête, ou une préparation est reportée dans elle-même ou dans celle d'une autre campagne. |
| `Exception\PreparationNotFound` | Aucune préparation n'a l'identifiant. |
| `Exception\PreparationConflict` | La préparation a changé depuis qu'elle a été lue, ou une nouvelle a l'identifiant d'une préparation gardée : lisez-la de nouveau et refaites le changement. |

Rien n'est gardé quand une exception est levée.
