<!-- phil-dev source-sha256=7c1a3146f036567dff5b7b567d63936099e8fce198bb888614cf247f16eb6ae0 -->
# Tenir un tirage

## Les enjeux et les sources

| Enjeu | Germe fourni par | Pourquoi |
|---|---|---|
| `Stake::Prize` : quelque chose de valeur est gagné | `SeedSource::Beacon`, toujours | Un organisateur qui détiendrait le germe pourrait tirer de nouveau jusqu'à ce qu'un ami gagne, avant de s'y engager |
| `Stake::None` : qui parle le premier, qui apporte les collations | `SeedSource::CommitReveal` par défaut, `SeedSource::Beacon` sur demande | Rien à gagner en trichant ; aucun réseau nécessaire |

`open()` refuse un prix dont le germe vient de l'organisateur, et un tirage par balise quand aucune balise n'est
configurée.

```php
$raffles->open('door-prize', Stake::Prize, winners: 3, alternates: 2);
$raffles->open('first-speaker', Stake::None);                          // commit-reveal
$raffles->open('pizza-topping', Stake::None, source: SeedSource::Beacon);
```

Un tirage désigne de 1 à 1 000 gagnants et de 0 à 1 000 substituts. Un tirage par engagement-révélation tire son
germe — 32 octets du générateur aléatoire sécurisé — à son ouverture, et son engagement, le SHA-256 des 64 chiffres
hexadécimaux du germe, figure dès lors dans le tirage et dans l'annonce.

## Les inscriptions : une chance par participant

```php
$raffles->enter('door-prize', 'member:1042');
```

Un participant est une référence opaque de 1 à 128 lettres, chiffres, points, deux-points, traits de soulignement,
tildes ou traits d'union : un identifiant de membre ou de billet, publié dans la preuve, donc jamais une adresse ni un
nom — l'`@` d'une adresse est refusé. Un participant s'inscrit une fois : une seconde inscription est refusée, rien
dans l'API n'accepte un nombre de chances, et le tirage est uniforme parmi les participants distincts. Un tirage
accepte jusqu'à 100 000 participants, et aucune inscription une fois scellé.

## Le scellement

```php
$announcement = $raffles->seal('door-prize');
$announcement->toArray();     // raffle, stake, source, winners, alternates, entrants, entrants_hash, sealed_at,
                              // commitment, chain, round, drawable_at
```

`seal()` refuse un tirage sans participants, liste les participants dans l'ordre des octets, en calcule l'empreinte et,
pour une balise, lit la chaîne et fixe la première ronde publiée strictement après la fermeture plus la marge — 30
secondes par défaut, de quoi laisser les horloges du serveur et de la balise différer. Publiez l'annonce aussitôt, là
où les participants la voient : l'empreinte prouve qu'aucun participant n'a été ajouté ou retiré ensuite ; la ronde,
annoncée avant d'exister, prouve que l'organisateur n'a pas pu la choisir. `announcement()` la redonne plus tard.

## Le tirage

```php
$proof = $raffles->draw('door-prize');
$proof->winnerList();         // the winners, in the order drawn
$proof->alternateList();      // the alternates, in the order they replace a winner
```

Un tirage par balise s'effectue une fois sa ronde publiée : avant `drawableAt()`, `draw()` refuse et ne lit rien. La
ronde lue doit être la ronde annoncée. Un tirage par engagement-révélation s'effectue aussitôt, son germe révélé dans
la preuve. Quand il y a moins de participants que de gagnants et de substituts, chaque participant est tiré, dans un
ordre aléatoire.

Un tirage s'effectue une fois : un gagnant qui ne réclame pas son prix est remplacé par le substitut suivant, jamais
par un nouveau tirage, de sorte que l'ordre de remplacement est fixé par le même germe et figure dans la preuve.
`proof()` redonne la preuve plus tard.

## L'événement

Une fois le tirage gardé comme effectué, `Raffles` envoie `Event\RaffleDrawn`, qui ne porte que l'identifiant du
tirage. Un écouteur lit la preuve pour annoncer les gagnants, les pousser vers un écran ou jouer une célébration — des
confettis sur la superposition d'une diffusion en direct — ; la bibliothèque elle-même n'affiche rien.
