<!-- phil-dev source-sha256=053f36f0afa1e3ae3009e8b46064881607c3908ec8ba4276516f4d2f5540f9a9 -->
# La prison

`Jail\Warden` envoie le dé d'un membre en prison et l'en fait sortir.

```php
$inmate = $warden->jail('member-7', 'd20:blue', 'Three natural ones in a row.');
$warden->jail('member-7', 'd6:red', until: new DateTimeImmutable('+1 day'));   // a sentence that ends by itself

$warden->inmate('member-7', 'd20:blue');       // the Inmate, or null when free
$warden->inmates('member-7');                  // the dice in jail now, in the order they went in
$warden->release('member-7', 'd20:blue', 'It has learned its lesson.');
$warden->history('member-7', 'd20:blue');      // every JailEvent, in order
```

## Les peines

Une peine sans fin dure jusqu'à ce que le dé soit libéré. Une peine avec une fin fait sortir le dé d'elle-même à ce
moment : le dé est alors libre, ne peut pas être libéré et peut retourner en prison. Un dé n'est en prison qu'une fois à
la fois.

## Le gardien et le dé

Le gardien est l'identifiant stable du membre à qui appartient le dé — jamais une adresse courriel : un identifiant qui
contient `@` est refusé. Le dé est le nom que l'application lui donne : `d20:blue`, l'identifiant d'un dé. Les deux
font de 1 à 128 octets d'UTF-8 sans caractères de contrôle ; une raison compte jusqu'à 500 caractères, sauts de ligne
permis. Un gardien a au plus 1 000 dés en prison à la fois.

## Les preuves

`Evidence::of($result)` garde, avec l'emprisonnement, les statistiques montrées à la table : le verdict, le nombre de
lancers, la valeur p et les moyennes. La prison ne les exige jamais et ne les lit jamais.

## Ce que la prison n'est pas

La prison est un geste de jeu. Elle ne change rien à la façon dont un dé roule, ne prend rien à un membre et n'exige
aucune statistique. Elle est privée pour le gardien, à moins que l'application ne la montre ; rien en elle ne classe
les membres ni les dés.

## Rédacteurs concurrents

Chaque événement porte sa position dans l'historique du dé ; `Contract\JailStore::append()` lance
`Exception\JailConflict` quand un autre rédacteur est passé le premier, et rien n'est écrit.
