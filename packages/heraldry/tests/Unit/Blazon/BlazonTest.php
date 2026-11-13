<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Unit\Blazon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Blazon\EnglishBlazon;
use Tilttek\Heraldry\Blazon\FrenchBlazon;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Field;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\Tincture;

#[CoversClass(EnglishBlazon::class)]
#[CoversClass(FrenchBlazon::class)]
#[CoversClass(Blazonry::class)]
final class BlazonTest extends TestCase
{
    /**
     * @return iterable<string, array{Coat, string, string}>
     */
    public static function coats(): iterable
    {
        $azure = Field::plain(Tincture::Azure);

        yield 'a plain field' => [new Coat($azure), 'Azure', 'D’azur plein'];
        yield 'a plain field of a tincture without elision' => [new Coat(Field::plain(Tincture::Gules)), 'Gules', 'De gueules plein'];
        yield 'a fur' => [new Coat(Field::plain(Tincture::Ermine)), 'Ermine', 'D’hermine plein'];
        yield 'Or keeps its capital' => [new Coat(Field::plain(Tincture::Or), new Piece(Ordinary::Pale, Tincture::Vert)), 'Or, a pale vert', 'D’or au pal de sinople'];
        yield 'a feminine ordinary' => [new Coat(Field::plain(Tincture::Argent), new Piece(Ordinary::Cross, Tincture::Purpure)), 'Argent, a cross purpure', 'D’argent à la croix de pourpre'];
        yield 'a bend sinister' => [new Coat(Field::plain(Tincture::Or), new Piece(Ordinary::BendSinister, Tincture::Sable)), 'Or, a bend sinister sable', 'D’or à la barre de sable'];
        yield 'a saltire' => [new Coat(Field::plain(Tincture::Or), new Piece(Ordinary::Saltire, Tincture::Vair)), 'Or, a saltire vair', 'D’or au sautoir de vair'];

        yield 'per pale' => [new Coat(Field::divided(Partition::PerPale, Tincture::Or, Tincture::Gules)), 'Per pale Or and gules', 'Parti d’or et de gueules'];
        yield 'per fess' => [new Coat(Field::divided(Partition::PerFess, Tincture::Azure, Tincture::Argent)), 'Per fess azure and argent', 'Coupé d’azur et d’argent'];
        yield 'per bend' => [new Coat(Field::divided(Partition::PerBend, Tincture::Vert, Tincture::Or)), 'Per bend vert and Or', 'Tranché de sinople et d’or'];
        yield 'per bend sinister' => [new Coat(Field::divided(Partition::PerBendSinister, Tincture::Sable, Tincture::Argent)), 'Per bend sinister sable and argent', 'Taillé de sable et d’argent'];
        yield 'quarterly' => [new Coat(Field::divided(Partition::Quarterly, Tincture::Or, Tincture::Azure)), 'Quarterly Or and azure', 'Écartelé d’or et d’azur'];
        yield 'per saltire' => [new Coat(Field::divided(Partition::PerSaltire, Tincture::Ermine, Tincture::Gules)), 'Per saltire ermine and gules', 'Écartelé en sautoir d’hermine et de gueules'];
        yield 'a divided field with an ordinary' => [
            new Coat(Field::divided(Partition::PerPale, Tincture::Or, Tincture::Gules), new Piece(Ordinary::Chevron, Tincture::Azure)),
            'Per pale Or and gules, a chevron azure',
            'Parti d’or et de gueules, au chevron d’azur',
        ];

        yield 'one charge, with an elided article' => [new Coat($azure, charges: new ChargeGroup(Charge::Mullet, Tincture::Or)), 'Azure, a mullet Or', 'D’azur à l’étoile d’or'];
        yield 'one masculine charge' => [new Coat($azure, charges: new ChargeGroup(Charge::Crescent, Tincture::Argent)), 'Azure, a crescent argent', 'D’azur au croissant d’argent'];
        yield 'one feminine charge' => [new Coat($azure, charges: new ChargeGroup(Charge::Billet, Tincture::Or)), 'Azure, a billet Or', 'D’azur à la billette d’or'];
        yield 'an annulet' => [new Coat(Field::plain(Tincture::Gules), charges: new ChargeGroup(Charge::Annulet, Tincture::Or)), 'Gules, an annulet Or', 'De gueules à l’annelet d’or'];
        yield 'three charges set usually' => [new Coat($azure, charges: new ChargeGroup(Charge::FleurDeLis, Tincture::Or, 3)), 'Azure, three fleurs-de-lis Or', 'D’azur à trois fleurs de lis d’or'];
        yield 'three feminine charges in fess' => [
            new Coat($azure, charges: new ChargeGroup(Charge::Mullet, Tincture::Or, 3, Arrangement::InFess)),
            'Azure, three mullets in fess Or',
            'D’azur à trois étoiles d’or rangées en fasce',
        ];
        yield 'three masculine charges in pale' => [
            new Coat($azure, charges: new ChargeGroup(Charge::Lozenge, Tincture::Argent, 3, Arrangement::InPale)),
            'Azure, three lozenges in pale argent',
            'D’azur à trois losanges d’argent posés en pal',
        ];
        yield 'two charges stand in pale' => [
            new Coat($azure, charges: new ChargeGroup(Charge::Mascle, Tincture::Or, 2)),
            'Azure, two mascles in pale Or',
            'D’azur à deux macles d’or posées en pal',
        ];
        yield 'two masculine charges in fess' => [
            new Coat($azure, charges: new ChargeGroup(Charge::Crescent, Tincture::Or, 2, Arrangement::InFess)),
            'Azure, two crescents in fess Or',
            'D’azur à deux croissants d’or rangés en fasce',
        ];

        yield 'roundels of a metal are bezants' => [
            new Coat(Field::plain(Tincture::Gules), charges: new ChargeGroup(Charge::Roundel, Tincture::Or, 3)),
            'Gules, three bezants',
            'De gueules à trois besants d’or',
        ];
        yield 'a roundel of argent is a plate' => [new Coat(Field::plain(Tincture::Sable), charges: new ChargeGroup(Charge::Roundel, Tincture::Argent)), 'Sable, a plate', 'De sable au besant d’argent'];
        yield 'roundels of a colour are tourteaux' => [
            new Coat(Field::plain(Tincture::Argent), charges: new ChargeGroup(Charge::Roundel, Tincture::Gules, 2)),
            'Argent, two torteaux in pale',
            'D’argent à deux tourteaux de gueules posés en pal',
        ];
        yield 'hurts' => [new Coat(Field::plain(Tincture::Or), charges: new ChargeGroup(Charge::Roundel, Tincture::Azure)), 'Or, a hurt', 'D’or au tourteau d’azur'];
        yield 'pommes' => [new Coat(Field::plain(Tincture::Or), charges: new ChargeGroup(Charge::Roundel, Tincture::Vert, 3)), 'Or, three pommes', 'D’or à trois tourteaux de sinople'];
        yield 'pellets' => [new Coat(Field::plain(Tincture::Argent), charges: new ChargeGroup(Charge::Roundel, Tincture::Sable)), 'Argent, a pellet', 'D’argent au tourteau de sable'];
        yield 'golpes' => [new Coat(Field::plain(Tincture::Or), charges: new ChargeGroup(Charge::Roundel, Tincture::Purpure, 2, Arrangement::InFess)), 'Or, two golpes in fess', 'D’or à deux tourteaux de pourpre rangés en fasce'];
        yield 'a roundel of fur keeps its tincture' => [
            new Coat(Field::plain(Tincture::Gules), charges: new ChargeGroup(Charge::Roundel, Tincture::Ermine)),
            'Gules, a roundel ermine',
            'De gueules au tourteau d’hermine',
        ];

        yield 'charges on the ordinary' => [
            new Coat($azure, new Piece(Ordinary::Fess, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Gules, 3, onPiece: true)),
            'Azure, on a fess Or three mullets gules',
            'D’azur à la fasce d’or chargée de trois étoiles de gueules',
        ];
        yield 'one charge on a masculine ordinary' => [
            new Coat($azure, new Piece(Ordinary::Chevron, Tincture::Or), new ChargeGroup(Charge::Crescent, Tincture::Gules, onPiece: true)),
            'Azure, on a chevron Or a crescent gules',
            'D’azur au chevron d’or chargé d’un croissant de gueules',
        ];
        yield 'one feminine charge on the ordinary' => [
            new Coat($azure, new Piece(Ordinary::Cross, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Gules, onPiece: true)),
            'Azure, on a cross Or a mullet gules',
            'D’azur à la croix d’or chargée d’une étoile de gueules',
        ];
        yield 'charges on the ordinary of the field' => [
            new Coat($azure, new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::FleurDeLis, Tincture::Azure, 3, onPiece: true)),
            'Azure, on a chief Or three fleurs-de-lis of the field',
            'D’azur au chef d’or chargé de trois fleurs de lis du champ',
        ];
        yield 'named roundels on the ordinary of the field' => [
            new Coat($azure, new Piece(Ordinary::Bend, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Azure, 2, onPiece: true)),
            'Azure, on a bend Or two hurts',
            'D’azur à la bande d’or chargée de deux tourteaux du champ',
        ];
        yield 'charges on the ordinary of a divided field' => [
            new Coat(Field::divided(Partition::PerFess, Tincture::Azure, Tincture::Gules), new Piece(Ordinary::Pale, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Azure, 3, onPiece: true)),
            'Per fess azure and gules, on a pale Or three mullets azure',
            'Coupé d’azur et de gueules, au pal d’or chargé de trois étoiles d’azur',
        ];

