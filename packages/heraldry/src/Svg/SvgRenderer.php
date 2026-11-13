<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Svg;

use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Exception\InvalidDrawing;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\ShieldShape;
use Tilttek\Heraldry\Tincture;

/**
 * Draws a coat of arms as an SVG image, with the shapes of this package only: no raster image, no font, no text but
 * its title and description, no script, no style sheet, no reference outside the image.
 *
 * The image is accessible: `role="img"`, its `<title>` names it and its `<desc>` holds the blazon, both referred to by
 * `aria-labelledby`. Every `id` starts with the prefix given, or with one derived from the arms, so that several images
 * share a page without clashing.
 */
final readonly class SvgRenderer
{
    public function __construct(
        private Palette $palette = new Palette(),
        private Blazonry $blazonry = new Blazonry(),
    ) {
    }

    /**
     * @param ?string $title the image's accessible name; "Coat of arms" or « Armoiries » when null
     * @param ?string $id    the prefix of every `id` of the image, a letter then up to 47 letters, digits, `-` or `_`;
     *                       derived from the arms and the shape when null
     *
     * @throws InvalidDrawing when the prefix is not such a text
     */
    public function render(Coat $coat, ShieldShape $shape = ShieldShape::Heater, Language $language = Language::English, ?string $title = null, ?string $id = null): string
    {
        $description = $this->blazonry->write($coat, $language);
        $id ??= 'arms-'.substr(hash('xxh3', $shape->value.' '.$this->blazonry->write($coat)), 0, 12);

        if (1 !== preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,47}\z/', $id)) {
            throw InvalidDrawing::id();
        }

        $title ??= Language::French === $language ? 'Armoiries' : 'Coat of arms';
        $shield = Shapes::shield($shape);
        $layers = $this->field($coat, $shield, $id).$this->piece($coat->piece, $shield, $id).$this->charges($coat, $id).$this->brisure($coat->brisure, $id);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 204 244" width="204" height="244" role="img"'
            .' aria-labelledby="'.$id.'-title '.$id.'-desc">'
            .'<title id="'.$id.'-title">'.$this->text($title).'</title>'
            .'<desc id="'.$id.'-desc">'.$this->text($description).'</desc>'
            .'<defs><clipPath id="'.$id.'-shield"><path d="'.$shield.'"/></clipPath>'.$this->furs($coat, $id).'</defs>'
            .'<g clip-path="url(#'.$id.'-shield)">'.$layers.'</g>'
            .'<path d="'.$shield.'" fill="none" stroke="'.$this->palette->outline.'" stroke-width="3" stroke-linejoin="round"/>'
            .'</svg>';
    }

    private function field(Coat $coat, string $shield, string $id): string
    {
        $field = $coat->field;
        $svg = '<path d="'.$shield.'" fill="'.$this->fill($field->tinctures[0], $id).'"/>';

        if ($field->partition instanceof Partition) {
            $svg .= '<path d="'.Shapes::partition($field->partition).'" fill="'.$this->fill($field->tinctures[1], $id).'"/>';
        }

        return $svg;
    }

    private function piece(?Piece $piece, string $shield, string $id): string
    {
        if (!$piece instanceof Piece) {
            return '';
        }

        $fill = $this->fill($piece->tincture, $id);

        if (Ordinary::Bordure === $piece->ordinary) {
            return '<path d="'.$shield.'" fill="none" stroke="'.$fill.'" stroke-width="44"/>';
        }

        return $this->paths(Shapes::ordinary($piece->ordinary), $fill);
    }

    private function charges(Coat $coat, string $id): string
    {
        $charges = $coat->charges;

        if (!$charges instanceof ChargeGroup) {
            return '';
        }

        $ordinary = $coat->piece?->ordinary;
        $spots = match (true) {
            !$ordinary instanceof Ordinary => Shapes::inRegion(Shapes::FIELD, $charges->count, $charges->placement()),
            $charges->onPiece => Shapes::onOrdinary($ordinary, $charges->count),
            Ordinary::Chief === $ordinary => Shapes::inRegion(Shapes::BELOW_CHIEF, $charges->count, $charges->placement()),
            Ordinary::Bordure === $ordinary => Shapes::inRegion(Shapes::WITHIN_BORDURE, $charges->count, $charges->placement()),
            default => Shapes::aroundOrdinary($ordinary, $charges->count),
        };
        [$paths, $evenOdd] = Shapes::charge($charges->charge);
        $fill = $this->fill($charges->tincture, $id);
        $svg = '';

        foreach ($spots as [$x, $y, $size]) {
            $scale = $size / 100;
            $svg .= '<g transform="translate('.Shapes::number($x).' '.Shapes::number($y).') scale('.Shapes::number($scale).')"'
                .' fill="'.$fill.'" stroke="'.$this->palette->outline.'" stroke-width="'.Shapes::number(1.5 / $scale).'"'
                .($evenOdd ? ' fill-rule="evenodd"' : '').'>';

            foreach ($paths as $path) {
                $svg .= '<path d="'.$path.'"/>';
            }

            $svg .= '</g>';
        }

        return $svg;
    }

    private function brisure(?Brisure $brisure, string $id): string
    {
        return $brisure instanceof Brisure ? $this->paths(Shapes::brisure($brisure->mark), $this->fill($brisure->tincture, $id)) : '';
    }

    /**
     * @param list<string> $paths
     */
    private function paths(array $paths, string $fill): string
    {
        $svg = '';

        foreach ($paths as $path) {
            $svg .= '<path d="'.$path.'" fill="'.$fill.'" stroke="'.$this->palette->outline.'" stroke-width="1.5"/>';
        }

        return $svg;
    }

    /** The patterns of the furs the arms use, once each. */
    private function furs(Coat $coat, string $id): string
    {
        $tinctures = [
            ...$coat->field->tinctures,
            $coat->piece?->tincture,
            $coat->charges?->tincture,
            $coat->brisure?->tincture,
        ];
        $svg = '';
        $argent = $this->palette->colour(Tincture::Argent);

        if (\in_array(Tincture::Ermine, $tinctures, true)) {
            $spot = 'M0,-1 L-4,9 L0,6 L4,9 Z '.Shapes::circle(0, -7, 2.2).' '.Shapes::circle(-4.5, -3, 2.2).' '.Shapes::circle(4.5, -3, 2.2);
            $svg .= '<pattern id="'.$id.'-ermine" width="40" height="44" patternUnits="userSpaceOnUse">'
                .'<rect width="40" height="44" fill="'.$argent.'"/>'
                .'<path d="'.$spot.'" fill="'.$this->palette->colour(Tincture::Sable).'" transform="translate(10 11)"/>'
                .'<path d="'.$spot.'" fill="'.$this->palette->colour(Tincture::Sable).'" transform="translate(30 33)"/>'
                .'</pattern>';
        }

        if (\in_array(Tincture::Vair, $tinctures, true)) {
            $svg .= '<pattern id="'.$id.'-vair" width="40" height="40" patternUnits="userSpaceOnUse">'
                .'<rect width="40" height="40" fill="'.$this->palette->colour(Tincture::Azure).'"/>'
                .'<path d="M0,20 L5,15 V5 L10,0 L15,5 V15 L20,20 Z M20,20 L25,15 V5 L30,0 L35,5 V15 L40,20 Z'
                .' M-10,40 L-5,35 V25 L0,20 L5,25 V35 L10,40 Z M10,40 L15,35 V25 L20,20 L25,25 V35 L30,40 Z'
                .' M30,40 L35,35 V25 L40,20 L45,25 V35 L50,40 Z" fill="'.$argent.'"/>'
                .'</pattern>';
        }

        return $svg;
    }

    private function fill(Tincture $tincture, string $id): string
    {
        return match ($tincture) {
            Tincture::Ermine => 'url(#'.$id.'-ermine)',
            Tincture::Vair => 'url(#'.$id.'-vair)',
            default => $this->palette->colour($tincture),
        };
    }

    private function text(string $text): string
    {
        // XML admits no control character but tab and line breaks: they are left out rather than refused.
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text), \ENT_XML1 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
