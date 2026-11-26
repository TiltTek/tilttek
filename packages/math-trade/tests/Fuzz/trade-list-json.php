<?php

declare(strict_types=1);

/*
 * Trade lists in JSON nobody wrote: whatever the document, the reader returns a trade list or refuses it with an
 * exception of the library; a list it returns is written and read back to the same document, and solves to trades in
 * which each offer changes hands once at most.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Tilttek\MathTrade\Exception\MathTradeException;
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\TradeSolver;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$json = new TradeListJson();
$solver = new TradeSolver();
$limits = new Limits(offers: 200, wishedOffers: 2_000, jsonBytes: 65_536);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($json, $solver, $limits): void {
    try {
        $list = $json->decode($input, $limits);
    } catch (MathTradeException) {
        // The refusal the reader promises for a document that is not a trade list within the limits.
        return;
    }

    $written = $json->encode($list);

    if ($json->encode($json->decode($written, new Limits())) !== $written) {
        throw new LogicException('A trade list read back from its JSON is not the same list.');
    }

    $result = $solver->solve($list, 'fuzz');
    $received = [];

    foreach ($result->swaps() as $swap) {
        if (isset($received[$swap->received]) || $swap->participant === $swap->receiveFrom) {
            throw new LogicException('An offer changes hands twice, or a participant trades with themselves.');
        }

        $received[$swap->received] = true;
    }
});
$config->setMaxLen(4096);
