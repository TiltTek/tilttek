<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'bench' => [
        // A page of statistics served by FrankenPHP in worker mode, the roller and the probabilities built once.
        'worker' => ['script' => 'tests/Benchmark/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
    'infection' => [
        'mutators' => [
            // The digit of a quotient in `Natural::divide()`, searched bit by bit: started at -1 rather than 0, the
            // bits still add up to the same largest digit, since together they reach past the base.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$digit = 0;',
                ],
            ],
            // The leading digits of `Natural::leading()`: a numeric string divides as the float it is cast to.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\[\\(float\\) substr\\(\\$digits, 0, self::LEADING\\).*',
                ],
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : complexité cognitive de la classe (115) au-dessus de 100, à découper.
            ['identifier' => 'complexity.classLike', 'path' => 'src/Computation/Calculator.php', 'count' => 1],
            // à corriger : complexité cognitive de Calculator::select() (42) au-dessus de 25, à découper.
            ['identifier' => 'complexity.functionLike', 'path' => 'src/Computation/Calculator.php', 'count' => 1],
            // à corriger : exception hors de l'espace de noms Exception, à déplacer.
            ['identifier' => 'symplify.requireExceptionNamespace', 'paths' => [
                'src/Computation/TooLarge.php',
                'src/Computation/Unsupported.php',
            ]],
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'path' => 'src/Event/Event.php'],
        ],
    ],
    'docs' => ['public' => true],
];
