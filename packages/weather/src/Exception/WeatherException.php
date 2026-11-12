<?php

declare(strict_types=1);

namespace Tilttek\Weather\Exception;

use Throwable;

/**
 * Every exception the library throws: a caller catches this one interface to catch them all. An exception of
 * `tilttek/generator` thrown by a draw reaches the caller as it is, under its own `GeneratorException`.
 */
interface WeatherException extends Throwable
{
}
