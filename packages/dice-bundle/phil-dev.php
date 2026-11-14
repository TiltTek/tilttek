<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : API de schéma DBAL dépréciée dans les tests (getColumns(), getName(), getPrimaryKey()).
            ['identifier' => 'method.deprecated', 'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php', 'count' => 3],
            // à corriger : getName()/getObjectName() appelées sur des classes internes de DBAL dans les tests.
            ['identifier' => 'method.internalClass', 'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php', 'count' => 1],
            // à corriger : DoctrineExtension est interne à DoctrineBundle, le test doit charger la configuration autrement.
            ['identifier' => 'new.internalClass', 'path' => 'tests/Unit/TilttekDiceBundleTest.php', 'count' => 1],
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'paths' => [
                'src/Application/Port/DiceAppearance.php',
                'src/Domain/Repository/DiceMacroRepository.php',
                'src/Domain/Repository/RecordedRollRepository.php',
            ]],
            // à corriger : contrainte de validation (attribut) hors d'un espace de noms Attribute, à déplacer.
            ['identifier' => 'symplify.requireAttributeNamespace', 'path' => 'src/Infrastructure/Validator/DiceExpression.php'],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The ink of `Contrast`: of the 16 777 216 colours written #rrggbb, none has a luminance that makes the two
            // ratios exactly equal (checked over all of them), so `>=` is worth `>` there.
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\(\\$luminance \\+ 0\\.05\\) / 0\\.05 >= 1\\.05 / \\(\\$luminance \\+ 0\\.05\\) \\? self::BLACK : self::WHITE;',
                ],
            ],
            // The journal of Doctrine reads only the rolls asked, and returns them in the order asked from the map by
            // identifier: a read of every roll would give the same list, only slower.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*->findBy\\(\\[\'id\' => \\$ids\\]\\).*',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true],
];
