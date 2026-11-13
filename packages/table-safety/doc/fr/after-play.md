<!-- phil-dev source-sha256=0022e715a844a49f37719d6bd01781fe794e21309f395eba1b3300bdb1e24a09 -->
# Après la partie

## Étoiles et souhaits

Après une séance, chaque participant donne ses **étoiles** — ce qu'il a aimé — et ses **souhaits** — ce qu'il aimerait
voir la prochaine fois :

```php
$safety->giveFeedback($table, '2026-11-12', $participant, new StarsAndWishes(['The heist'], ['More downtime']), $now);
```

Chaque texte est rogné et compte 500 caractères au plus ; les textes vides sont écartés ; on donne au plus 10 étoiles et
10 souhaits. Une séance est nommée par une clé que choisit l'application — une date, `session-3` —, écrite comme une clé
de thème. Les donner de nouveau remplace les étoiles et les souhaits antérieurs du participant pour cette séance.

## Le sommaire d'une séance

`TableSafety::feedback()` rassemble les étoiles et les souhaits d'une séance sans dire qui a donné quoi : chaque étoile
et chaque souhait en ordre alphabétique plutôt que dans l'ordre où ils ont été donnés, et le nombre de participants qui
ont répondu.

```php
$summary = $safety->feedback($table, '2026-11-12');

$summary->stars;        // ['Bards', 'The heist']
$summary->wishes;       // ['More downtime']
$summary->respondents;  // 2
```
