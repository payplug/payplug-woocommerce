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
}
