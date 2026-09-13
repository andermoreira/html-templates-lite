<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	/** @var HTL_Settings */
	private HTL_Settings $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings                    = new HTL_Settings();
		$GLOBALS['htl_test_options']       = array();
		$GLOBALS['htl_test_post_types']    = array();
		$GLOBALS['htl_test_post_statuses'] = array();
	}

	public function test_sanitize_keeps_only_known_template_ids(): void {
		$GLOBALS['htl_test_post_types'] = array(
			10 => HTL_Post_Type::SLUG,
			11 => 'post',
		);

		self::assertSame(
			array( 'home' => 10 ),
			$this->settings->sanitize(
				array(
					'home'     => '10',
					'category' => 11,
					'unknown'  => 10,
				)
			)
		);
	}

	public function test_sanitize_rules_discards_incomplete_or_invalid_rules(): void {
		$GLOBALS['htl_test_post_types'] = array(
			20 => HTL_Post_Type::SLUG,
			21 => 'post',
		);

		self::assertSame(
			array(
				array(
					'post_type'   => 'post',
					'category'    => 'news',
					'template_id' => 20,
				),
			),
			$this->settings->sanitize_rules(
				array(
					array(
						'post_type'   => 'post',
						'category'    => 'News',
						'template_id' => '20',
					),
					array(
						'post_type'   => 'missing',
						'category'    => '',
						'template_id' => 20,
					),
					array(
						'post_type'   => 'post',
						'category'    => '',
						'template_id' => 21,
					),
				)
			)
		);
	}
}
