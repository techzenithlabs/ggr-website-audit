<?php
/**
 * SEO Intelligence Analyzer.
 *
 * @package GGR_Website_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGRWA_SEO_Intelligence_Analyzer {

	/**
	 * Analyze post SEO.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	public function analyze( $post_id ) {

		$keyword = get_post_meta(
			$post_id,
			'_ggrwa_focus_keyword',
			true
		);

		$title = get_the_title( $post_id );

		$slug = get_post_field(
			'post_name',
			$post_id
		);

		$content = get_post_field(
			'post_content',
			$post_id
		);

		$checks = array();

		$score = 0;

		/*
		 * Focus Keyword
		 */
		$checks['keyword'] = ! empty( $keyword );

		if ( $checks['keyword'] ) {
			$score += 20;
		}

		/*
		 * Title Check
		 */
		$checks['title'] =
			! empty( $keyword ) &&
			false !== stripos(
				$title,
				$keyword
			);

		if ( $checks['title'] ) {
			$score += 20;
		}

		/*
		 * URL Check
		 */
		$checks['url'] =
			! empty( $keyword ) &&
			false !== stripos(
				$slug,
				sanitize_title( $keyword )
			);

		if ( $checks['url'] ) {
			$score += 20;
		}

		/*
		 * Content Check
		 */
		$checks['content'] =
			! empty( $keyword ) &&
			false !== stripos(
				wp_strip_all_tags( $content ),
				$keyword
			);

		if ( $checks['content'] ) {
			$score += 20;
		}

		/*
		 * Meta Check
		 * Placeholder for now.
		 */
		$checks['meta'] = false;

		return array(
			'score'   => $score,
			'keyword' => $keyword,
			'checks'  => $checks,
		);
	}

    
}