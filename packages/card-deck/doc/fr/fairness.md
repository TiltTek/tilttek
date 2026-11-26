<!-- phil-dev source-sha256=22b87cb0f29b69036d96db5799c7e8a7d3507a76edd9520f1cb5146f773df7d9 -->
# Équité

Un joueur fait confiance au paquet quand le serveur n'aurait pas pu l'arranger. La bibliothèque tire son hasard d'une
`Seed` de `tilttek/dice`, liée par un engagement publié avant que les joueurs n'ajoutent une graine à eux.

## Le déroulement d'une partie

1. `CardTable::open()` enregistre la partie avec une graine tirée d'une source sûre et retourne son engagement,
   `Seed::commitment()` : le SHA-256 du domaine de `tilttek/dice`, de la graine et du nombre 0. L'application le
   publie.
2. Les joueurs choisissent une graine du client, d'au plus 256 octets, après la publication de l'engagement.
   `CardTable::start()` mélange la pioche à partir de la graine, de la graine du client et du nombre 0.
3. Chaque geste suivant qui tire du hasard — un `Shuffle`, un `Draw` au hasard — prend le nombre suivant, 1, 2 et ainsi
   de suite. Les nombres suivent le journal, donc une relecture tire les mêmes.
4. `CardTable::finish()` révèle la graine : un `Reveal` contient le paquet, la graine, la graine du client et le
   journal.

Le serveur a fixé sa graine avant de connaître celle du client, et l'engagement le lie : il n'a pas pu choisir l'ordre.

## Le mélange

`Shuffler` est le mélange de Fisher-Yates : de la dernière place à la deuxième, chaque place échange avec une place
tirée parmi elle-même et celles qui la précèdent, par `Randomizer::getInt(0, place)`. Le générateur est celui que
donne `Seed::randomizer()` : `Xoshiro256**` initialisé par un HMAC-SHA256 de la graine du client et du nombre. Chaque
ordre est également probable, et une même graine, une même graine du client et un même nombre donnent le même ordre
dans tout langage qui implémente le même moteur et le même tirage.

Une pioche au hasard prend la carte à `Randomizer::getInt(0, count - 1)` du nombre suivant.

## Vérifier une partie terminée

```php
$reveal = Reveal::fromArray($published);   // what the server published at the end

$honest = $reveal->matches(Commitment::fromHex($commitmentPublishedAtTheStart));
$state = $reveal->replay();                 // the final state, the same fingerprint as the server's
```

`matches()` vérifie la graine contre l'engagement, en temps constant. `replay()` applique de nouveau le journal ; un
journal qui ne tient pas ensemble lève une exception. `Reveal::fromArray()` vérifie chaque champ et accepte au plus
10 000 gestes.

## Ce que la graine ne protège pas

Le serveur connaît la graine pendant la partie, donc il connaît l'ordre de la pioche : l'engagement prouve que l'ordre
n'a pas été choisi, pas que personne ne l'a lu. Un magasin garde la graine d'une partie inachevée comme un secret —
chiffrée au repos, jamais dans un journal, jamais envoyée à un navigateur — et ne la révèle que par `finish()`.
