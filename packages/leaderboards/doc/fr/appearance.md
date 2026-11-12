<!-- phil-dev source-sha256=02b2effe9cac7d7726e1bd004096c7de424c2f2a22cf731ebfe634cd9781d0ff -->
# Paraître dans un classement

**Personne n'est classé publiquement sans l'avoir choisi.** Un classement montre quelque chose d'un membre aux autres ;
un membre qui n'a pas choisi d'être montré ne l'est pas.

## Le choix

| Visibilité | Ce que les autres voient sur la ligne du membre |
|---|---|
| `Hidden`, celle de chaque membre tant qu'il n'a pas choisi | Le rang et le score, sans nom |
| `Named` | La référence du membre, que l'application nomme |
| `Pseudonym` | Le pseudonyme que le membre a choisi : de 1 à 40 caractères, aucun caractère de contrôle, aucune espace à l'une ou l'autre extrémité |

```php
$leaderboards->appear($member, Appearance::named());
$leaderboards->appear($member, Appearance::pseudonym('Le Nain Gris'));
$leaderboards->appear($member, Appearance::hidden());
$leaderboards->appearance($member); // the choice kept, hidden by default
```

Le choix vaut pour tous les classements. Choisir d'être caché oublie le choix gardé.

## Ce que voit chaque spectateur

Un membre caché garde son rang (le rang des autres ne change pas parce que quelqu'un a choisi de ne pas paraître) et
voit sa propre ligne avec sa référence et `self` activé. Les autres voient une `Row` dont le membre et le pseudonyme
sont tous deux nuls ; `Row::isHidden()` le signale, et un gabarit y écrit « Un membre ». Une ligne sous pseudonyme ne
porte jamais la référence du membre, pour qu'une page ne puisse pas relier le pseudonyme à un compte.

Un instantané s'affiche selon le choix de chaque membre au moment où il est affiché, et non au moment où il a été pris :
un membre qui se cache ensuite disparaît aussi des classements finaux.

## Le rôle de l'application

L'application demande au membre, dans son profil, comment il veut paraître, et appelle `appear()` ; elle donne les noms
des membres nommés quand elle produit le rendu d'un classement. L'exportation d'un membre contient son choix
([Effacement](erasure.md)).
