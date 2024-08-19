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
		$complete = $this->call_private_method( Forvoyez_Image_Renderer::class, 'is_metadata_complete', array( 'Alt Text', 'Title', 'Caption' ) );
		$this->assertTrue( $complete, 'Metadata should be complete when all fields are filled' );

		$incomplete_alt = $this->call_private_method( Forvoyez_Image_Renderer::class, 'is_metadata_complete', array( '', 'Title', 'Caption' ) );
		$this->assertFalse( $incomplete_alt, 'Metadata should be incomplete when alt text is missing' );

		$incomplete_title = $this->call_private_method( Forvoyez_Image_Renderer::class, 'is_metadata_complete', array( 'Alt Text', '', 'Caption' ) );
		$this->assertFalse( $incomplete_title, 'Metadata should be incomplete when title is missing' );

		$incomplete_caption = $this->call_private_method( Forvoyez_Image_Renderer::class, 'is_metadata_complete', array( 'Alt Text', 'Title', '' ) );
		$this->assertFalse( $incomplete_caption, 'Metadata should be incomplete when caption is missing' );

		$all_empty = $this->call_private_method( Forvoyez_Image_Renderer::class, 'is_metadata_complete', array( '', '', '' ) );
		$this->assertFalse( $all_empty, 'Metadata should be incomplete when all fields are empty' );
	}

	/**
	 * Test the render_metadata_icons method for correct icon visibility.
	 */
	public function test_render_metadata_icons() {
		ob_start();
		Forvoyez_Image_Renderer::render_metadata_icons( 'Alt Text', 'Title', 'Caption', true );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'hidden alt-missing', $output, 'Alt icon should be hidden when alt text is present' );
		$this->assertStringContainsString( 'hidden title-missing', $output, 'Title icon should be hidden when title is present' );
		$this->assertStringContainsString( 'hidden caption-missing', $output, 'Caption icon should be hidden when caption is present' );
		$this->assertStringNotContainsString( 'hidden all-complete', $output, 'All complete icon should be visible when all metadata is present' );

		ob_start();
		Forvoyez_Image_Renderer::render_metadata_icons( '', '', '', false );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'hidden alt-missing', $output, 'Alt icon should be visible when alt text is missing' );
		$this->assertStringNotContainsString( 'hidden title-missing', $output, 'Title icon should be visible when title is missing' );
		$this->assertStringNotContainsString( 'hidden caption-missing', $output, 'Caption icon should be visible when caption is missing' );
		$this->assertStringContainsString( 'hidden all-complete', $output, 'All complete icon should be hidden when metadata is incomplete' );
	}

    /**
     * Test the render_action_buttons method for correct button presence.
     */
    public function test_render_action_buttons() {
        ob_start();
        Forvoyez_Image_Renderer::render_action_buttons();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'analyze-button', $output, 'Analyze button should be present' );
        $this->assertStringContainsString( 'see-more-button', $output, 'See more button should be present' );
    }

	/**
	 * Helper method to call private methods for testing.
	 *
	 * @param string $className The name of the class containing the private method.
	 * @param string $methodName The name of the private method.
	 * @param array $args The arguments to pass to the method.
	 * @return mixed The result of the method call.
	 */
	private function call_private_method( $className, $methodName, array $args ) {
		$class  = new ReflectionClass( $className );
		$method = $class->getMethod( $methodName );
		$method->setAccessible( true );

		return $method->invokeArgs( null, $args );
	}
}
