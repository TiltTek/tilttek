<?php

declare(strict_types=1);

namespace Tilttek\Dice\Reader;

use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Parser\DiceParser;

/**
 * Reads a command written in the notation of Rolisteam's DiceParser into the library's tree: its tokens are written
 * again in the library's notation, then parsed, and an error is placed in the command as it was written. What the
 * library does not roll — the split of explosions `y`, the merge `m` and the bind `b` of every instruction — is
 * refused with its position.
 */
final readonly class RolisteamReader
{
    public function __construct(
        private Limits $limits = new Limits(),
    ) {
    }

    /**
     * @throws InvalidExpression when the command does not read
     * @throws LimitExceeded     when it reads but goes past a limit
     */
    public function read(string $input, ?Macros $macros = null): Pool
    {
        $this->limits->checkLength($input);
        $rewriter = new RolisteamRewriter($input);
        $text = $rewriter->rewrite();
        $limits = $this->limits;
        // The command is within the length already; the library's notation may write it a few characters longer.
        $parser = new DiceParser(new Limits($limits->dice, $limits->sides, $limits->explosions, $limits->expressions, max($limits->length, mb_strlen($text)), $limits->rerolls, $limits->repetitions, $limits->text), new Notation(), $macros);

        try {
            return $parser->parse($text);
        } catch (InvalidExpression $invalidExpression) {
            throw InvalidExpression::at($input, $rewriter->position($invalidExpression->position), $invalidExpression->reason);
        }
    }
}