        yield 'charges around a feminine ordinary' => [
            new Coat($azure, new Piece(Ordinary::Fess, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Argent, 3)),
            'Azure, a fess Or between three mullets argent',
            'D’azur à la fasce d’or accompagnée de trois étoiles d’argent',
        ];
        yield 'charges around, in the tincture of the ordinary' => [
            new Coat($azure, new Piece(Ordinary::Fess, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Or, 2)),
            'Azure, a fess between two mullets Or',
            'D’azur à la fasce d’or accompagnée de deux étoiles du même',
        ];
        yield 'charges flanking a pale' => [
            new Coat($azure, new Piece(Ordinary::Pale, Tincture::Or), new ChargeGroup(Charge::Annulet, Tincture::Or, 2)),
            'Azure, a pale between two annulets Or',
            'D’azur au pal d’or accosté de deux annelets du même',
        ];
        yield 'charges around a masculine ordinary' => [
            new Coat(Field::plain(Tincture::Gules), new Piece(Ordinary::Chevron, Tincture::Argent), new ChargeGroup(Charge::Mullet, Tincture::Or, 3)),
            'Gules, a chevron argent between three mullets Or',
            'De gueules au chevron d’argent accompagné de trois étoiles d’or',
        ];
        yield 'named roundels around the ordinary' => [
            new Coat($azure, new Piece(Ordinary::Bend, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Or, 2)),
            'Azure, a bend Or between two bezants',
            'D’azur à la bande d’or accompagnée de deux besants du même',
        ];

