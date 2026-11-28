<?php

declare(strict_types=1);

/*
 * The fuzz target of the text of a rule, which comes from the game master, run by `phil-dev fuzz`: any bytes are either
 * refused with the library's own exceptions or give a text that is valid UTF-8, of line feeds only, within its bounds,
 * and kept as it is when given again.
 */

use Tilttek\TableRules\Exception\TableRulesException;
use Tilttek\TableRules\Rule;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $rule = new Rule('fuzz', 'Fuzz', $input);
    } catch (TableRulesException) {
        // The refusals the library promises for a text it does not keep.
        return;
    }

    if (1 !== preg_match('//u', $rule->text) || str_contains($rule->text, "\r") || '' === $rule->text || count($rule->lines()) > Rule::MAX_LINES) {
        throw new LogicException('A text kept is out of its shape.');
    }

    if (new Rule('fuzz', 'Fuzz', $rule->text)->text !== $rule->text) {
        throw new LogicException('A text kept changes when given again.');
    }
});
$config->setMaxLen(1024);
