<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tilttek\TableSafety\Feedback\StarsAndWishes;

/**
 * The form of a participant's stars and wishes after a session: two text areas, one star or one wish per line. Its
 * data is the two texts; `lines()` splits them.
 *
 * @extends AbstractType<array{stars?: ?string, wishes?: ?string}>
 */
final class StarsAndWishesType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $limit = ['maxlength' => StarsAndWishes::MAX_ITEMS * (StarsAndWishes::MAX_LENGTH + 1), 'rows' => 4];

        $builder
            ->add('stars', TextareaType::class, ['label' => 'feedback.stars', 'help' => 'feedback.one_per_line', 'required' => false, 'attr' => $limit])
            ->add('wishes', TextareaType::class, ['label' => 'feedback.wishes', 'help' => 'feedback.one_per_line', 'required' => false, 'attr' => $limit]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'tilttek_table_safety']);
    }

    /**
     * The lines of a text area, each trimmed, empty ones left out.
     *
     * @return list<string>
     */
    public static function lines(mixed $text): array
    {
        if (!\is_string($text)) {
            return [];
        }

        $lines = preg_split('/\R/u', $text);

        return false === $lines ? [] : array_values(array_filter(array_map(trim(...), $lines), static fn (string $line): bool => '' !== $line));
    }

    /**
     * @param array<array-key, mixed> $data what the form returned
     */
    public static function feedback(array $data): StarsAndWishes
    {
        return new StarsAndWishes(self::lines($data['stars'] ?? null), self::lines($data['wishes'] ?? null));
    }

    /**
     * @return array{stars: string, wishes: string}
     */
    public static function data(StarsAndWishes $feedback): array
    {
        return ['stars' => implode("\n", $feedback->stars), 'wishes' => implode("\n", $feedback->wishes)];
    }
}
