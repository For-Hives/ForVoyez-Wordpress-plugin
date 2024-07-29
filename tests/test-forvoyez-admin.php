<?php

class Test_Forvoyez_Admin extends WP_UnitTestCase {
    private $forvoyez_admin;
    private $api_manager_mock;

    public function setUp(): void {
        parent::setUp();
        $this->api_manager_mock = $this->createMock(Forvoyez_API_Manager::class);
        $this->forvoyez_admin = new Forvoyez_Admin($this->api_manager_mock);
    }

    public function test_parse_and_sanitize_filters() {
        $method = new ReflectionMethod(Forvoyez_Admin::class, 'parse_and_sanitize_filters');
        $method->setAccessible(true);

        $input = [
            ['name' => 'filter[]', 'value' => 'alt'],
            ['name' => 'filter[]', 'value' => 'title'],
            ['name' => 'filter[]', 'value' => 'invalid'],
            ['name' => 'not_filter', 'value' => 'caption'],
        ];

        $expected = ['alt', 'title'];
        $result = $method->invoke($this->forvoyez_admin, $input);

        $this->assertEquals($expected, $result);
    }

    public function test_build_meta_query() {
        $method = new ReflectionMethod(Forvoyez_Admin::class, 'build_meta_query');
        $method->setAccessible(true);

        $filters = ['alt', 'title'];
        $expected = [
            'relation' => 'OR',
            [
                'key' => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'post_title',
                'value' => '',
                'compare' => '=',
            ],
        ];

        $result = $method->invoke($this->forvoyez_admin, $filters);
        $this->assertEquals($expected, $result);
    }

    public function test_get_query_args() {
        $method = new ReflectionMethod(Forvoyez_Admin::class, 'get_query_args');
        $method->setAccessible(true);

        $paged = 2;
        $per_page = 10;
        $filters = ['alt'];

        $result = $method->invoke($this->forvoyez_admin, $paged, $per_page, $filters);

        $this->assertEquals('attachment', $result['post_type']);
        $this->assertEquals('image', $result['post_mime_type']);
        $this->assertEquals('inherit', $result['post_status']);
        $this->assertEquals(10, $result['posts_per_page']);
        $this->assertEquals(2, $result['paged']);
        $this->assertArrayHasKey('meta_query', $result);
    }

    public function test_get_image_counts() {
        // Create some test images
        $this->factory->attachment->create_many(5, ['post_mime_type' => 'image/webp']);

        // Create an image without alt text
        $attachment_id = $this->factory->attachment->create(['post_mime_type' => 'image/webp']);
        delete_post_meta($attachment_id, '_wp_attachment_image_alt');

        $counts = $this->forvoyez_admin->get_image_counts();

        $this->assertArrayHasKey('all', $counts);
        $this->assertArrayHasKey('missing_alt', $counts);
        $this->assertArrayHasKey('missing_all', $counts);
        $this->assertEquals(6, $counts['all']);
        $this->assertGreaterThan(0, $counts['missing_alt']);
    }

    public function test_get_image_ids() {
        // Create test images
        $normal_image = $this->factory->attachment->create(['post_mime_type' => 'image/webp']);
        update_post_meta($normal_image, '_wp_attachment_image_alt', 'Normal Alt Text');

        $missing_alt_image = $this->factory->attachment->create(['post_mime_type' => 'image/webp']);
        delete_post_meta($missing_alt_image, '_wp_attachment_image_alt');

        error_log("Normal image ID: " . $normal_image);
        error_log("Missing alt image ID: " . $missing_alt_image);

        // Test for 'all'
        $all_ids = array_map('intval', $this->forvoyez_admin->get_image_ids('all'));
        error_log("All IDs: " . print_r($all_ids, true));

        $this->assertContains($normal_image, $all_ids, "Normal image should be in 'all' results");
        $this->assertContains($missing_alt_image, $all_ids, "Missing alt image should be in 'all' results");

        // Test for 'missing_alt'
        $missing_alt_ids = array_map('intval', $this->forvoyez_admin->get_image_ids('missing_alt'));
        error_log("Missing alt IDs: " . print_r($missing_alt_ids, true));

        $this->assertNotContains($normal_image, $missing_alt_ids, "Normal image should not be in 'missing_alt' results");
        $this->assertContains($missing_alt_image, $missing_alt_ids, "Missing alt image should be in 'missing_alt' results");

        // Additional checks
        error_log("Normal image alt text: " . get_post_meta($normal_image, '_wp_attachment_image_alt', true));
        error_log("Missing alt image alt text: " . get_post_meta($missing_alt_image, '_wp_attachment_image_alt', true));

        // Check if images are actually created
        $normal_post = get_post($normal_image);
        $missing_alt_post = get_post($missing_alt_image);
        error_log("Normal image post: " . print_r($normal_post, true));
        error_log("Missing alt image post: " . print_r($missing_alt_post, true));
    }
}