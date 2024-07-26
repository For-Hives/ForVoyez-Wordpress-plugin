<?php
/**
 * Class TestForVoyezImageRenderer
 *
 * Unit tests for the Forvoyez_Image_Renderer class logic.
 *
 * @package ForVoyez
 */

class TestForVoyezImageRenderer extends WP_UnitTestCase {

    /**
     * Test the is_metadata_complete method.
     */
    public function test_is_metadata_complete() {
        $result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'is_metadata_complete', ['Alt Text', 'Title', 'Caption']);
        $this->assertTrue($result);

        $result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'is_metadata_complete', ['', 'Title', 'Caption']);
        $this->assertFalse($result);

        $result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'is_metadata_complete', ['Alt Text', '', 'Caption']);
        $this->assertFalse($result);

        $result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'is_metadata_complete', ['Alt Text', 'Title', '']);
        $this->assertFalse($result);

        $result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'is_metadata_complete', ['', '', '']);
        $this->assertFalse($result);
    }

    /**
     * Test the logic for determining which metadata icons should be displayed.
     */
    public function test_metadata_icon_logic() {
        $complete_metadata = ['Alt Text', 'Title', 'Caption'];
        $incomplete_metadata = ['', 'Title', ''];

        $complete_result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'render_metadata_icons', $complete_metadata);
        $incomplete_result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'render_metadata_icons', $incomplete_metadata);

        // For complete metadata, all icons should be hidden except 'all-complete'
        $this->assertStringContainsString('hidden alt-missing', $complete_result);
        $this->assertStringContainsString('hidden title-missing', $complete_result);
        $this->assertStringContainsString('hidden caption-missing', $complete_result);
        $this->assertStringNotContainsString('hidden all-complete', $complete_result);

        // For incomplete metadata, 'alt-missing' and 'caption-missing' should be visible, 'title-missing' should be hidden
        $this->assertStringNotContainsString('hidden alt-missing', $incomplete_result);
        $this->assertStringContainsString('hidden title-missing', $incomplete_result);
        $this->assertStringNotContainsString('hidden caption-missing', $incomplete_result);
        $this->assertStringContainsString('hidden all-complete', $incomplete_result);
    }

    /**
     * Test the logic for generating action buttons.
     */
    public function test_action_button_generation() {
        $result = $this->call_private_method(Forvoyez_Image_Renderer::class, 'render_action_buttons', []);

        // Check that both 'analyze' and 'see-more' buttons are generated
        $this->assertStringContainsString('analyze-button', $result);
        $this->assertStringContainsString('see-more-button', $result);
    }

    /**
     * Helper method to call private methods for testing.
     *
     * @param string $className The name of the class containing the private method.
     * @param string $methodName The name of the private method.
     * @param array $args The arguments to pass to the method.
     * @return mixed The result of the method call.
     */
    private function call_private_method($className, $methodName, array $args) {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }
}