<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Blazon;

use Override;
use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Contract\BlazonWriter;
use Tilttek\Heraldry\Field;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\Tincture;

/**
 * The blazon in English: the field first, then what lies on it, each charge's number, name and arrangement before its
 * tincture — "Azure, three mullets in fess Or".
 *
 * Tinctures are written in lowercase but Or, which keeps its capital to stay apart from the conjunction. A tincture
 * shared by an ordinary and the charges written beside it is written once, at the end; a charge on the ordinary in the
 * tincture of the field is "of the field"; a roundel takes the name of its tincture — bezant, plate, torteau, hurt,
 * pomme, pellet, golpe — and no tincture word.
 */
final readonly class EnglishBlazon implements BlazonWriter
{
    private const array TINCTURES = [
        'or' => 'Or',
        'argent' => 'argent',
        'gules' => 'gules',
        'azure' => 'azure',
        'vert' => 'vert',
        'sable' => 'sable',
        'purpure' => 'purpure',
        'ermine' => 'ermine',
        'vair' => 'vair',
    ];

    private const array PARTITIONS = [
        'per_pale' => 'Per pale',
        'per_fess' => 'Per fess',
        'per_bend' => 'Per bend',
        'per_bend_sinister' => 'Per bend sinister',
        'quarterly' => 'Quarterly',
        'per_saltire' => 'Per saltire',
    ];

    /** The names of a charge, singular and plural. */
    private const array CHARGES = [
        'mullet' => ['mullet', 'mullets'],
        'crescent' => ['crescent', 'crescents'],
        'roundel' => ['roundel', 'roundels'],
        'annulet' => ['annulet', 'annulets'],
        'lozenge' => ['lozenge', 'lozenges'],
        'mascle' => ['mascle', 'mascles'],
        'billet' => ['billet', 'billets'],
        'fleur_de_lis' => ['fleur-de-lis', 'fleurs-de-lis'],
    ];

    /** The roundels named by their tincture, singular and plural. */
    private const array ROUNDELS = [
        'or' => ['bezant', 'bezants'],
        'argent' => ['plate', 'plates'],
        'gules' => ['torteau', 'torteaux'],
        'azure' => ['hurt', 'hurts'],
        'vert' => ['pomme', 'pommes'],
        'sable' => ['pellet', 'pellets'],
        'purpure' => ['golpe', 'golpes'],
    ];

    #[Override]
    public function write(Coat $coat): string
    {
        $parts = [$this->field($coat->field)];
        $piece = $coat->piece;
        $charges = $coat->charges;

        if ($piece instanceof Piece && $charges instanceof ChargeGroup) {
            array_push($parts, ...$this->pieceAndCharges($coat->field, $piece, $charges));
        } elseif ($piece instanceof Piece) {
            $parts[] = $this->ordinary($piece->ordinary).' '.$this->tincture($piece->tincture);
        } elseif ($charges instanceof ChargeGroup) {
            $parts[] = $this->charges($charges, true).$this->tinctureOf($charges);
        }

        if ($coat->brisure instanceof Brisure) {
            $parts[] = $this->brisure($coat->brisure, \count($parts) > 1);
        }

        return implode(', ', $parts);
    }

    private function field(Field $field): string
    {
        if ($field->isPlain()) {
            return ucfirst($this->tincture($field->tincture()));
        }

        \assert($field->partition instanceof Partition);

        return self::PARTITIONS[$field->partition->value].' '.$this->tincture($field->tinctures[0]).' and '.$this->tincture($field->tinctures[1]);
    }

    /**
     * @return list<string>
     */
    private function pieceAndCharges(Field $field, Piece $piece, ChargeGroup $charges): array
    {
        $ordinary = $this->ordinary($piece->ordinary);
        $tincture = $this->tincture($piece->tincture);
        $shared = $charges->tincture === $piece->tincture && !$this->isNamed($charges);

        if ($charges->onPiece) {
            $ofTheField = $field->isPlain() && $field->tincture() === $charges->tincture && !$this->isNamed($charges);

            return ['on '.$ordinary.' '.$tincture.' '.$this->charges($charges, false).($ofTheField ? ' of the field' : $this->tinctureOf($charges))];
        }

        if ($piece->ordinary->surroundsCharges()) {
            return [$shared
                ? $ordinary.' between '.$this->charges($charges, false).' '.$tincture
                : $ordinary.' '.$tincture.' between '.$this->charges($charges, false).$this->tinctureOf($charges)];
        }

        $beside = $this->charges($charges, true);

        if (Ordinary::Bordure === $piece->ordinary) {
            return [$beside.($shared ? '' : $this->tinctureOf($charges)).' within '.$ordinary.' '.$tincture];
        }

        return $shared ? [$beside.' and '.$ordinary.' '.$tincture] : [$beside.$this->tinctureOf($charges), $ordinary.' '.$tincture];
    }

    /**
     * The number, the name and — on the field — the arrangement of the charges, without their tincture.
     */
    private function charges(ChargeGroup $charges, bool $arranged): string
    {
        $names = (Charge::Roundel === $charges->charge ? self::ROUNDELS[$charges->tincture->value] ?? null : null) ?? self::CHARGES[$charges->charge->value];

        if (1 === $charges->count) {
            return (Charge::Annulet === $charges->charge ? 'an ' : 'a ').$names[0];
        }

        $words = (2 === $charges->count ? 'two ' : 'three ').$names[1];

        return $arranged ? $words.match ($charges->placement()) {
            Arrangement::InFess => ' in fess',
            Arrangement::InPale => ' in pale',
            Arrangement::Usual => '',
        } : $words;
    }

    /** The tincture of the charges, after a space; nothing for a named roundel. */
    private function tinctureOf(ChargeGroup $charges): string
    {
        return $this->isNamed($charges) ? '' : ' '.$this->tincture($charges->tincture);
    }

    /** Whether the charges are roundels named by their tincture. */
    private function isNamed(ChargeGroup $charges): bool
    {
        return Charge::Roundel === $charges->charge && isset(self::ROUNDELS[$charges->tincture->value]);
    }

    private function ordinary(Ordinary $ordinary): string
    {
        return 'a '.str_replace('_', ' ', $ordinary->value);
    }

    private function brisure(Brisure $brisure, bool $overall): string
    {
        return match ($brisure->mark) {
            BrisureMark::Label => 'a label',
            BrisureMark::Bendlet => ($overall ? 'overall ' : '').'a bendlet',
        }.' '.$this->tincture($brisure->tincture);
    }

    private function tincture(Tincture $tincture): string
    {
        return self::TINCTURES[$tincture->value];
    }
}
