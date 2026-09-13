<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {

	/** @var HTL_Renderer */
	private HTL_Renderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		$this->renderer                    = new HTL_Renderer();
		$GLOBALS['htl_test_options']       = array();
		$GLOBALS['htl_test_post_types']    = array();
		$GLOBALS['htl_test_post_statuses'] = array();
		$GLOBALS['htl_test_conditions']    = array();
		$GLOBALS['htl_test_taxonomies']    = array();
	}

	public function test_archive_resolution_uses_specific_condition_order(): void {
		$GLOBALS['htl_test_options'][ HTL_Settings::OPTION_KEY ] = array(
			'404'  => 40,
			'home' => 10,
		);
		$GLOBALS['htl_test_conditions']                          = array(
			'404'  => true,
			'home' => true,
		);

		self::assertSame( 40, $this->invoke_private( 'resolve_archive_template' ) );
	}

	public function test_rule_resolution_returns_first_matching_rule(): void {
		$GLOBALS['htl_test_options']['htl_singular_rules'] = array(
			array(
				'post_type'   => 'post',
				'category'    => 'news',
				'template_id' => 20,
			),
			array(
				'post_type'   => 'post',
				'category'    => '',
				'template_id' => 21,
			),
		);
		$GLOBALS['htl_test_post_types'][5]                 = 'post';
		$GLOBALS['htl_test_taxonomies']['post:category']   = true;
		$GLOBALS['htl_test_taxonomies']['category:5:news'] = true;

		self::assertSame( 20, $this->invoke_private( 'resolve_rule_template', 5 ) );
	}

	private function invoke_private( string $method, ...$arguments ) {
		$reflection = new ReflectionMethod( HTL_Renderer::class, $method );

		return $reflection->invokeArgs( $this->renderer, $arguments );
	}
}
