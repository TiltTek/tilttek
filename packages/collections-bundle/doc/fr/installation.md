<!-- phil-dev source-sha256=4b0aee7db18c2791dfa94b2e197d43baf61bb7790f124656e0702de65c0399bb -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5 et son bundle.
- `tilttek/activity-bundle`, pour remettre l'objet du jour sur l'activité des membres.

## Enregistrer le bundle

```sh
composer require tilttek/collections-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\CollectionsBundle\TilttekCollectionsBundle::class => ['all' => true],
];
```

## La migration

Quand l'ORM est installé, le bundle ajoute ses six tables au schéma qu'il génère pour la connexion configurée :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez les tables à partir de `CollectionsTables::configure()`.

## Paquets facultatifs

| Paquet | Ce qu'il ajoute |
|---|---|
| `tilttek/activity-bundle` | La projection de l'objet du jour, tenue à jour et reconstruite |
| `phil/almanac` | Des objets du jour à des dates calculées |
| `tilttek/exchange-bundle` | Les échanges d'exemplaires entre membres, par `CollectionsInventory` |
| `tilttek/dice-bundle` | Les dés qu'a choisis un membre, par `CollectionDiceAppearance` |
| `symfony/console` | Les commandes |
| `symfony/twig-bundle`, `symfony/translation` | Les fonctions Twig, les gabarits et leurs textes |
| `phil/privacy-bundle` | L'export et l'effacement d'un membre |
