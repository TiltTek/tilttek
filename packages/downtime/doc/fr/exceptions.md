<!-- phil-dev source-sha256=fbaacaa3208e45f99bdf4a7d9cfb3b4774e2901ad4abff4d5f74223832620f56 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\DowntimeException` : attrapez-la pour les attraper toutes.

| Exception | Levée quand |
|---|---|
| `Exception\InvalidDowntime` | Une valeur sort de sa forme ou de ses bornes — un identifiant, une clé, un texte, une expression de dés, un montant, un changement, une plage, une liste trop longue ou vide, une clé ou une ressource en double, une version sous 0, une limite, les jours d'une avance —, la résolution d'une activité nomme une issue qu'elle ne déclare pas, les plages d'un jet se chevauchent ou laissent un trou, les valeurs d'une entrée ne s'accordent pas avec son étape, ou le maître de jeu nomme une issue que l'activité n'a pas. |
| `Exception\UnknownActivity` | Les règles du système ne déclarent aucune activité de cette clé. Elle porte `system` et `activity`. |
| `Exception\DowntimeRefused` | Le service n'autorise pas le changement : le membre ne joue pas le personnage et ne mène pas la campagne, ou ne la mène pas pour un appel du maître de jeu ; une entrée a l'identifiant ; la file du personnage est pleine ; la campagne a plus d'entrées en file qu'une avance n'en lit ; ou l'entrée n'est pas à une étape d'où part le changement. |
| `Exception\EntryNotFound` | Aucune entrée n'a cet identifiant. |
| `Exception\DowntimeConflict` | Une entrée a changé depuis qu'elle a été lue, ou une nouvelle a l'identifiant d'une entrée conservée : relisez-la et refaites le changement. |

Rien n'est conservé et rien n'est annoncé quand une exception est levée.
