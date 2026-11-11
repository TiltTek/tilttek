<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        'targets' => ['dice-parser' => 'tests/Fuzz/dice-parser.php', 'rolisteam-reader' => 'tests/Fuzz/rolisteam-reader.php'],
    ],
    'infection' => [
        'mutators' => [
            // The signs compared in `Arithmetic`, where zero never reaches the comparison: a zero dividend or remainder
            // returns before it, a zero divisor is refused before it, and a zero base gives a power that never
            // overflows; `<=` is worth `<` there.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$negative = \\(\\$left < 0\\) !== \\(\\$right < 0\\);',
                    'return 0 !== \\$remainder && \\(\\$remainder < 0\\) !== \\(\\$right < 0\\) \\? \\$remainder \\+ \\$right : \\$remainder;',
                    '\\$power = self::raise\\(\\$base, -\\$exponent\\) \\?\\? \\(\\$base < 0 && 0 !== \\$exponent % 2 \\? -\\\\PHP_INT_MAX : \\\\PHP_INT_MAX\\);',
                    // A comment of the parser never starts where an instruction ends, on its separator: `<=` is worth
                    // `<` there.
                    '\\$this->instruction = \\\\count\\(array_filter\\(\\$ends, static fn \\(int \\$end\\): bool => \\$end < \\$token->position\\)\\);',
                ],
            ],
            // The counts of `Limits`, which stop at the largest integer: a sum equal to it is it, and a product within
            // a divisor of it is past any limit but the largest integer itself; `>=` is worth `>` there.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$sum = \\$term > \\\\PHP_INT_MAX - \\$sum \\? \\\\PHP_INT_MAX : \\$sum \\+ \\$term;',
                    'return 0 !== \\$left && \\$right > intdiv\\(\\\\PHP_INT_MAX, \\$left\\) \\? \\\\PHP_INT_MAX : \\$left \\* \\$right;',
                ],
            ],
            // The memo of `Grouping::best()` only spares time: the same mask gives the same groups, found again.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$best\\[\\$mask\\];',
                ],
            ],
            // The lowest and highest faces of the dice of instructions that count no die, in `Roller`: with no die to
            // mark, any value is worth 0.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$(lowest|highest) = \\[\\] === \\$values \\? 0 : (min|max)\\(\\$values\\);',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$(lowest|highest) = \\[\\] === \\$values \\? 0 : (min|max)\\(\\$values\\);',
                ],
            ],
            // A power to the exponent 0, in `Arithmetic`: taken as a negative exponent, it is one divided by the power
            // to 0, which is 1 as well; `>` is worth `>=` there.
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$exponent >= 0\\) \\{',
                ],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // à corriger : complexité cognitive de la classe (163) au-dessus de 100, à découper.
            ['identifier' => 'complexity.classLike', 'path' => 'src/Parser/DiceParser.php', 'count' => 1],
            // à corriger : complexité cognitive de DiceParser::modifiers() (35) au-dessus de 25, à découper.
            ['identifier' => 'complexity.functionLike', 'path' => 'src/Parser/DiceParser.php', 'count' => 1],
            // à corriger : addToAssertionCount() est interne à PHPUnit, à remplacer par une assertion explicite.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/LimitsTest.php', 'count' => 7],
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'paths' => [
                'src/Die/DieType.php',
                'src/Die/NumericDie.php',
                'src/Expression/Node.php',
                'src/Expression/Validator.php',
                'src/Result/NodeResult.php',
            ]],
            // à corriger : constructeur privé d'une exception à constructeurs nommés, plus étroit que celui du parent.
            ['identifier' => 'symplify.parentMethodVisibilityOverride', 'path' => 'src/Exception/InvalidExpression.php'],
        ],
    ],
    'docs' => ['public' => true],
];
