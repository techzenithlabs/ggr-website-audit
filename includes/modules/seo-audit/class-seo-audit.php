<?php
/**
 * SEO Audit module — menu, assets, and AJAX handlers.
 *
 * AJAX actions are registered via the static register_ajax() method so they
 * work even on AJAX requests where admin_menu never fires.
 * The instance (new GGRWA_SEO_Audit) is created by GGRWA_Admin::load_modules()
 * for the admin menu — we never create it here to avoid duplicates.
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GGRWA_SEO_Audit {

    /* -----------------------------------------------------------------------
     * AJAX REGISTRATION (static — called early from plugin loader)
     * --------------------------------------------------------------------- */

    /**
     * Register wp_ajax_ hooks.
     * Called statically from GGRWA_Plugin::load_seo_module() so it runs
     * before admin_menu and therefore works for AJAX requests too.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_ggrwa_seo_dashboard_data',  [ __CLASS__, 'ajax_dashboard_data'  ] );
        add_action( 'wp_ajax_ggrwa_run_seo_full_audit',  [ __CLASS__, 'ajax_run_full_audit'  ] );
        add_action( 'wp_ajax_ggrwa_seo_issue_detail',    [ __CLASS__, 'ajax_issue_detail'    ] );
        add_action( 'wp_ajax_ggrwa_analyze_single_post', [ __CLASS__, 'ajax_analyze_single'  ] );
        add_action( 'wp_ajax_ggrwa_search_posts',        [ __CLASS__, 'ajax_search_posts'    ] );
    }

    /* -----------------------------------------------------------------------
     * AJAX HANDLERS 
     * --------------------------------------------------------------------- */

    /** Return cached (or fresh) dashboard data as JSON. */
    public static function ajax_dashboard_data() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        wp_send_json_success( GGRWA_SEO_Data_Aggregator::get( false ) );
    }

    /** Return posts affected by a specific issue key + fix guide. */
    public static function ajax_issue_detail() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $issue_key = sanitize_key( $_POST['issue_key'] ?? '' );
        if ( empty( $issue_key ) ) {
            wp_send_json_error( [ 'message' => 'Missing issue_key' ] );
        }

        $data = GGRWA_SEO_Data_Aggregator::get_issue_detail( $issue_key, 20 );
        wp_send_json_success( $data );
    }

    /** Bust cache, recompute, return fresh data. */
    public static function ajax_run_full_audit() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        @set_time_limit( 120 );
        ignore_user_abort( true );

        global $wpdb;
        if ( ! $wpdb->check_connection( false ) ) {
            $wpdb->db_connect();
        }

        $data = GGRWA_SEO_Data_Aggregator::get( true ); // force_refresh

        update_option( 'ggrwa_last_audit_time', time() );

        // Reconnect after long computation.
        if ( ! $wpdb->check_connection( false ) ) {
            $wpdb->db_connect();
        }

        wp_send_json_success( $data );
    }

    /** Search posts/pages/CPTs — resolves URLs, IDs, slugs, ?p=, ?page_id=, preview links, title search, categories, tags, custom meta. */
    public static function ajax_search_posts() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $q         = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        $post_type = sanitize_key( $_POST['post_type'] ?? '' );
        $types     = $post_type ? [ $post_type ] : array_keys( ggrwa_get_analyzable_post_types() );
        $statuses  = [ 'publish', 'draft', 'pending', 'private', 'future' ];

        $found_ids = [];

        /* ── 1. Numeric ID — e.g. user typed "42" ── */
        if ( ctype_digit( trim( $q ) ) ) {
            $found_ids[] = (int) $q;
        }

        /* ── 2. URL input — any permalink structure or preview link ── */
        if ( empty( $found_ids ) && filter_var( $q, FILTER_VALIDATE_URL ) ) {
            $parsed = wp_parse_url( $q );
            $qs     = [];
            if ( ! empty( $parsed['query'] ) ) {
                parse_str( $parsed['query'], $qs );
            }

            // ?p=123  or  ?page_id=123  or  ?preview=true&p=123
            foreach ( [ 'p', 'page_id', 'post_id', 'preview_id' ] as $param ) {
                if ( ! empty( $qs[ $param ] ) && ctype_digit( (string) $qs[ $param ] ) ) {
                    $found_ids[] = (int) $qs[ $param ];
                    break;
                }
            }

            // ?preview=true — try resolving via url_to_postid on the base URL
            if ( empty( $found_ids ) && ! empty( $qs['preview'] ) ) {
                $base = $parsed['scheme'] . '://' . $parsed['host'] . ( $parsed['path'] ?? '' );
                $id   = url_to_postid( $base );
                if ( $id ) $found_ids[] = $id;
            }

            // Pretty permalink — url_to_postid handles all structures
            if ( empty( $found_ids ) ) {
                $id = url_to_postid( $q );
                if ( $id ) $found_ids[] = $id;
            }

            // Slug from path as fallback
            if ( empty( $found_ids ) && ! empty( $parsed['path'] ) ) {
                $slug = trim( basename( rtrim( $parsed['path'], '/' ) ) );
                if ( $slug ) {
                    $p = get_page_by_path( $slug, OBJECT, $types );
                    if ( $p ) $found_ids[] = $p->ID;
                }
            }
        }

        /* ── 3. Slug input (no slashes, no spaces) ── */
        if ( empty( $found_ids ) && preg_match( '/^[a-z0-9\-_]+$/i', trim( $q ) ) && strpos( $q, ' ' ) === false ) {
            $p = get_page_by_path( sanitize_title( $q ), OBJECT, $types );
            if ( $p ) $found_ids[] = $p->ID;
        }

        /* ── 4. Category / tag name ── */
        if ( empty( $found_ids ) ) {
            global $wpdb;
            $term = get_term_by( 'name', $q, 'category' ) ?: get_term_by( 'name', $q, 'post_tag' );
            if ( $term ) {
                $ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                     WHERE tr.term_taxonomy_id = %d
                       AND p.post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ")
                     LIMIT 20",
                    array_merge( [ $term->term_taxonomy_id ], $types )
                ) );
                $found_ids = array_map( 'intval', $ids );
            }
        }

        /* ── 5. Custom meta value search ── */
        if ( empty( $found_ids ) ) {
            global $wpdb;
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_value LIKE %s
                   AND p.post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ")
                   AND p.post_status IN ('publish','draft','pending','private','future')
                 LIMIT 10",
                array_merge( [ '%' . $wpdb->esc_like( $q ) . '%' ], $types )
            ) );
            $found_ids = array_map( 'intval', $ids );
        }

        /* ── 6. Title / keyword text search (always runs as fallback) ── */
        $text_posts = get_posts( [
            's'              => $q,
            'post_type'      => $types,
            'post_status'    => $statuses,
            'posts_per_page' => 20,
            'orderby'        => 'relevance',
            'fields'         => 'ids',
        ] );
        $found_ids = array_unique( array_merge( $found_ids, array_map( 'intval', $text_posts ) ) );

        if ( empty( $found_ids ) ) {
            wp_send_json_success( [] );
        }

        /* ── Build results ── */
        $results = [];
        foreach ( array_slice( $found_ids, 0, 20 ) as $id ) {
            $p = get_post( $id );
            if ( ! $p ) continue;

            // Type mismatch check — if user filtered by a specific type
            $type_mismatch = $post_type && $p->post_type !== $post_type;

            $results[] = [
                'id'            => $p->ID,
                'title'         => get_the_title( $p ) ?: '(no title)',
                'type'          => $p->post_type,
                'type_label'    => get_post_type_object( $p->post_type )->labels->singular_name ?? $p->post_type,
                'status'        => $p->post_status,
                'url'           => get_permalink( $p ),
                'edit_url'      => get_edit_post_link( $p->ID, 'raw' ),
                'visibility'    => self::get_visibility( $p ),
                'type_mismatch' => $type_mismatch,
                'mismatch_msg'  => $type_mismatch
                    ? "This is a {$p->post_type}, not a {$post_type}. Showing it anyway."
                    : '',
            ];
        }

        wp_send_json_success( $results );
    }

    /** Resolve human-readable visibility for a post. */
    private static function get_visibility( WP_Post $p ): string {
        if ( $p->post_status === 'private' )           return 'private';
        if ( ! empty( $p->post_password ) )            return 'password';
        return 'public';
    }

    /** Deep per-page SEO analysis — works on ANY post status. */
    public static function ajax_analyze_single() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( [ 'message' => 'Invalid post ID' ] );

        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( [ 'message' => 'Post not found' ] );

        wp_send_json_success( self::run_single_analysis( $post ) );
    }

    /** Build status context — unique to GGR, not in Rank Math or Yoast. */
    private static function get_status_context( WP_Post $post ): array {
        $status     = $post->post_status;
        $visibility = self::get_visibility( $post );
        $permalink  = get_permalink( $post );
        $edit_url   = get_edit_post_link( $post->ID, 'raw' );
        $type_label = get_post_type_object( $post->post_type )->labels->singular_name ?? 'Post';

        // Detect permalink structure.
        $permalink_structure = get_option( 'permalink_structure', '' );
        $permalink_type = 'plain';
        if ( strpos( $permalink_structure, '%year%/%monthnum%/%day%' ) !== false ) $permalink_type = 'day-name';
        elseif ( strpos( $permalink_structure, '%year%/%monthnum%' ) !== false )   $permalink_type = 'month-name';
        elseif ( strpos( $permalink_structure, 'archives' ) !== false )            $permalink_type = 'numeric';
        elseif ( strpos( $permalink_structure, '%postname%' ) !== false )          $permalink_type = 'post-name';
        elseif ( ! empty( $permalink_structure ) )                                 $permalink_type = 'custom';

        $permalink_labels = [
            'plain'      => 'Plain (?p=123) — not SEO friendly',
            'day-name'   => 'Day & Name — dates hurt evergreen content',
            'month-name' => 'Month & Name — dates can reduce CTR over time',
            'numeric'    => 'Numeric — no keywords in URL',
            'post-name'  => 'Post Name — best for SEO ✓',
            'custom'     => 'Custom structure',
        ];
        $permalink_advice = [
            'plain'      => 'Switch to Post Name permalink structure in Settings → Permalinks for better SEO.',
            'day-name'   => 'Consider switching to Post Name — date-based URLs hurt evergreen content rankings.',
            'month-name' => 'Consider switching to Post Name — month URLs can reduce CTR on older content.',
            'numeric'    => 'Switch to Post Name permalink structure — numeric URLs have no keyword value.',
            'post-name'  => 'Great choice! Post Name is the most SEO-friendly permalink structure.',
            'custom'     => 'Make sure your custom permalink includes %postname% for best SEO results.',
        ];

        // Status-specific advisory messages.
        $advisories = [];
        $can_index  = false;
        $banner_type = 'info';

        switch ( $status ) {
            case 'publish':
                $can_index = true;
                if ( $visibility === 'password' ) {
                    $banner_type   = 'warning';
                    $advisories[]  = "This {$type_label} is published but password-protected.";
                    $advisories[]  = 'Google cannot crawl password-protected content — it will NOT appear in search results.';
                    $advisories[]  = 'Action: Remove the password if you want this page indexed, or keep it protected for private use.';
                    $can_index     = false;
                } elseif ( $visibility === 'private' ) {
                    $banner_type   = 'warning';
                    $advisories[]  = "This {$type_label} is set to Private.";
                    $advisories[]  = 'Only logged-in admins/editors can see it. Google cannot index private posts.';
                    $advisories[]  = 'Action: Change visibility to Public in the editor if you want it to rank on Google.';
                    $can_index     = false;
                } else {
                    $banner_type  = 'success';
                    $advisories[] = "This {$type_label} is live and publicly accessible.";
                    $advisories[] = 'Google can crawl and index this page. Your SEO score below applies fully.';
                }
                break;

            case 'draft':
                $banner_type   = 'draft';
                $advisories[]  = "This {$type_label} is saved as a Draft.";
                $advisories[]  = 'Drafts are not visible to the public or Google — they cannot rank in search results.';
                $advisories[]  = 'Action: Click Publish in the editor when your content is ready to go live.';
                $advisories[]  = 'Tip: Use this GGR analysis now to fix all SEO issues before publishing — get it right from day one.';
                break;

            case 'pending':
                $banner_type   = 'pending';
                $advisories[]  = "This {$type_label} is Pending Review.";
                $advisories[]  = 'It is waiting for an editor or admin to approve and publish it.';
                $advisories[]  = 'Google cannot see pending posts — they are not live yet.';
                $advisories[]  = 'Action: Ask your editor to review and publish, or publish it yourself if you have permission.';
                break;

            case 'future':
                $scheduled_date = get_the_date( 'D, M j Y \a\t g:i a', $post );
                $banner_type    = 'scheduled';
                $advisories[]   = "This {$type_label} is Scheduled to publish on {$scheduled_date}.";
                $advisories[]   = 'It is not live yet — Google cannot crawl it until the publish date.';
                $advisories[]   = 'Tip: Optimise your SEO now so it is perfect the moment it goes live.';
                break;

            case 'private':
                $banner_type   = 'warning';
                $advisories[]  = "This {$type_label} is set to Private.";
                $advisories[]  = 'Only logged-in admins and editors can view it. Google will not index it.';
                $advisories[]  = 'Action: Change the visibility to Public in the editor to make it rankable.';
                break;

            default:
                $banner_type   = 'info';
                $advisories[]  = "This {$type_label} has status: {$status}.";
                $advisories[]  = 'It may not be publicly accessible. Check the post status in the editor.';
        }

        return [
            'status'           => $status,
            'visibility'       => $visibility,
            'can_index'        => $can_index,
            'banner_type'      => $banner_type,
            'advisories'       => $advisories,
            'permalink'        => $permalink,
            'permalink_type'   => $permalink_type,
            'permalink_label'  => $permalink_labels[ $permalink_type ] ?? '',
            'permalink_advice' => $permalink_advice[ $permalink_type ] ?? '',
            'edit_url'         => $edit_url,
        ];
    }

    /** Core per-page analysis engine. */
    private static function run_single_analysis( WP_Post $post ) {
        $content    = wp_strip_all_tags( $post->post_content );
        $title      = get_the_title( $post );
        $url        = get_permalink( $post );
        $word_count = str_word_count( $content );
        $slug       = $post->post_name;

        // Focus keyword — try Rank Math, Yoast, then our own meta.
        $focus_kw = get_post_meta( $post->ID, 'rank_math_focus_keyword', true )
                 ?: get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true )
                 ?: get_post_meta( $post->ID, '_ggr_focus_keyword', true )
                 ?: '';
        $focus_kw = strtolower( trim( $focus_kw ) );

        // Meta description.
        $meta_desc = get_post_meta( $post->ID, 'rank_math_description', true )
                  ?: get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true )
                  ?: get_post_meta( $post->ID, '_ggr_meta_description', true )
                  ?: '';

        // OG image.
        $og_img = get_post_meta( $post->ID, 'rank_math_og_image', true )
               ?: get_post_meta( $post->ID, '_yoast_wpseo_opengraph-image', true )
               ?: ( has_post_thumbnail( $post->ID ) ? get_the_post_thumbnail_url( $post->ID, 'full' ) : '' );

        // Schema.
        $schema_raw = get_post_meta( $post->ID, 'rank_math_schema_Article', true )
                   ?: get_post_meta( $post->ID, '_yoast_wpseo_schema_article_type', true )
                   ?: '';

        // Parse headings from raw content.
        preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', $post->post_content, $hm );
        $h1_count = 0; $h2_count = 0;
        foreach ( $hm[1] as $level ) {
            if ( $level == 1 ) $h1_count++;
            if ( $level == 2 ) $h2_count++;
        }

        // Images without alt.
        preg_match_all( '/<img[^>]+>/i', $post->post_content, $imgs );
        $img_total    = count( $imgs[0] );
        $img_no_alt   = 0;
        foreach ( $imgs[0] as $img ) {
            if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img ) ) $img_no_alt++;
        }

        // Internal / external links.
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\']/i', $post->post_content, $lm );
        $home        = home_url();
        $int_links   = 0; $ext_links = 0;
        foreach ( $lm[1] as $href ) {
            if ( strpos( $href, $home ) === 0 || strpos( $href, '/' ) === 0 ) $int_links++;
            elseif ( preg_match( '/^https?:\/\//i', $href ) ) $ext_links++;
        }

        // Readability (Flesch approximation).
        $sentences  = max( 1, preg_match_all( '/[.!?]+/', $content, $sm ) );
        $words      = max( 1, $word_count );
        $syllables  = max( 1, (int) ( $words * 1.5 ) ); // rough estimate
        $flesch     = round( 206.835 - 1.015 * ( $words / $sentences ) - 84.6 * ( $syllables / $words ) );
        $flesch     = max( 0, min( 100, $flesch ) );

        // Keyword density.
        $kw_density = 0;
        $kw_in_title = false; $kw_in_meta = false; $kw_in_slug = false; $kw_in_content = false;
        if ( $focus_kw ) {
            $content_lower   = strtolower( $content );
            $kw_count        = substr_count( $content_lower, $focus_kw );
            $kw_density      = $words > 0 ? round( ( $kw_count / $words ) * 100, 1 ) : 0;
            $kw_in_title     = strpos( strtolower( $title ), $focus_kw ) !== false;
            $kw_in_meta      = strpos( strtolower( $meta_desc ), $focus_kw ) !== false;
            $kw_in_slug      = strpos( strtolower( $slug ), str_replace( ' ', '-', $focus_kw ) ) !== false;
            $kw_in_content   = $kw_count > 0;
        }

        // Build checks.
        $checks = [];

        // Title checks.
        $title_len = mb_strlen( $title );
        $checks[] = self::check( 'title_length', 'Title Length', $title_len >= 50 && $title_len <= 60 ? 'good' : ( $title_len >= 40 ? 'warning' : 'critical' ), $title_len . ' chars', $title_len >= 50 && $title_len <= 60 ? 'Ideal length (50–60 chars).' : ( $title_len < 50 ? 'Too short — aim for 50–60 chars.' : 'Too long — may be truncated in SERPs.' ), 'title' );
        if ( $focus_kw ) $checks[] = self::check( 'kw_in_title', 'Keyword in Title', $kw_in_title ? 'good' : 'critical', $kw_in_title ? 'Found' : 'Missing', $kw_in_title ? 'Focus keyword appears in the title.' : 'Add your focus keyword to the title.', 'title' );

        // Meta description.
        $meta_len = mb_strlen( $meta_desc );
        $checks[] = self::check( 'meta_desc', 'Meta Description', $meta_len >= 120 && $meta_len <= 160 ? 'good' : ( $meta_len > 0 ? 'warning' : 'critical' ), $meta_len > 0 ? $meta_len . ' chars' : 'Missing', $meta_len >= 120 && $meta_len <= 160 ? 'Ideal length.' : ( $meta_len === 0 ? 'No meta description set.' : ( $meta_len < 120 ? 'Too short — aim for 120–160 chars.' : 'Too long — will be truncated.' ) ), 'meta' );
        if ( $focus_kw ) $checks[] = self::check( 'kw_in_meta', 'Keyword in Meta', $kw_in_meta ? 'good' : 'warning', $kw_in_meta ? 'Found' : 'Missing', $kw_in_meta ? 'Focus keyword in meta description.' : 'Add focus keyword to meta description.', 'meta' );

        // Content.
        $checks[] = self::check( 'word_count', 'Word Count', $word_count >= 600 ? 'good' : ( $word_count >= 300 ? 'warning' : 'critical' ), $word_count . ' words', $word_count >= 600 ? 'Good content length.' : ( $word_count >= 300 ? 'Consider expanding to 600+ words.' : 'Too thin — aim for at least 300 words.' ), 'content' );
        if ( $focus_kw ) {
            $checks[] = self::check( 'kw_density', 'Keyword Density', $kw_density >= 0.5 && $kw_density <= 2.5 ? 'good' : ( $kw_density > 0 ? 'warning' : 'critical' ), $kw_density . '%', $kw_density >= 0.5 && $kw_density <= 2.5 ? 'Ideal density (0.5–2.5%).' : ( $kw_density === 0 ? 'Keyword not found in content.' : ( $kw_density > 2.5 ? 'Possible keyword stuffing.' : 'Density too low — use keyword more naturally.' ) ), 'content' );
            $checks[] = self::check( 'kw_in_content', 'Keyword in Content', $kw_in_content ? 'good' : 'critical', $kw_in_content ? 'Found' : 'Missing', $kw_in_content ? 'Keyword present in content.' : 'Add your focus keyword to the content body.', 'content' );
        }
        $checks[] = self::check( 'readability', 'Readability Score', $flesch >= 60 ? 'good' : ( $flesch >= 40 ? 'warning' : 'critical' ), $flesch . '/100', $flesch >= 60 ? 'Easy to read.' : ( $flesch >= 40 ? 'Moderate — simplify sentences.' : 'Hard to read — use shorter sentences.' ), 'content' );

        // Headings.
        $checks[] = self::check( 'h1_count', 'H1 Tag', $h1_count === 1 ? 'good' : ( $h1_count === 0 ? 'critical' : 'warning' ), $h1_count . ' found', $h1_count === 1 ? 'Exactly one H1 — perfect.' : ( $h1_count === 0 ? 'No H1 tag found — add one.' : 'Multiple H1 tags — use only one.' ), 'headings' );
        $checks[] = self::check( 'h2_count', 'H2 Subheadings', $h2_count >= 2 ? 'good' : ( $h2_count === 1 ? 'warning' : 'critical' ), $h2_count . ' found', $h2_count >= 2 ? 'Good use of subheadings.' : ( $h2_count === 1 ? 'Add more H2 subheadings for structure.' : 'No H2 tags — structure your content.' ), 'headings' );
        if ( $focus_kw ) $checks[] = self::check( 'kw_in_slug', 'Keyword in URL', $kw_in_slug ? 'good' : 'warning', $kw_in_slug ? 'Found' : 'Missing', $kw_in_slug ? 'Keyword in URL slug.' : 'Consider adding keyword to the URL slug.', 'url' );

        // Images.
        $checks[] = self::check( 'img_alt', 'Image Alt Text', $img_no_alt === 0 ? 'good' : ( $img_no_alt <= 2 ? 'warning' : 'critical' ), $img_no_alt . ' missing', $img_no_alt === 0 ? 'All images have alt text.' : $img_no_alt . ' image(s) missing alt text.', 'images' );

        // Links.
        $checks[] = self::check( 'internal_links', 'Internal Links', $int_links >= 2 ? 'good' : ( $int_links === 1 ? 'warning' : 'critical' ), $int_links . ' found', $int_links >= 2 ? 'Good internal linking.' : ( $int_links === 1 ? 'Add more internal links.' : 'No internal links — add at least 2.' ), 'links' );
        $checks[] = self::check( 'external_links', 'External Links', $ext_links >= 1 ? 'good' : 'warning', $ext_links . ' found', $ext_links >= 1 ? 'External links present.' : 'Add at least one authoritative external link.', 'links' );

        // OG / Social.
        $checks[] = self::check( 'og_image', 'OG Image', $og_img ? 'good' : 'warning', $og_img ? 'Set' : 'Missing', $og_img ? 'Open Graph image is set.' : 'Set an OG image for better social sharing.', 'social' );
        $checks[] = self::check( 'schema', 'Schema Markup', $schema_raw ? 'good' : 'warning', $schema_raw ? 'Detected' : 'None', $schema_raw ? 'Schema markup detected.' : 'Add schema markup for rich results.', 'social' );

        // Score.
        $good     = count( array_filter( $checks, fn($c) => $c['status'] === 'good' ) );
        $total    = count( $checks );
        $score    = $total > 0 ? (int) round( ( $good / $total ) * 100 ) : 0;
        $grade    = $score >= 80 ? 'A' : ( $score >= 60 ? 'B' : ( $score >= 40 ? 'C' : 'D' ) );

        return [
            'post_id'        => $post->ID,
            'title'          => $title,
            'url'            => $url,
            'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
            'post_type'      => $post->post_type,
            'focus_kw'       => $focus_kw,
            'word_count'     => $word_count,
            'score'          => $score,
            'grade'          => $grade,
            'checks'         => $checks,
            'status_context' => self::get_status_context( $post ),
        ];
    }

    private static function check( $key, $label, $status, $value, $desc, $group ) {
        return compact( 'key', 'label', 'status', 'value', 'desc', 'group' );
    }

    /* -----------------------------------------------------------------------
     * INSTANCE — admin menu + asset enqueue
     * Instantiated once by GGRWA_Admin::load_modules().
     * --------------------------------------------------------------------- */

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu'   ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'  ] );
    }

    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Page Analyzer',
            'Page Analyzer',
            'manage_options',
            'ggrwa-seo-audit',
            [ $this, 'render' ]
        );
    }

    public function render() {
        // Ensure PageSpeed class is available for the view.
        $psi = GGRWA_PLUGIN_PATH . 'includes/engine/class-ggrwa-pagespeed.php';
        if ( file_exists( $psi ) && ! class_exists( 'GGRWA_PageSpeed' ) ) {
            require_once $psi;
        }
        require GGRWA_PLUGIN_PATH . 'includes/modules/seo-audit/view-page-analyzer.php';
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'ggr-website-audit_page_ggrwa-seo-audit' ) return;

        wp_enqueue_style(
            'ggr-page-analyzer-css',
            GGRWA_PLUGIN_URL . 'includes/modules/seo-audit/assets/page-analyzer.css',
            [],
            GGRWA_VERSION
        );

        wp_enqueue_script(
            'ggr-page-analyzer-js',
            GGRWA_PLUGIN_URL . 'includes/modules/seo-audit/assets/page-analyzer.js',
            [ 'jquery' ],
            GGRWA_VERSION,
            true
        );

        // Pass AJAX URL + nonce to JS.
        wp_localize_script( 'ggr-page-analyzer-js', 'ggrwa_page_analyzer', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'ggrwa_seo_dashboard' ),
            'site_name'  => get_bloginfo( 'name' ),
            'home_url'   => home_url( '/' ),
            'post_types' => ggrwa_get_analyzable_post_types(),
        ] );
    }
}
