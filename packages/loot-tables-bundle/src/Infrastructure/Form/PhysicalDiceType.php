<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootTable;

/**
 * The form a cashier fills with the physical dice a buyer rolled for a loot box: one field per unit of the line, the
 * dice of the unit written as its shop code — one character per die, in the order of the tables. A code that does not
 * read as the dice of the box is refused on its field, before anything is entered.
 *
 * Its options are what `PhysicalDice::toEnter()` gives, the box and the number of units; `codes()` reads the codes of
 * a submitted form, in the order of the units, for `PhysicalDice::enter()`. The register shows it with the component
 * `TilttekLootTables:PhysicalDice`.
 *
 * @extends AbstractType<array<string, string|null>>
 */
final class PhysicalDiceType extends AbstractType
{
    public const string DOMAIN = 'tilttek_loot_tables';

    /** The most units one form asks for: a register enters a line of a few loot boxes at a time. */
    public const int MAX_UNITS = 100;

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * The codes of a submitted form, one per unit, in the order of the units.
     *
     * @param FormInterface<array<string, string|null>|null> $form
     *
     * @return list<string>
     */
    public static function codes(FormInterface $form): array
    {
        $codes = [];

        foreach ($form as $name => $field) {
            if (str_starts_with($name, 'unit_')) {
                $code = $field->getData();
                $codes[] = \is_string($code) ? trim($code) : '';
            }
        }

        return $codes;
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $box = $options['box'];
        $units = $options['units'];
        \assert($box instanceof LootBox);
        \assert(\is_int($units));
        $dice = \count($box->tables);
        $digits = array_all($box->tables, static fn (LootTable $table): bool => $table->sides() <= 9);
        $faces = substr(ShopCode::ALPHABET, 0, max(array_map(static fn (LootTable $table): int => $table->sides(), $box->tables)));
        $pattern = \sprintf('[%s]{%d}', $faces.strtolower(preg_replace('/\d/', '', $faces) ?? ''), $dice);
        $tables = implode(', ', array_map(static fn (LootTable $table): string => $table->name, $box->tables));

        for ($unit = 1; $unit <= $units; ++$unit) {
            $builder->add('unit_'.$unit, TextType::class, [
                'label' => 'physical_dice.unit',
                'label_translation_parameters' => ['%unit%' => $unit],
                'help' => 'physical_dice.help',
                'help_translation_parameters' => ['%tables%' => $tables, '%dice%' => $dice],
                'translation_domain' => self::DOMAIN,
                'trim' => true,
                'attr' => [
                    'minlength' => $dice,
                    'maxlength' => $dice,
                    'size' => max($dice, 2),
                    'pattern' => $pattern,
                    'inputmode' => $digits ? 'numeric' : 'text',
                    'autocomplete' => 'off',
                    'autocapitalize' => 'characters',
                    'spellcheck' => 'false',
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($box, $dice): void {
            foreach ($event->getForm() as $field) {
                $data = $field->getData();
                $code = \is_string($data) ? $data : '';

                try {
                    ShopCode::read($box, $code);
                } catch (InvalidCode) {
                    $field->addError($this->error($code, $dice));
                }
            }
        });
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $box = $options['box'];
        \assert($box instanceof LootBox);
        $view->vars['loot_box'] = $box->name;
        $view->vars['loot_tables'] = array_map(static fn (LootTable $table): string => $table->name, $box->tables);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['box', 'units']);
        $resolver->setAllowedTypes('box', LootBox::class);
        $resolver->setAllowedTypes('units', 'int');
        $resolver->setAllowedValues('units', static fn (int $units): bool => $units >= 1 && $units <= self::MAX_UNITS);
        $resolver->setDefault('translation_domain', self::DOMAIN);
    }

    private function error(string $code, int $dice): FormError
    {
        $template = 'physical_dice.invalid';
        $parameters = ['%code%' => $code, '%dice%' => $dice];

        return new FormError($this->translator?->trans($template, $parameters, self::DOMAIN) ?? strtr('"%code%" is not the dice of this box: one character per die, %dice% dice.', $parameters), $template, $parameters);
    }
}
