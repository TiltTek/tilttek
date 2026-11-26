<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

/**
 * What one traded offer means for its owner: "you send this offer to X, you receive that offer from Y".
 */
final readonly class Swap
{
    /**
     * @param string $participant the owner of the sent offer, who receives the other
     * @param string $sent        the offer they send
     * @param string $sendTo      the participant they send it to
     * @param string $received    the offer they receive for it
     * @param string $receiveFrom the participant they receive it from
     */
    public function __construct(
        public string $participant,
        public string $sent,
        public string $sendTo,
        public string $received,
        public string $receiveFrom,
    ) {
    }
}
