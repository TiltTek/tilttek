<!-- phil-dev source-sha256=9b0815329d8b1999384d80afac73f30e1c815a01b61e34510074e87026ce276d -->
# Contribuer

[English](CONTRIBUTING.md)

Voici le monodépôt des paquets ouverts de [Tilttek](https://github.com/TiltTek). Chaque paquet `tilttek/*` est développé
ici, en suivant les règles des paquets `phil/*` de
[Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement) (PHIL), et son propre dépôt est
une copie en lecture seule scindée de `packages/<name>/` : **ouvrez les tickets et les demandes de tirage ici**, pas sur
un dépôt scindé, que chaque scission écrase.

## Signaler un bogue

- Vérifiez d'abord que le bogue n'est pas déjà signalé.
- Nommez le paquet et donnez sa version, celles de PHP et de Symfony, la configuration en cause et le plus petit code
  qui montre le bogue.
- Une vulnérabilité de sécurité n'est pas un rapport de bogue : suivez plutôt la
  [politique de sécurité](SECURITY.fr.md).

## Proposer un changement

Ouvrez un ticket avant un changement important, pour en convenir d'abord de la conception. Ensuite :

1. **Écrivez votre propre code.** Aucun code n'est copié d'un autre projet, même sous une licence compatible : un autre
   projet peut servir de référence à lire, jamais de source à copier, à traduire ou à porter. Une licence tierce n'entre
   dans un paquet que par une dépendance Composer déclarée.
2. **Gardez le paquet indépendant de toute application.** Son code, ses commentaires et sa documentation ne nomment
   jamais une application qui l'utilise ; ce qui est propre à une application relève de la configuration.
3. **Respectez les couches.** Un bundle garde séparés `Domain` (PHP pur, objets-valeurs et règles, sans conteneur ni
   entrée-sortie), `Application` (les services et les ports que l'application implémente) et `Infrastructure` (les
   adaptateurs vers Symfony, Doctrine, Twig…) ; la classe du bundle ne fait que les brancher.
4. **Testez-le.** Chaque changement vient avec ses tests. La couche du domaine est tenue à 100 % de couverture des
   mutations : un mutant qui survit est un test manquant, ou du code que rien ne peut atteindre.
5. **Documentez-le, en anglais.** Mettez à jour le dossier `doc/` du paquet et, quand un sujet s'ajoute, le tableau de
   la documentation de son README. Les miroirs français (`README.fr.md`, `doc/fr/`) peuvent suivre dans une demande de
   tirage ultérieure.
6. **Passez les vérifications.** Depuis la racine du monodépôt, après `composer install` et `composer hooks`, les
   vérifications de qualité s'exécutent sur chaque paquet — PHP-CS-Fixer, Rector, PHPStan à son niveau maximal, deptrac,
   Arkitect, PHPUnit et Infection — et `composer packages` installe chaque paquet seul, vérifie ses dépendances
   déclarées et exécute ses tests. Une demande de tirage est fusionnée quand toutes les vérifications passent.

Les vérifications sont des scripts Composer de la racine : `composer gates` les exécute dans l'ordre, et chacune
s'exécute seule, par exemple `composer phpstan` ou `composer test`. Les suites qui pilotent un vrai navigateur
s'exécutent à part, avant une version, avec `composer test:browser` : elle installe le Stimulus de chaque suite depuis
son fichier de verrou npm et a besoin du chromedriver du système dans le chemin. Pour vérifier un paquet seul, depuis
son dossier :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Licence

En contribuant, vous acceptez de placer votre contribution sous la licence MIT du paquet, dont son `LICENSE` nomme le
titulaire.

## Code de conduite

Toute personne qui participe suit le [code de conduite](CODE_OF_CONDUCT.fr.md).
