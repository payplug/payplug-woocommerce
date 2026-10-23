<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Controller;

use Payplug\PayplugWoocommerce\Controller\HostedFields;
use PHPUnit\Framework\TestCase;

class HostedFields_test extends TestCase
{
    public function testTemplateFormRendersFieldContainers(): void
    {
        $html = HostedFields::template_form(false);

        $this->assertStringContainsString('id="hosted-fields-brand"', $html);
        $this->assertStringContainsString('id="hosted-fields-card"', $html);
        $this->assertStringContainsString('id="hosted-fields-expiry"', $html);
        $this->assertStringContainsString('id="hosted-fields-cryptogram"', $html);
        $this->assertStringContainsString('id="hf-token"', $html);
        $this->assertStringContainsString('id="hf-selected-brand"', $html);
    }

    public function testTemplateFormHidesSaveCardCheckboxWhenNotAvailable(): void
    {
        $html = HostedFields::template_form(false);

        $this->assertStringNotContainsString('name="savecard"', $html);
    }

    public function testTemplateFormShowsSaveCardCheckboxWhenAvailable(): void
    {
        $html = HostedFields::template_form(true);

        $this->assertStringContainsString('name="savecard"', $html);
    }

    public function testTemplateFormRendersARadioForEachSavedCard(): void
    {
        $card = (object) ['id' => 5, 'brand' => 'VISA', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030];

        $html = HostedFields::template_form(false, [$card]);

        $this->assertStringContainsString('name="payplug_uhf_card_choice"', $html);
        $this->assertStringContainsString('value="5"', $html);
        $this->assertStringContainsString('VISA', $html);
        $this->assertStringContainsString('4242', $html);
    }

    public function testTemplateFormRendersAnOtherCardOptionCheckedByDefaultWhenCardsExist(): void
    {
        $card = (object) ['id' => 5, 'brand' => 'VISA', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030];

        $html = HostedFields::template_form(false, [$card]);

        $this->assertStringContainsString('value="other" checked', $html);
    }

    public function testTemplateFormRendersNoCardRadiosWhenThereAreNoSavedCards(): void
    {
        $html = HostedFields::template_form(false, []);

        $this->assertStringNotContainsString('payplug_uhf_card_choice', $html);
    }

    public function testTemplateFormAlwaysRendersTheThreeCardFallbackHiddenFields(): void
    {
        $html = HostedFields::template_form(false);

        $this->assertStringContainsString('id="hf-last4"', $html);
        $this->assertStringContainsString('id="hf-expiration-month"', $html);
        $this->assertStringContainsString('id="hf-expiration-year"', $html);
    }

    public function testTemplateFormRendersTheSavedCardsBlockBeforeAndOutsideTheHostedFieldsForm(): void
    {
        $card = (object) ['id' => 5, 'brand' => 'VISA', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030];

        $html = HostedFields::template_form(false, [$card]);

        $saved_cards_position = strpos($html, 'HostedFields_savedCards');
        $form_position = strpos($html, '<form class="payplug HostedFields');

        $this->assertNotFalse($saved_cards_position);
        $this->assertNotFalse($form_position);
        $this->assertLessThan($form_position, $saved_cards_position, 'The saved-cards block must render before (and outside) the hosted-fields <form>, not nested inside it.');
    }
}
