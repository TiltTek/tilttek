<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Blazon;

use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Contract\BlazonWriter;
use Tilttek\Heraldry\Language;

/**
 * The blazon of a coat of arms in the language asked for.
 */
final readonly class Blazonry
{
    public function __construct(
        private BlazonWriter $english = new EnglishBlazon(),
        private BlazonWriter $french = new FrenchBlazon(),
    ) {
    }

    public function write(Coat $coat, Language $language = Language::English): string
    {
        return match ($language) {
            Language::English => $this->english->write($coat),
            Language::French => $this->french->write($coat),
        };
    }
}
