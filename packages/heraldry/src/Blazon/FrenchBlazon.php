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
use Tilttek\Heraldry\TinctureKind;

/**
 * The blazon in French: the field first, then each piece introduced by « à » and its article, the tincture after the
 * name and before the arrangement — « D’azur à trois étoiles d’or rangées en fasce ».
 *
 * The article contracts (« au chef », « à la fasce », « à l’étoile »), « de » elides before a vowel (« d’or »,
 * « d’une étoile »), and participles agree with what they describe: « la fasce chargée », « le chevron accompagné »,
 * « le pal accosté », « trois étoiles rangées », « trois besants posés ». A charge on the ordinary in the tincture of
 * the field is « du champ », and one beside the ordinary in its tincture « du même »; a plain field alone is « plein »;
 * a roundel is a besant on metal and a tourteau otherwise.
 */
final readonly class FrenchBlazon implements BlazonWriter
{
    /** The name of each tincture, and whether « de » elides before it. */
    private const array TINCTURES = [
        'or' => ['or', true],
        'argent' => ['argent', true],
        'gules' => ['gueules', false],
        'azure' => ['azur', true],
        'vert' => ['sinople', false],
        'sable' => ['sable', false],
        'purpure' => ['pourpre', false],
        'ermine' => ['hermine', true],
        'vair' => ['vair', false],
    ];

    private const array PARTITIONS = [
        'per_pale' => 'Parti',
        'per_fess' => 'Coupé',
        'per_bend' => 'Tranché',
        'per_bend_sinister' => 'Taillé',
        'quarterly' => 'Écartelé',
        'per_saltire' => 'Écartelé en sautoir',
    ];

    /** The name of each ordinary, and whether it is feminine. */
    private const array ORDINARIES = [
        'chief' => ['chef', false],
        'fess' => ['fasce', true],
        'pale' => ['pal', false],
        'bend' => ['bande', true],
        'bend_sinister' => ['barre', true],
        'chevron' => ['chevron', false],
        'cross' => ['croix', true],
        'saltire' => ['sautoir', false],
        'bordure' => ['bordure', true],
    ];

    /** The names of each charge, singular and plural, whether it is feminine, and whether its article elides. */
    private const array CHARGES = [
        'mullet' => ['étoile', 'étoiles', true, true],
        'crescent' => ['croissant', 'croissants', false, false],
        'besant' => ['besant', 'besants', false, false],
        'tourteau' => ['tourteau', 'tourteaux', false, false],
        'annulet' => ['annelet', 'annelets', false, true],
        'lozenge' => ['losange', 'losanges', false, false],
        'mascle' => ['macle', 'macles', true, false],
        'billet' => ['billette', 'billettes', true, false],
        'fleur_de_lis' => ['fleur de lis', 'fleurs de lis', true, false],
    ];

    #[Override]
    public function write(Coat $coat): string
    {
        $elements = [];
        $piece = $coat->piece;
        $charges = $coat->charges;

        if ($piece instanceof Piece && $charges instanceof ChargeGroup) {
            array_push($elements, ...$this->pieceAndCharges($coat->field, $piece, $charges));
        } elseif ($piece instanceof Piece) {
            $elements[] = $this->piece($piece);
        } elseif ($charges instanceof ChargeGroup) {
            $elements[] = $this->chargesOnField($charges);
        }

        if ($coat->brisure instanceof Brisure) {
            $elements[] = $this->brisure($coat->brisure, [] !== $elements);
        }

        $field = $this->field($coat->field);

        if ([] === $elements) {
            return $coat->field->isPlain() ? $field.' plein' : $field;
        }

        return $field.($coat->field->isPlain() ? ' ' : ', ').implode(', ', $elements);
    }

    private function field(Field $field): string
    {
        if ($field->isPlain()) {
            return 'D'.mb_substr($this->of($field->tincture()), 1);
        }

        \assert($field->partition instanceof Partition);

        return self::PARTITIONS[$field->partition->value].' '.$this->of($field->tinctures[0]).' et '.$this->of($field->tinctures[1]);
    }

    /**
     * @return list<string>
     */
    private function pieceAndCharges(Field $field, Piece $piece, ChargeGroup $charges): array
    {
        [, $feminine] = self::ORDINARIES[$piece->ordinary->value];
        $agreement = $feminine ? 'e' : '';

        if ($charges->onPiece) {
            $tincture = $field->isPlain() && $field->tincture() === $charges->tincture ? 'du champ' : $this->of($charges->tincture);

            return [$this->piece($piece).' chargé'.$agreement.' '.$this->ofCount($charges).' '.$tincture];
        }

        if ($piece->ordinary->surroundsCharges()) {
            $verb = Ordinary::Pale === $piece->ordinary ? 'accosté' : 'accompagné';
            $tincture = $charges->tincture === $piece->tincture ? 'du même' : $this->of($charges->tincture);

            return [$this->piece($piece).' '.$verb.$agreement.' '.$this->ofCount($charges).' '.$tincture];
        }

        return [$this->chargesOnField($charges), $this->piece($piece)];
    }

    /** « à la fasce d’or », « au chef de gueules ». */
    private function piece(Piece $piece): string
    {
        [$name, $feminine] = self::ORDINARIES[$piece->ordinary->value];

        return ($feminine ? 'à la ' : 'au ').$name.' '.$this->of($piece->tincture);
    }

    /** « à l’étoile d’or », « à trois étoiles d’or rangées en fasce ». */
    private function chargesOnField(ChargeGroup $charges): string
    {
        [$singular, $plural, $feminine, $elides] = $this->names($charges);

        if (1 === $charges->count) {
            $article = $elides ? 'à l’' : ($feminine ? 'à la ' : 'au ');

            return $article.$singular.' '.$this->of($charges->tincture);
        }

        $words = 'à '.(2 === $charges->count ? 'deux' : 'trois').' '.$plural.' '.$this->of($charges->tincture);
        $agreement = $feminine ? 'es' : 's';

        return $words.match ($charges->placement()) {
            Arrangement::InFess => ' rangé'.$agreement.' en fasce',
            Arrangement::InPale => ' posé'.$agreement.' en pal',
            Arrangement::Usual => '',
        };
    }

    /** « d’une étoile », « d’un croissant », « de trois étoiles ». */
    private function ofCount(ChargeGroup $charges): string
    {
        [$singular, $plural, $feminine] = $this->names($charges);

        return 1 === $charges->count
            ? ($feminine ? 'd’une ' : 'd’un ').$singular
            : 'de '.(2 === $charges->count ? 'deux' : 'trois').' '.$plural;
    }

    /**
     * @return array{string, string, bool, bool}
     */
    private function names(ChargeGroup $charges): array
    {
        $key = Charge::Roundel === $charges->charge
            ? (TinctureKind::Metal === $charges->tincture->kind() ? 'besant' : 'tourteau')
            : $charges->charge->value;

        return self::CHARGES[$key];
    }

    private function brisure(Brisure $brisure, bool $overall): string
    {
        return match ($brisure->mark) {
            BrisureMark::Label => 'au lambel '.$this->of($brisure->tincture),
            BrisureMark::Bendlet => 'au bâton '.$this->of($brisure->tincture).($overall ? ' brochant sur le tout' : ''),
        };
    }

    /** « d’or », « de gueules ». */
    private function of(Tincture $tincture): string
    {
        [$name, $elides] = self::TINCTURES[$tincture->value];

        return ($elides ? 'd’' : 'de ').$name;
    }
}
