<?php

declare(strict_types=1);

namespace AI_SEO_Captain\Tests\Unit;

use AI_SEO_Captain\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Settings static utility methods.
 * Only pure static methods are tested here — no WP option DB calls.
 *
 * @covers \AI_SEO_Captain\Settings
 */
class SettingsTest extends TestCase
{
    // ------------------------------------------------------------------
    //  defaults()
    // ------------------------------------------------------------------

    public function test_defaults_returns_array(): void
    {
        $defaults = Settings::defaults();
        $this->assertIsArray($defaults);
    }

    public function test_defaults_has_required_keys(): void
    {
        $defaults = Settings::defaults();
        foreach (array('provider', 'model', 'ai_temperature', 'api_key', 'frontend_output_enabled') as $key) {
            $this->assertArrayHasKey($key, $defaults, "Missing default key: $key");
        }
    }

    public function test_defaults_openai_provider(): void
    {
        $this->assertSame('openai', Settings::defaults()['provider']);
    }

    public function test_defaults_temperature_within_bounds(): void
    {
        $temp = Settings::defaults()['ai_temperature'];
        $this->assertGreaterThanOrEqual(0.0, $temp);
        $this->assertLessThanOrEqual(2.0, $temp);
    }

    public function test_defaults_frontend_off_by_default(): void
    {
        $this->assertSame(0, Settings::defaults()['frontend_output_enabled']);
    }

    // ------------------------------------------------------------------
    //  get_supported_providers()
    // ------------------------------------------------------------------

    public function test_supported_providers_returns_array(): void
    {
        $this->assertIsArray(Settings::get_supported_providers());
    }

    public function test_supported_providers_includes_openai(): void
    {
        $this->assertContains('openai', Settings::get_supported_providers());
    }

    public function test_supported_providers_includes_google(): void
    {
        $this->assertContains('google', Settings::get_supported_providers());
    }

    // ------------------------------------------------------------------
    //  get_models_for_provider()
    // ------------------------------------------------------------------

    public function test_models_for_openai_not_empty(): void
    {
        $models = Settings::get_models_for_provider('openai');
        $this->assertNotEmpty($models);
    }

    public function test_models_for_invalid_provider_empty(): void
    {
        $models = Settings::get_models_for_provider('nonexistent_provider');
        $this->assertSame(array(), $models);
    }

    public function test_models_are_keyed_by_model_id(): void
    {
        $models = Settings::get_models_for_provider('openai');
        foreach ($models as $id => $label) {
            $this->assertIsString($id);
            $this->assertIsString($label);
            $this->assertNotEmpty($id);
        }
    }

    // ------------------------------------------------------------------
    //  sanitize_provider_model()
    // ------------------------------------------------------------------

    public function test_sanitize_valid_model_returned(): void
    {
        $models = Settings::get_models_for_provider('openai');
        $first_model = array_key_first($models);
        $this->assertSame($first_model, Settings::sanitize_provider_model('openai', $first_model));
    }

    public function test_sanitize_invalid_model_returns_default(): void
    {
        $result = Settings::sanitize_provider_model('openai', 'totally-fake-model-xyz');
        $this->assertSame(Settings::get_default_model_for_provider('openai'), $result);
    }

    // ------------------------------------------------------------------
    //  sanitize_custom_model_id()
    // ------------------------------------------------------------------

    public function test_custom_model_strips_disallowed_chars(): void
    {
        $result = Settings::sanitize_custom_model_id('my-model/v1.0@org!');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._:-]*$/', $result);
    }

    public function test_custom_model_empty_returns_empty(): void
    {
        $this->assertSame('', Settings::sanitize_custom_model_id(''));
    }

    public function test_custom_model_truncated_at_120_chars(): void
    {
        $long = str_repeat('a', 200);
        $result = Settings::sanitize_custom_model_id($long);
        $this->assertLessThanOrEqual(120, strlen($result));
    }

    public function test_custom_model_allows_colon_and_dash(): void
    {
        $model = 'org:my-model-v1';
        $result = Settings::sanitize_custom_model_id($model);
        $this->assertSame($model, $result);
    }

    // ------------------------------------------------------------------
    //  get_default_model_for_provider()
    // ------------------------------------------------------------------

    public function test_default_model_openai_valid(): void
    {
        $default = Settings::get_default_model_for_provider('openai');
        $models = Settings::get_models_for_provider('openai');
        $this->assertArrayHasKey($default, $models);
    }

    public function test_default_model_unknown_provider_returns_string(): void
    {
        $result = Settings::get_default_model_for_provider('unknown_xyz');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
