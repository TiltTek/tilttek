<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Attribution;

use Phil\Url\Url;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;

/**
 * The attribution a page that shows a system's records displays: one notice for each source whose licence asks for
 * one, with the licence and its address — as plain text, or as an HTML list safe to print.
 */
final readonly class AttributionRenderer
{
    /**
     * The sources of a system, or of the records a page shows, that ask for attribution, each once.
     *
     * @param GameSystem|list<Provenance> $what   a whole system, or the provenances of the records a page shows
     * @param ?string                     $locale the locale whose translations are shown; every translation when null
     * @param bool                        $all    whether the sources that ask for no attribution are listed too
     *
     * @return list<Provenance>
     */
    public function sources(GameSystem|array $what, ?string $locale = null, bool $all = false): array
    {
        $sources = $what instanceof GameSystem ? $what->provenances($locale) : $what;
        $distinct = [];

        foreach ($sources as $source) {
            if ($all || $source->licence->requiresAttribution()) {
                $distinct[$source->identity()] ??= $source;
            }
        }

        return array_values($distinct);
    }

    /**
     * One line for each source: its attribution, or its title, then its licence and the licence's address.
     *
     * @param GameSystem|list<Provenance> $what
     */
    public function text(GameSystem|array $what, ?string $locale = null, bool $all = false): string
    {
        return implode("\n", array_map($this->line(...), $this->sources($what, $locale, $all)));
    }

    /**
     * An HTML list of the sources, every text escaped, every address a link with `rel="license"` for the licence;
     * an empty string when no source asks for attribution.
     *
     * @param GameSystem|list<Provenance> $what
     */
    public function html(GameSystem|array $what, ?string $locale = null, bool $all = false): string
    {
        $sources = $this->sources($what, $locale, $all);

        if ([] === $sources) {
            return '';
        }

        $items = array_map(static function (Provenance $source): string {
            $statement = self::escape('' === $source->attribution ? $source->title : $source->attribution);

            if ($source->url instanceof Url && '' === $source->attribution) {
                $statement = \sprintf('<a href="%s">%s</a>', self::escape($source->url->toString()), $statement);
            }

            return \sprintf('<li>%s %s</li>', $statement, self::licence($source->licence));
        }, $sources);

        return "<ul class=\"attribution\">\n".implode("\n", $items)."\n</ul>";
    }

    private function line(Provenance $source): string
    {
        $statement = '' === $source->attribution ? $source->title : $source->attribution;

        if ($source->url instanceof Url && '' === $source->attribution) {
            $statement .= ' <'.$source->url->toString().'>';
        }

        $url = $source->licence->url();

        return null === $url
            ? \sprintf('%s (%s)', $statement, $source->licence->title())
            : \sprintf('%s (%s: %s)', $statement, $source->licence->title(), $url);
    }

    private static function licence(Licence $licence): string
    {
        $url = $licence->url();

        return null === $url
            ? \sprintf('(%s)', self::escape($licence->title()))
            : \sprintf('(<a href="%s" rel="license">%s</a>)', self::escape($url), self::escape($licence->title()));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
