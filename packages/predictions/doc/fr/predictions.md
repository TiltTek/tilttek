<!-- phil-dev source-sha256=d8c58cea0450522aaf10a0c495dcb59348a885c841997429ddf3b0491e7921bd -->
# Poser et régler

## La question

`ask($key, $outcomes, $closesAt, $points, $scoring)` pose une question :

```php
$predictions->ask('final:2026', ['home', 'away', 'draw'], new DateTimeImmutable('2026-12-05 19:00 UTC'), 50);
$predictions->ask('campaign:12:end', ['on-time', 'late'], new DateTimeImmutable('2026-12-01 00:00 UTC'), 100, Scoring::Probability);
```

- La clé est celle de l'application, tirée de l'événement — de 1 à 200 octets, sans espace ni caractère de contrôle,
  ne commençant pas par `#`. La même question posée de nouveau est renvoyée telle quelle ; posée sous la même clé avec
  d'autres résultats, une autre échéance, d'autres points ou une autre notation, elle est refusée.
- Les résultats sont de 2 à 32 clés des issues de l'événement, chacune de 1 à 64 lettres minuscules, chiffres, `_`, `.`
  ou `-`. Ils ne contiennent aucun deux-points, de sorte que la référence d'un membre comme `member:12` n'est jamais un
  résultat.
- L'échéance est dans le futur ; elle est conservée en UTC.
- Les points sont ceux qu'un membre peut gagner sur la question, de 1 à 1 000 000.

**Une question porte sur un résultat d'un événement, jamais sur une personne.** Quelle équipe gagne la finale, quelle
faction tient la ville à la fin de la campagne, si le congrès affiche complet : oui. Qui sera couronné à un concours de
beauté, qui quittera le groupe, comment un membre se comportera : non. L'application déclare le résultat ; la
bibliothèque refuse les résultats qui ont la forme de la référence d'un membre, et la documentation interdit le reste.

## Le choix

`predict($question, $member, $pick)` enregistre le choix du membre, à la place de celui qu'il a fait auparavant :

```php
$predictions->predict('final:2026', $member, Pick::choice('home'));
$predictions->predict('campaign:12:end', $member, Pick::probabilities(['on-time' => 70, 'late' => 30]));
```

Une question notée par choix accepte `Pick::choice()` ; une question notée par probabilité accepte
`Pick::probabilities()`, des pourcentages entiers de 0 à 100 dont la somme est 100, un résultat omis recevant 0 %. La
prédiction garde son identifiant aléatoire quand le membre change son choix.

Rien n'est misé : prédire ne coûte aucun point au membre, et la bibliothèque n'en prend jamais.

## L'échéance

Une question accepte des prédictions tant qu'elle est ouverte et que l'horloge précède son échéance : à l'échéance
même, elle est close. Le magasin vérifie de nouveau à l'écriture, en une seule étape où une résolution ne peut pas se
glisser, de sorte qu'aucune prédiction n'est écrite une fois le résultat déclaré.

## Résoudre et régler

`resolve($question, $outcome)` déclare le résultat, ce qui clôt la question, même avant son échéance, et la règle :
chaque prédiction gagne les points que son choix rapporte — [Notation](scoring.md) donne les règles —, accordés par
`Points::grant()` depuis le compte d'émission `predictions` sous une clé construite à partir de l'identifiant de la
prédiction :

```text
prediction:9a3e…41
```

Le grand livre écrit une clé une seule fois : `settle($question)`, relancé après un règlement interrompu, ou lancé par
deux processus à la fois, ne verse rien en double. Les prédictions sont lues par pages dans l'ordre des identifiants,
de sorte qu'un membre effacé pendant un règlement ne décale aucune autre prédiction. `Settlement` indique combien de
prédictions ont été lues, combien ont gagné des points, le total des points, et combien de mouvements cet appel a
écrits.

Une question résolue ne peut pas être résolue avec un autre résultat, ni annulée ; déclarée de nouveau avec le même
résultat, elle se règle de nouveau.

## Annuler

`cancel($question)` clôt une question ouverte sans aucun point : un événement annulé, une question posée par erreur.
Son règlement ne lit rien et ne verse rien ; elle n'accepte plus de prédictions et ne peut plus être résolue ensuite.

## Lire

`question($key)`, `predictionOf($question, $member)` et `predictionsOf($member, $offset, $limit)`, les prédictions du
membre dans l'ordre où elles ont été faites la première fois, au plus 100 à la fois.

## Effacer un membre

`erase($member)` supprime les prédictions du membre : aucune ne le nomme ensuite. Les clés des mouvements portent
l'identifiant aléatoire de la prédiction, jamais le membre ; les points eux-mêmes sont effacés par `Points::erase()`,
que l'application appelle aussi. Un membre effacé avant un règlement n'en gagne rien.
