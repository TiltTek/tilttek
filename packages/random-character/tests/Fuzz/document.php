<?php

declare(strict_types=1);

/*
 * The fuzz target of the documents, run by `phil-dev fuzz`: any YAML text is either refused with the library's own
 * exceptions, or gives rules whose every field is found again by its key and names only fields before it.
 */

use Tilttek\RandomCharacter\Exception\RandomCharacterException;
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RandomCharacter\Format\Syntax;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $catalogue = new RulesReader(4096)->parse("format: tilttek-random-character/1\nsystem: fuzz\n".$input, Syntax::Yaml);
    } catch (RandomCharacterException) {
        // The refusals the reader promises for a text it cannot read.
        return;
    }

    foreach ($catalogue->all() as $rules) {
        $before = [];

        foreach ($rules->fields as $field) {
            if ($rules->field($field->key) !== $field || [] !== array_diff($field->references, $before)) {
                throw new LogicException(sprintf('The field "%s" of the rules "%s" is not read back in order.', $field->key, $rules->key));
            }

            $before[] = $field->key;
        }
    }
});
$config->setMaxLen(1024);
