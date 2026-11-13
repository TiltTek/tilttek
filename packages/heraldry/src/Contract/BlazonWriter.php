<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Contract;

use Tilttek\Heraldry\Coat;

/**
 * Writes the blazon of a coat of arms: the description from which a herald draws it, in the order and with the
 * agreements of one language.
 */
interface BlazonWriter
{
    /** The blazon, beginning with a capital and without a closing full stop. */
    public function write(Coat $coat): string;
}
