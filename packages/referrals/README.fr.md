<!-- phil-dev source-sha256=ab637d047f363900411ad66606f547001e768bc36825cf1b2408b9f496f06a41 -->
# tilttek/referrals

**Des parrainages récompensés en points, des deux côtés, une fois que le nouveau joue vraiment, avec un plafond par parrain.**

tilttek/referrals permet à un membre de partager un code — dans un lien que l'application construit — et le récompense,
lui et l'ami qui s'est joint par ce code, en points de `tilttek/points` : jamais en argent, en commission ou en rabais.
La récompense attend la première vraie action du nouveau — une partie jouée, une fiche de personnage publiée —, que
l'application signale par un port, jamais l'inscription elle-même, de sorte qu'un compte créé seulement pour ramasser
des points ne rapporte rien. Par défaut, un parrain est récompensé pour dix parrainages, au total ; au-delà du
plafond, le nouveau reçoit tout de même ses points.

Les garde-fous des échanges de `tilttek/exchange` valent aussi pour les parrainages : un membre ne se parraine jamais
lui-même, ni son autre compte quand l'application indique que deux comptes appartiennent à une même personne ; un parrain
dont le compte est trop récent attend, et seul un membre arrivé récemment peut être parrainé, chaque garde-fou sur les
dates restant désactivé jusqu'à ce que l'application le règle. Le code est aléatoire et ne dit rien de son membre.

Les points sont donnés une seule fois, quoi qu'il arrive : le mouvement de chaque côté est écrit sous une clé construite
à partir de l'identifiant aléatoire du parrainage, que le grand livre n'écrit qu'une fois, et le parrainage est réglé
en une seule étape du magasin, par laquelle le plafond tient. Effacer un membre ne laisse aucun code ni aucun
parrainage qui le relie à quelqu'un.

Elle s'adresse à une communauté qui veut que ses membres amènent des amis sans transformer l'amitié en vente. C'est une
bibliothèque PHP simple dont les dépendances sont `tilttek/points`, `tilttek/exchange`, `phil/resource` et `psr/clock`.

## État

| Partie | État |
|---|---|
| `Referrals` : code, parrainage, récompense, lecture, plafond, effacement | Implémenté et testé |
| `ReferralCode`, `ReferralId`, `Referral`, `Terms`, `FirstAction` | Implémenté et testé |
| `Contract\FirstActions`, `Contract\SamePerson`, `Contract\ReferralStore`, avec leurs valeurs par défaut | Implémenté et testé |
| `Store\InMemoryReferralStore` | Implémenté et testé |
| Un magasin sur Doctrine DBAL, et un bundle Symfony | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Parrainer et récompenser](doc/fr/referrals.md) | Les codes, les garde-fous, la première action, la récompense versée une fois, le plafond, la lecture, l'effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, les premières actions, le signal de même personne, les dates d'arrivée, les conditions. |
| [Exceptions](doc/fr/exceptions.md) | `ReferralsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et le test du code qui parraine des membres. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/referrals
```

```php
use Tilttek\Referrals\Referrals;
use Tilttek\Referrals\Store\InMemoryReferralStore;

$referrals = new Referrals(new InMemoryReferralStore(), $points, $firstActions, $clock);

$code = $referrals->code($member);              // put in the link the member shares
$referrals->refer($code->value, $newcomer);     // when the newcomer joins through it
$referrals->reward($newcomer);                  // when the newcomer plays a first game: 100 and 50 points
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