        yield 'charges with a chief' => [
            new Coat($azure, new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Argent, 3)),
            'Azure, three mullets argent, a chief Or',
            'D’azur à trois étoiles d’argent, au chef d’or',
        ];
        yield 'charges and a chief of one tincture' => [
            new Coat($azure, new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Or, 2, Arrangement::InFess)),
            'Azure, two mullets in fess and a chief Or',
            'D’azur à deux étoiles d’or rangées en fasce, au chef d’or',
        ];
        yield 'named roundels with a chief' => [
            new Coat($azure, new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Or, 3)),
            'Azure, three bezants, a chief Or',
            'D’azur à trois besants d’or, au chef d’or',
        ];
        yield 'a charge within a bordure' => [
            new Coat($azure, new Piece(Ordinary::Bordure, Tincture::Gules), new ChargeGroup(Charge::Mullet, Tincture::Or)),
            'Azure, a mullet Or within a bordure gules',
            'D’azur à l’étoile d’or, à la bordure de gueules',
        ];
        yield 'charges within a bordure of their tincture' => [
            new Coat($azure, new Piece(Ordinary::Bordure, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Or, 3)),
            'Azure, three mullets within a bordure Or',
            'D’azur à trois étoiles d’or, à la bordure d’or',
        ];
        yield 'named roundels within a bordure' => [
            new Coat($azure, new Piece(Ordinary::Bordure, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Or, 3)),
            'Azure, three bezants within a bordure Or',
            'D’azur à trois besants d’or, à la bordure d’or',
        ];

        yield 'a label alone' => [new Coat($azure, brisure: new Brisure(BrisureMark::Label, Tincture::Gules)), 'Azure, a label gules', 'D’azur au lambel de gueules'];
        yield 'a bendlet alone' => [new Coat(Field::plain(Tincture::Ermine), brisure: new Brisure(BrisureMark::Bendlet, Tincture::Gules)), 'Ermine, a bendlet gules', 'D’hermine au bâton de gueules'];
        yield 'a bendlet over a divided field' => [
            new Coat(Field::divided(Partition::PerPale, Tincture::Or, Tincture::Gules), brisure: new Brisure(BrisureMark::Bendlet, Tincture::Sable)),
            'Per pale Or and gules, a bendlet sable',
            'Parti d’or et de gueules, au bâton de sable',
        ];
        yield 'a bendlet over everything' => [
            new Coat(Field::divided(Partition::PerPale, Tincture::Or, Tincture::Gules), new Piece(Ordinary::Bend, Tincture::Azure), brisure: new Brisure(BrisureMark::Bendlet, Tincture::Sable)),
            'Per pale Or and gules, a bend azure, overall a bendlet sable',
            'Parti d’or et de gueules, à la bande d’azur, au bâton de sable brochant sur le tout',
        ];
        yield 'a label over charges' => [
            new Coat($azure, charges: new ChargeGroup(Charge::Mullet, Tincture::Or, 3), brisure: new Brisure(BrisureMark::Label, Tincture::Argent)),
            'Azure, three mullets Or, a label argent',
            'D’azur à trois étoiles d’or, au lambel d’argent',
        ];
    }

    #[DataProvider('coats')]
    public function testItWritesTheBlazonInEnglishAndFrench(Coat $coat, string $english, string $french): void
    {
        self::assertSame($english, new EnglishBlazon()->write($coat));
        self::assertSame($french, new FrenchBlazon()->write($coat));
        self::assertSame($english, new Blazonry()->write($coat));
        self::assertSame($english, new Blazonry()->write($coat, Language::English));
        self::assertSame($french, new Blazonry()->write($coat, Language::French));
    }
}
