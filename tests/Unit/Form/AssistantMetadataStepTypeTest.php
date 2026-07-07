<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Assistant\Model\ModelMap;
use App\Form\AssistantMetadataStepType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Unit tests for the step-2 metadata form type's view augmentation.
 *
 * finishView() exposes the known-model choices to the language-model
 * field for the template's `<datalist>`, but the field only exists while
 * this is the flow's active step, so the two branches (child present /
 * absent) are covered directly here.
 */
final class AssistantMetadataStepTypeTest extends TestCase
{
    private function type(): AssistantMetadataStepType
    {
        return new AssistantMetadataStepType(new ModelMap(\dirname(__DIR__, 3).'/config/model_map.yaml'));
    }

    // Verifies finishView() exposes the model choices and datalist id when the field is present.
    public function testFinishViewExposesModelChoices(): void
    {
        $view = new FormView();
        $view->children['languageModel'] = new FormView($view);

        $this->type()->finishView($view, $this->createStub(FormInterface::class), []);

        self::assertNotEmpty($view->children['languageModel']->vars['model_choices']);
        self::assertSame('metadata-model-options', $view->children['languageModel']->vars['model_datalist_id']);
    }

    // Verifies finishView() is a no-op when the language-model field is absent (an inactive step).
    public function testFinishViewSkipsWhenFieldAbsent(): void
    {
        $view = new FormView();

        $this->type()->finishView($view, $this->createStub(FormInterface::class), []);

        self::assertSame([], $view->children);
    }
}
