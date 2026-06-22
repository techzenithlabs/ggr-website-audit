<?php
/**
 * SEO Intelligence Meta Box.
 *
 * Displays the GGR SEO Intelligence panel
 * below the post title.
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGRWA_SEO_Intelligence_Metabox {

	/**
	 * Constructor.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {

		add_action(
			'edit_form_after_title',
			array( $this, 'render_after_title' )
		);

		add_action(
			'save_post',
			array( $this, 'save_meta_box' )
		);
	}

	/**
	 * Render SEO Intelligence panel below title.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public function render_after_title( $post ) {

		// Restrict to posts and pages.
		if (
			! in_array(
				$post->post_type,
				array( 'post', 'page' ),
				true
			)
		) {
			return;
		}

		$this->render_meta_box( $post );
	}

	/**
	 * Render SEO Intelligence UI.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public function render_meta_box( $post ) {

		wp_nonce_field(
			'ggrwa_seo_intelligence_nonce',
			'ggrwa_seo_intelligence_nonce'
		);

        $analyzer = new GGRWA_SEO_Intelligence_Analyzer();

        $analysis = $analyzer->analyze(
            $post->ID
        );

        $keyword = $analysis['keyword'];

		include GGRWA_PLUGIN_PATH .
			'includes/modules/seo-intelligence/view-metabox.php';
	}

	/**
	 * Save SEO Intelligence data.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function save_meta_box( $post_id ) {

		// Nonce check.
		if (
			! isset( $_POST['ggrwa_seo_intelligence_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['ggrwa_seo_intelligence_nonce']
					)
				),
				'ggrwa_seo_intelligence_nonce'
			)
		) {
			return;
		}

		// Autosave check.
		if (
			defined( 'DOING_AUTOSAVE' ) &&
			DOING_AUTOSAVE
		) {
			return;
		}

		// Permission check.
		if (
			! current_user_can(
				'edit_post',
				$post_id
			)
		) {
			return;
		}

		$keyword = '';

		if ( isset( $_POST['_ggrwa_focus_keyword'] ) ) {

			$keyword = sanitize_text_field(
				wp_unslash(
					$_POST['_ggrwa_focus_keyword']
				)
			);
		}

		update_post_meta(
			$post_id,
			'_ggrwa_focus_keyword',
			$keyword
		);


		$meta_title = '';

		if (isset($_POST['_ggrwa_meta_title'])) {

			$meta_title = sanitize_text_field(
				wp_unslash(
					$_POST['_ggrwa_meta_title']
				)
			);
		}

		update_post_meta(
			$post_id,
			'_ggrwa_meta_title',
			$meta_title
		);

		$meta_description = '';

		if (isset($_POST['_ggrwa_meta_description'])) {

			$meta_description = sanitize_textarea_field(
				wp_unslash(
					$_POST['_ggrwa_meta_description']
				)
			);
		}

		update_post_meta(
			$post_id,
			'_ggrwa_meta_description',
			$meta_description
		);

        /**
         * Clear dashboard cache when SEO keyword changes.
         *
         * Dashboard widgets use transient-based aggregation.
         * When a focus keyword is added/updated we must invalidate
         * cached SEO statistics so the dashboard immediately reflects
         * the latest values.
         *
         * @since 2.5.0
         */
        if (
            class_exists(
                'GGRWA_SEO_Data_Aggregator'
            )
        ) {
            GGRWA_SEO_Data_Aggregator::bust_cache();
        }
    }
}