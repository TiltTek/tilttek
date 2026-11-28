<!-- phil-dev source-sha256=caa20ed57c4a276011cb90f40cda4fbaa89bda6d2705c101523ce67323869221 -->
# Composition

La composition d'un groupe compare ses membres actifs aux rôles que déclare un système de jeu. C'est un conseil : la
bibliothèque ne refuse pour cela aucun membre, aucun rôle ni aucun groupe, et l'application l'affiche comme elle veut —
une suggestion au maître de jeu, un badge sur la fiche du groupe. Les rôles ne sont qu'une aide : un groupe joue aussi
bien sans, et un jeu sans classes ni métiers n'en a aucun.

## Les rôles d'un système

Un `RoleCatalogue` contient de 0 à 50 rôles aux clés distinctes, chacun un `Role` avec un nom de 1 à 100 caractères et
un minimum, soit le moins de membres qu'un groupe équilibré en compte : 1 par défaut, 0 pour un rôle bienvenu mais
jamais manquant, 10 au plus.

Un système de `tilttek/rpg-systems` déclare les rôles d'un groupe comme ses rôles de groupe, dans son ordre :

```php
use Tilttek\Party\Composition\Role;
use Tilttek\Party\Composition\RoleCatalogue;

$roles = RoleCatalogue::fromSystem($system);                         // the party roles of the system
$roles = RoleCatalogue::fromSystem($system, locale: 'fr', minimum: 0);
$roles = new RoleCatalogue([new Role('healer', 'Healer'), new Role('scout', 'Scout', minimum: 0)]);
$roles = RoleCatalogue::none();                                      // a game without roles
```

`fromSystem()` nomme chaque rôle dans la locale donnée, en revenant au nom anglais du rôle à défaut, et donne à chaque
rôle le même minimum ; un système sans rôles de groupe donne un catalogue vide, que dit `isEmpty()`. Les sources ouvertes que
lisent les profils d'importation de `tilttek/rpg-systems` ne déclarent aucun rôle de groupe, et la bibliothèque n'en
invente aucun : une application qui veut des rôles pour un tel système déclare les siens, à la main.

## Un jeu sans rôles

Face à un catalogue vide, il n'y a rien à conseiller. La composition ne contient aucune couverture, aucun membre sans
rôle et aucun rôle inconnu — les rôles que portent les membres sont alors des étiquettes libres —, `isBalanced()` est
vrai, `fairShare` vaut 0 et `hasAdvice()` est faux : l'application n'affiche alors aucune suggestion.

```php
$composition = $parties->composition($party, RoleCatalogue::fromSystem($system));

if ($composition->hasAdvice()) {
    // show the missing and crowded roles
}
```

## Lire une composition

```php
$composition = $parties->composition($party, $roles);
// or, on members the application already holds:
$composition = new CompositionAdvisor()->advise($members, $roles);
```

Chaque rôle du catalogue reçoit un `RoleCoverage` : les membres actifs qui le remplissent, dans l'ordre du groupe, et
un `Coverage` :

- `Missing` quand moins de membres actifs le remplissent que son minimum ;
- `Crowded` quand plus de membres le remplissent que sa juste part — les membres actifs divisés entre les rôles,
  arrondis à l'entier supérieur, et jamais sous le minimum du rôle lui-même ;
- `Covered` autrement.

Avec quatre membres actifs et trois rôles, la juste part est de deux : un troisième guérisseur surcharge les soins.
Avec un seul rôle, aucun rôle n'est jamais surchargé. Un membre qui remplit deux rôles compte dans chacun.

`Composition` indique :

| Lecture | Ce qu'elle contient |
|---|---|
| `coverages` | Chaque rôle du catalogue, dans son ordre |
| `missing()`, `crowded()` | Les rôles sous leur minimum, au-dessus de leur juste part |
| `hasAdvice()` | Faux face à un catalogue sans rôles |
| `isBalanced()` | Vrai quand aucun rôle ne manque ni n'est surchargé |
| `coverageOf($key)` | La couverture d'un rôle, ou null |
| `unassigned` | Les membres actifs qui ne remplissent aucun rôle du catalogue |
| `unknownRoles` | Les rôles que des membres remplissent et que le catalogue n'a pas, par clé, avec leurs membres |
| `active`, `fairShare` | Les membres actifs comptés, et la part égale avant les minimums, 0 sans rôles |

Les membres en absence ne comptent nulle part : ni dans un rôle, ni parmi les membres sans rôle, ni dans la juste part.

## Coût

`CompositionAdvisor` ne garde aucun état. Son travail croît avec les membres multipliés par leurs rôles — au plus
100 membres de 5 rôles chacun — et avec les rôles du catalogue, au plus 50.
