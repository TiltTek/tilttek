<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Export;

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Validation;

/**
 * An army list as a fragment of HTML, every value escaped, with no script and no style: an `<article
 * class="army-list">` the application places in its page and prints with its own style sheet.
 */
final readonly class HtmlExport
{
    public function render(ArmyList $list, Catalogue $catalogue, ?Validation $validation = null): string
    {
        $sheet = Sheet::of($list, $catalogue, $validation);
        $html = '<article class="army-list">'."\n"
            .'<header><h1>'.self::escape($sheet->title).'</h1><p>'.self::escape($sheet->subtitle).'</p></header>'."\n";

        foreach ($sheet->detachments as $detachment) {
            $html .= '<section class="army-list__detachment"><h2>'.self::escape($detachment['heading']).'</h2>'."\n"
                .'<ul class="army-list__units">'."\n";

            foreach ($detachment['units'] as $unit) {
                $html .= '<li><span class="army-list__unit">'.self::escape($unit['label']).'</span> — <span class="army-list__points">'.self::escape($unit['points']).'</span>';

                if ([] !== $unit['options']) {
                    $html .= '<ul class="army-list__options">'.implode('', array_map(static fn (string $option): string => '<li>'.self::escape($option).'</li>', $unit['options'])).'</ul>';
                }

                $html .= '</li>'."\n";
            }

            $html .= '</ul></section>'."\n";
        }

        if ([] !== $sheet->violations) {
            $html .= '<section class="army-list__violations"><h2>Violations</h2><ul>'
                .implode('', array_map(static fn (string $violation): string => '<li>'.self::escape($violation).'</li>', $sheet->violations))
                .'</ul></section>'."\n";
        }

        return $html.'</article>'."\n";
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
