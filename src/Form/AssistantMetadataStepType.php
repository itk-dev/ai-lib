<?php

declare(strict_types=1);

namespace App\Form;

use App\Framework\SupportedFrameworks;
use App\Validator\SupportedFramework;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 2 of the assistant-create wizard — "Gennemgang".
 *
 * The metadata fields the user reviews and edits before saving.
 * All fields inherit their initial values from the DTO, which
 * the flow's step-1 `POST_SUBMIT` listener pre-populated from
 * the OpenWebUI JSON. Validation lives in the `metadata` group
 * so step 1's `Json` constraint doesn't re-run on every step-2
 * submit.
 *
 * `framework` is a `<select>` backed by
 * {@see SupportedFrameworks}, which reads the deploy-time
 * `SUPPORTED_FRAMEWORKS` env var. The choice list mirrors the
 * one on the admin organisation form and shares the same
 * `SupportedFramework` machine-name validator.
 *
 * `tags` is a comma-separated textbox transformed to / from the
 * DTO's `list<string>` shape, matching the pattern the pre-flow
 * version of this page used.
 */
final class AssistantMetadataStepType extends AbstractType
{
    private const string INPUT_CLASS = 'rounded-lg border border-line bg-surface px-3 py-2 text-base text-ink focus:outline-none focus:ring-2 focus:ring-primary/40';
    private const string LABEL_CLASS = 'block font-medium text-ink';
    private const string ROW_CLASS = 'grid gap-1 text-sm';

    /**
     * @param SupportedFrameworks $frameworks deploy-time list feeding the framework `<select>` choices + validator
     */
    public function __construct(private readonly SupportedFrameworks $frameworks)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'assistant.new.step_metadata.title_label',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'assistant.new.step_metadata.title_required',
                        groups: ['metadata'],
                    ),
                ],
                'attr' => ['class' => self::INPUT_CLASS, 'autofocus' => true],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'row_attr' => ['class' => self::ROW_CLASS],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'assistant.new.step_metadata.description_label',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'assistant.new.step_metadata.description_required',
                        groups: ['metadata'],
                    ),
                ],
                'attr' => ['class' => self::INPUT_CLASS, 'rows' => 4],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'row_attr' => ['class' => self::ROW_CLASS],
            ])
            ->add('languageModel', TextType::class, [
                'label' => 'assistant.new.step_metadata.language_model_label',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'assistant.new.step_metadata.language_model_required',
                        groups: ['metadata'],
                    ),
                ],
                'attr' => ['class' => self::INPUT_CLASS],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'row_attr' => ['class' => self::ROW_CLASS],
            ])
            ->add('framework', ChoiceType::class, [
                'label' => 'assistant.new.step_metadata.framework_label',
                'help' => 'assistant.new.step_metadata.framework_help',
                'choices' => $this->frameworks->list(),
                'placeholder' => false,
                'required' => true,
                'empty_data' => $this->frameworks->default(),
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'assistant.new.step_metadata.framework_required',
                        groups: ['metadata'],
                    ),
                    new SupportedFramework(groups: ['metadata']),
                ],
                'attr' => ['class' => self::INPUT_CLASS],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'row_attr' => ['class' => self::ROW_CLASS],
            ])
            ->add('tags', TextType::class, [
                'label' => 'assistant.new.step_metadata.tags_label',
                'help' => 'assistant.new.step_metadata.tags_help',
                'required' => false,
                'empty_data' => '',
                'attr' => ['class' => self::INPUT_CLASS],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'row_attr' => ['class' => self::ROW_CLASS],
            ])
            ->get('tags')->addModelTransformer(new CallbackTransformer(
                /**
                 * @param list<string>|null $tags
                 */
                static fn (?array $tags): string => null === $tags ? '' : implode(', ', $tags),
                /** @return list<string> */
                static fn (?string $raw): array => array_values(array_filter(
                    array_map(static fn (string $t): string => trim($t), explode(',', (string) $raw)),
                    static fn (string $t): bool => '' !== $t,
                )),
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'validation_groups' => ['Default', 'metadata'],
        ]);
    }
}
