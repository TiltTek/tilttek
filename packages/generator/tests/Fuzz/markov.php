<?php

declare(strict_types=1);

/*
 * The fuzz target of the Markov lists, run by `phil-dev fuzz`: any entries, one by line, teach a chain of each order
 * that writes, in a bounded number of letters, a word within its lengths, or nothing.
 */

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\MarkovChain;
use Tilttek\Generator\Engine\MarkovModel;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    if (!mb_check_encoding($input, 'UTF-8')) {
        return;
    }

    $entries = array_map(static fn (string $line): Entry => new Entry($line), explode("\n", $input));

    foreach ([1, 2, 3] as $order) {
        $chain = new MarkovChain($order, 2, 8, true);
        $word = MarkovModel::learn($chain, $entries)->word(new Randomizer(new Xoshiro256StarStar(crc32($input))));

        if (null !== $word && (mb_strlen($word) < 2 || mb_strlen($word) > 8)) {
            throw new LogicException(sprintf('The word "%s" of order %d is outside its lengths.', $word, $order));
        }
    }
});
$config->setMaxLen(256);
