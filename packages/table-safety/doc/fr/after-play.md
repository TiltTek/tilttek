<!-- phil-dev source-sha256=2386b8448329e5d30d41c9b20f93ee3c50199eedcac40796d9bb485be55b8085 -->
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
et chaque souhait en ordre alphabétique plutôt que dans l'ordre où ils ont été donnés, et le nombre de participants dont
il contient les réponses.

```php
$summary = $safety->feedback($table, '2026-11-12');

$summary->hasEnoughAnswers();  // false: "not enough answers yet"
$summary->stars;               // ['Bards', 'More maps', 'The heist'] once it has enough
$summary->wishes;              // ['More downtime']
$summary->respondents;         // 3
```

## Le seuil

Dans un petit groupe, un sommaire d'une ou deux réponses dit qui les a écrites. Un sommaire ne contient donc des
réponses que par groupes entiers d'un **seuil** de participants, 3 par défaut, pris dans l'ordre de leurs réponses :

- sous le seuil, il ne contient rien : `hasEnoughAnswers()` est faux, `stars` et `wishes` sont vides et `respondents`
  vaut 0, quel que soit le nombre de réponses ;
- au-delà, les réponses d'un groupe incomplet attendent : avec un seuil de 3, quatre ou cinq réponses donnent le
  sommaire des trois premières, six les donnent toutes.

Une réponse de plus ne change donc jamais le sommaire des seules réponses d'une personne. L'application choisit le seuil
— jamais une table, dont le meneur de jeu lit le sommaire :

```php
$safety = new TableSafety($catalogue, $tables, $participations, $signals, $feedback, feedbackThreshold: 4);
```

Un seuil de 1 montre chaque réponse dès qu'elle est donnée. [Vie privée](privacy.md#le-sommaire-des-étoiles-et-des-souhaits)
dit ce que le seuil ne cache pas.
