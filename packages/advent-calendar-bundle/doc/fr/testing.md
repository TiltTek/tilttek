<!-- phil-dev source-sha256=c968bad384673ded5f7f1e2ba3e83530cf29376481609a2c7d38442bdc041ee9 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

Les tests unitaires couvrent la configuration et les services qu'elle raccorde, les calendriers de la configuration, le
membre et sa garde, les célébrations, les cases d'un visiteur, les enregistrements de calendrier, le magasin des
ouvertures sur SQLite et en mémoire par un seul contrat — deux connexions qui ouvrent une case l'accordent une fois —,
le catalogue de la base de données et l'écouteur du schéma, et le module de vie privée. Les tests fonctionnels
démarrent une petite application — FrameworkBundle avec sessions, formulaires et CSRF, DoctrineBundle avec l'ORM sur
SQLite, TwigBundle et les composants Twig, les recettes du kit PHIL UX copiées là où `ux:install` les met, une horloge
au 3 décembre 2026 — et parcourent l'écran : un visiteur qui ne voit aucune surprise, un membre qui ouvre une case avec
un formulaire simple et en arrière-plan et qui est célébré une fois, une ouverture refusée, un calendrier du jour même,
les gardes, un calendrier de la base de données, le formulaire et les commandes. Ils sont ignorés quand le répartiteur
d'événements, une dépendance de développement, ou le kit PHIL UX manque. Les tests JavaScript couvrent la lecture des
réponses du serveur. Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

## Tester une application qui l'utilise

Configurez `store: memory` dans l'environnement de test, enregistrez un `MemberResolver` que le test pilote, et un
`MockClock` comme service `clock` réglé sur un jour du calendrier. Vérifiez les surprises que votre écouteur de
`BoxOpened` a accordées.
