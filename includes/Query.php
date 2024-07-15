<?php
/**
 * @package The_SEO_Framework\Classes\Helper\Query
 * @subpackage The_SEO_Framework\Query
 */

namespace BCFE;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
use BCFE\Cache;
use BCFE\Helper;

/*use \The_SEO_Framework\{
    Admin,
    Data,
};*/

/**
 * The SEO Framework plugin
 * Copyright (C) 2023 - 2024 Sybre Waaijer, CyberWire B.V. (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Holds a collection of helper methods for the WordPress query.
 * Interprets the WordPress query to eliminate known pitfalls.
 *
 * @since 5.0.0
 * @access protected
 *         Use tsf()->query() instead.
 */
class Query {

    /**
     * Returns the post type name from query input or real ID.
     *
     * @since 4.0.5
     * @since 4.2.0 Now supports common archives without relying on the first post.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @param int|WP_Post|null $post (Optional) Post ID or post object.
     * @return string|false Post type on success, false on failure.
     */
    public static function get_post_type_real_id( $post = null ) {

        if ( isset( $post ) )
            return \get_post_type( $post );

        if ( static::is_archive() ) {
            if ( static::is_category() || static::is_tag() || static::is_tax() ) {
                $post_type = Helper::get_post_types();
                $post_type = \is_array( $post_type ) ? reset( $post_type ) : $post_type;
            } elseif ( \is_post_type_archive() ) {
                $post_type = \get_query_var( 'post_type' );
                $post_type = \is_array( $post_type ) ? reset( $post_type ) : $post_type;
            } else {
                // Let WP guess for us. This works reliably (enough) on non-404 queries.
                $post_type = \get_post_type();
            }
        } else {
            $post_type = \get_post_type( static::get_the_real_id() );
        }

        return $post_type;
    }

    /**
     * Returns the post type name from current screen.
     *
     * @since 3.1.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Screen $current_screen
     *
     * @return string
     */
    public static function get_admin_post_type() {
        return $GLOBALS['current_screen']->post_type ?? '';
    }

    /**
     * Get the real page ID, also from CPT, archives, author, blog, etc.
     * Memoizes the return value.
     *
     * @since 2.5.0
     * @since 3.1.0 No longer checks if we can cache the query when $use_cache is false.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @param bool $use_cache Whether to use the cache or not.
     * @return int|false The ID.
     */
    public static function get_the_real_id( $use_cache = true ) {

        if ( \is_admin() )
            return static::get_the_real_admin_id();

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition -- I know.
        if ( $use_cache && ( null !== $memo = Helper::umemo( __METHOD__ ) ) ) return $memo;

        // Try to get ID from plugins or feed when caching is available.
        if ( $use_cache ) {
            /**
             * @since 2.5.0
             * @param int $id
             */
            $id = \apply_filters(
                'breadcrumbs_for_elementor_real_id',
                \is_feed() ? \get_the_id() : 0,
            );
        }

        /**
         * @since 2.6.2
         * @param int  $id        Can be either the Post ID, or the Term ID.
         * @param bool $use_cache Whether this value is stored in runtime caching.
         */
        $id = (int) \apply_filters(
            'breadcrumbs_for_elementor_current_object_id',
            ( $id ?? 0 ) ?: \get_queried_object_id(), // This catches most IDs. Even Post IDs.
            $use_cache,
        );

        // Do not overwrite cache when not requested. Otherwise, we'd have two "initial" states, causing incongruities.
        return $use_cache ? Helper::umemo( __METHOD__, $id ) : $id;
    }

    /**
     * Fetches post or term ID within the admin.
     *
     * @since 2.7.0
     * @since 2.8.0 Removed WP 3.9 compat
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return int The admin ID.
     */
    public static function get_the_real_admin_id() {
        /**
         * @since 2.9.0
         * @param int $id
         */
        return (int) \apply_filters(
            'breadcrumbs_for_elementor_current_admin_id',
            // Get in the loop first, fall back to globals or get parameters.
            \get_the_id()
                ?: static::get_admin_post_id()
                ?: static::get_admin_term_id()
        );
    }

    /**
     * Returns the front page ID, if home is a page.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return int the ID.
     */
    public static function get_the_front_page_id() {
        return Helper::umemo( __METHOD__ )
            ?? Helper::umemo(
                __METHOD__,
                Helper::has_page_on_front() ? (int) \get_option( 'page_on_front' ) : 0,
            );
    }

    /**
     * Fetches the Post ID on admin pages.
     *
     * @since 5.0.0
     *
     * @return int Post ID.
     */
    public static function get_admin_post_id() {
        return static::is_post_edit()
            // phpcs:ignore, WordPress.Security.NonceVerification -- current_screen validated the 'post' object.
            ? \absint( $_GET['post'] ?? $_GET['post_id'] ?? 0 )
            : 0;
    }

    /**
     * Fetches the Term ID on admin pages.
     *
     * @since 2.6.0
     * @since 2.6.6 Moved from class The_SEO_Framework_Term_Data.
     * @since 3.1.0 1. Removed WP 4.5 compat. Now uses global $tag_ID.
     *              2. Removed caching
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global int $tag_ID
     *
     * @return int Term ID.
     */
    public static function get_admin_term_id() {
        return static::is_archive_admin()
            ? \absint( $GLOBALS['tag_ID'] ?? 0 )
            : 0;
    }

    /**
     * Returns the current taxonomy, if any.
     * Memoizes the return value.
     *
     * @since 3.0.0
     * @since 3.1.0 1. Now works in the admin.
     *              2. Added memoization.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Screen $current_screen
     *
     * @return string The queried taxonomy type.
     */
    public static function get_current_taxonomy() {
        return Cache::memo()
            ?? Cache::memo(
                ( \is_admin() ? $GLOBALS['current_screen'] : \get_queried_object() )
                    ->taxonomy ?? '',
            );
    }

    /**
     * Returns the current post type, if any.
     * Memoizes the return value.
     *
     * @since 4.1.4
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Now falls back to the current post type instead erroneously to a boolean.
     *              3. Now memoizes the return value.
     *
     * @return string The queried post type.
     */
    public static function get_current_post_type() {
        return Cache::memo()
            ?? Cache::memo(
                \is_admin()
                    ? static::get_admin_post_type()
                    : static::get_post_type_real_id()
            );
    }

    /**
     * Detects attachment page.
     *
     * @since 2.6.0
     * @since 4.0.0 Now reliably works on admin screens.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @param mixed $attachment Attachment ID, title, slug, or array of such.
     * @return bool
     */
    public static function is_attachment( $attachment = '' ) {

        if ( \is_admin() )
            return static::is_attachment_admin();

        if ( ! $attachment )
            return \is_attachment();

        return Cache::memo( null, $attachment )
            ?? Cache::memo( \is_attachment( $attachment ), $attachment );
    }

    /**
     * Detects attachments within the admin area.
     *
     * @since 4.0.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @see static::is_attachment()
     *
     * @return bool
     */
    public static function is_attachment_admin() {
        return static::is_singular_admin() && 'attachment' === static::is_singular_admin();
    }

    /**
     * Determines whether the content type is both singular and archival.
     * Simply put, it detects a blog page and WooCommerce shop page.
     *
     * @since 3.1.0
     * @since 4.0.5 1. The output is now filterable.
     *              2. Added caching.
     *              3. Now has a first parameter `$post`.
     * @since 4.0.6 Added a short-circuit on current-requests for `is_singular()`.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @param int|\WP_Post|null $post (Optional) Post ID or post object.
     * @return bool
     */
    public static function is_singular_archive( $post = null ) {

        if ( isset( $post ) ) {
            // Keep this an integer, even if 0. Only "null" may tell it's in the loop.
            $id = \is_int( $post )
                ? $post
                : ( \get_post( $post )->ID ?? 0 );
        } else {
            $id = null;
        }

        return Cache::memo( null, $id )
            ?? Cache::memo(
            /**
             * @since 4.0.5
             * @since 4.0.7 The $id can now be null, when no post is given.
             * @param bool     $is_singular_archive Whether the post ID is a singular archive.
             * @param int|null $id                  The supplied post ID. Null when in the loop.
             */
                (bool) \apply_filters(
                    'breadcrumbs_for_elementor_is_singular_archive',
                    static::is_blog_as_page( $id ),
                    $id,
                ),
                $id,
            );
    }

    /**
     * Detects archive pages. Also in admin.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Query $wp_query
     *
     * @return bool
     */
    public static function is_archive() {

        if ( \is_admin() )
            return static::is_archive_admin();

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition -- I know.
        if ( null !== $memo = Cache::memo() ) return $memo;

        if ( \is_archive() && false === static::is_singular() )
            return Cache::memo( true );

        if ( isset( $GLOBALS['wp_query']->query ) && false === static::is_singular() ) {
            global $wp_query;

            if (
                $wp_query->is_tax
                || $wp_query->is_category
                || $wp_query->is_tag
                || $wp_query->is_post_type_archive
                || $wp_query->is_author
                || $wp_query->is_date
            )
                return Cache::memo( true );
        }

        return Cache::memo( false );
    }

    /**
     * Extends default WordPress is_archive() and determines screen in admin.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Screen $current_screen
     *
     * @return bool Post Type is archive
     */
    public static function is_archive_admin() {

        switch ( $GLOBALS['current_screen']->base ?? '' ) {
            case 'edit-tags':
            case 'term':
                return true;
        }

        return false;
    }


    /**
     * Detects Post edit screen in WP Admin.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Screen $current_screen
     *
     * @return bool We're on Post Edit screen.
     */
    public static function is_post_edit() {
        return 'post' === ( $GLOBALS['current_screen']->base ?? '' );
    }

    /**
     * Detects author archives.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @uses static::is_archive()
     *
     * @param mixed $author Optional. User ID, nickname, nicename, or array of User IDs, nicknames, and nicenames
     * @return bool
     */
    public static function is_author( $author = '' ) {

        if ( ! $author )
            return \is_author();

        return Cache::memo( null, $author )
            ?? Cache::memo( \is_author( $author ), $author );
    }

    /**
     * Detects the blog page.
     *
     * @since 2.6.0
     * @since 4.2.0 Added the first parameter to allow custom query testing.
     * @since 5.0.0 1. Renamed from `is_home()`.
     *              2. Moved from `\The_SEO_Framework\Load`.
     * @since 5.0.3 1. Will no longer validate `0` as a plausible blog page.
     *              2. Will no longer validate `is_home()` when the blog page is not assigned.
     *
     * @param int|\WP_Post|null $post Optional. Post ID or post object.
     *                               Do not supply from WP_Query's main loop-query.
     * @return bool
     */
    public static function is_blog( $post = null ) {

        if ( isset( $post ) ) {
            $id = \is_int( $post )
                ? ( $post ?: null )
                : ( \get_post( $post )->ID ?? null );

            return ( (int) \get_option( 'page_for_posts' ) ) === $id;
        }

        // If not blog page is assigned, it won't exist. Ignore whatever WP thinks.
        return Helper::has_blog_page() && \is_home();
    }

    /**
     * Detects the non-front blog page.
     *
     * @since 4.2.0
     * @since 5.0.0 1. Renamed from `is_home_as_page()`.
     *              2. Moved from `\The_SEO_Framework\Load`.
     *
     * @param int|\WP_Post|null $post Optional. Post ID or post object.
     *                               Do not supply from WP_Query's main loop-query.
     * @return bool
     */
    public static function is_blog_as_page( $post = null ) {
        // If front is a blog, the blog is never a page.
        return Helper::has_page_on_front() ? static::is_blog( $post ) : false;
    }

    /**
     * Detects category archives.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @uses static::is_archive()
     *
     * @param mixed $category Optional. Category ID, name, slug, or array of Category IDs, names, and slugs.
     * @return bool
     */
    public static function is_category( $category = '' ) {

        if ( \is_admin() )
            return static::is_category_admin();

        return Cache::memo( null, $category )
            ?? Cache::memo( \is_category( $category ), $category );
    }

    /**
     * Extends default WordPress is_category() and determines screen in admin.
     *
     * @since 2.6.0
     * @since 3.1.0 No longer guesses category by name. It now only matches WordPress's built-in category.
     * @since 4.0.0 Removed caching.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool Post Type is category
     */
    public static function is_category_admin() {
        return static::is_archive_admin() && 'category' === static::get_current_taxonomy();
    }

    /**
     * Determines if the current query handles term metadata.
     *
     * @since 5.0.0
     *
     * @return bool
     */
    public static function is_editable_term() {
        return Cache::memo()
            ?? Cache::memo(
                Query::is_category() || Query::is_tag() || Query::is_tax()
            );
    }

    /**
     * Detects front page.
     *
     * Adds support for custom "show_on_front" entries.
     * When the homepage isn't a 'page' (tested via `is_front_page()`) or 'post',
     * it isn't considered a real front page -- it could be anything custom (Extra by Elegant Themes),
     * or the `show_on_front` setting is somehow corrupted.
     *
     * @since 2.9.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool
     */
    public static function is_real_front_page() {
        return Cache::memo()
            ?? Cache::memo(
                \is_front_page()
                    ?: static::is_blog()
                    && 0 === static::get_the_real_id()
                    && 'post' !== \get_option( 'show_on_front' ) // 'page' is tested via `is_front_page()`
            );
    }

    /**
     * Checks for front page by input ID without engaging into the query.
     *
     * @NOTE This doesn't check for anomalies in the query.
     * So, don't use this to test user-engaged WordPress queries, ever.
     * WARNING: This will lead to **FALSE POSITIVES** for Date, CPTA, Search, and other archives.
     *
     * @see $this->is_real_front_page(), which solely uses query checking.
     * @see static::is_static_front_page(), which adds an "is homepage static" check.
     *
     * @since 3.2.2
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @param int $id The tested ID.
     * @return bool
     */
    public static function is_real_front_page_by_id( $id ) {
        return static::get_the_front_page_id() === $id;
    }

    /**
     * Detects pages.
     * When $page is supplied, it will check against the current object. So it will not work in the admin screens.
     *
     * @since 2.6.0
     * @since 4.0.0 Now tests for post type, which is more reliable.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @api not used internally, polar opposite of is_single().
     * @uses static::is_singular()
     *
     * @param int|string|array $page Optional. Page ID, title, slug, or array of such. Default empty.
     * @return bool
     */
    public static function is_page( $page = '' ) {

        if ( \is_admin() )
            return static::is_page_admin();

        if ( empty( $page ) )
            return \is_page();

        return Cache::memo( null, $page )
            ?? Cache::memo(
                \is_int( $page ) || $page instanceof \WP_Post
                    ? \in_array( \get_post_type( $page ), Helper::get_all_hierarchical(), true )
                    : \is_page( $page ),
                $page,
            );
    }

    /**
     * Detects pages within the admin area.
     *
     * @since 2.6.0
     * @since 4.0.0 Now tests for post type, although redundant.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @see static::is_page()
     *
     * @return bool
     */
    public static function is_page_admin() {
        return static::is_singular_admin()
            && \in_array( static::is_singular_admin(), Helper::get_all_hierarchical(), true );
    }

    /**
     * Detects search.
     *
     * @since 2.6.0
     * @since 2.9.4 Now always returns false in admin.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool
     */
    public static function is_search() {
        return \is_search() && ! \is_admin();
    }

    /**
     * Determines if the current page is singular is holds singular items within the admin screen.
     * Replaces and expands default WordPress `is_singular()`.
     *
     * @since 2.5.2
     * @since 3.1.0 Now passes $post_types parameter in admin screens, only when it's an integer.
     * @since 4.0.0 No longer processes integers as input.
     * @since 4.2.4 No longer tests type of $post_types.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @uses static::is_singular_admin()
     *
     * @param string|string[] $post_types Optional. Post type or array of post types. Default empty string.
     * @return bool Post Type is singular
     */
    public static function is_singular( $post_types = '' ) {

        // WP_Query functions require loop, do alternative check.
        if ( \is_admin() )
            return static::is_singular_admin();

        if ( $post_types )
            return \is_singular( $post_types );

        return Cache::memo()
            ?? Cache::memo( \is_singular() || static::is_singular_archive() );
    }

    /**
     * Determines if the page is singular within the admin screen.
     *
     * @since 2.5.2
     * @since 3.1.0 Added $post_id parameter. When used, it'll only check for it.
     * @since 4.0.0 Removed first parameter.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Screen $current_screen
     *
     * @return bool Post Type is singular
     */
    public static function is_singular_admin() {

        switch ( $GLOBALS['current_screen']->base ?? '' ) {
            case 'edit':
            case 'post':
                return true;
        }

        return false;
    }

    /**
     * Detects the static front page.
     *
     * @since 5.0.0
     *
     * @param int $id the Page ID to check. If empty, the current ID will be fetched.
     * @return bool True when homepage is static and given/current ID matches.
     */
    public static function is_static_front_page( $id = 0 ) {

        // Memo this slow part separately; memo_query() would cache the whole method, which isn't necessary.
        $front_id = Helper::umemo( __METHOD__ )
            ?? Helper::umemo(
                __METHOD__,
                Helper::has_assigned_page_on_front()
                    ? (int) \get_option( 'page_on_front' )
                    : false,
            );

        return false !== $front_id && ( $id ?: static::get_the_real_id() ) === $front_id;
    }

    /**
     * Detects tag archives.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @uses static::is_archive()
     *
     * @param mixed $tag Optional. Tag ID, name, slug, or array of Tag IDs, names, and slugs.
     * @return bool
     */
    public static function is_tag( $tag = '' ) {

        // Admin requires another check.
        if ( \is_admin() )
            return static::is_tag_admin();

        return Cache::memo( null, $tag )
            ?? Cache::memo( \is_tag( $tag ), $tag );
    }

    /**
     * Determines if the page is a tag within the admin screen.
     *
     * @since 2.6.0
     * @since 3.1.0 No longer guesses tag by name. It now only matches WordPress's built-in tag.
     * @since 4.0.0 Removed caching.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool Post Type is tag.
     */
    public static function is_tag_admin() {
        return static::is_archive_admin() && 'post_tag' === static::get_current_taxonomy();
    }

    /**
     * Detects taxonomy archives.
     *
     * @since 2.6.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @TODO add is_tax_admin() ?
     *
     * @param string|array     $taxonomy Optional. Taxonomy slug or slugs.
     * @param int|string|array $term     Optional. Term ID, name, slug or array of Term IDs, names, and slugs.
     * @return bool
     */
    public static function is_tax( $taxonomy = '', $term = '' ) {
        return Cache::memo( null, $taxonomy, $term )
            ?? Cache::memo( \is_tax( $taxonomy, $term ), $taxonomy, $term );
    }

    /**
     * Determines if SSL is used.
     * Memoizes the return value.
     *
     * @since 2.8.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool True if SSL, false otherwise.
     */
    public static function is_ssl() {
        return Helper::umemo( __METHOD__ )
            ?? Helper::umemo( __METHOD__, \is_ssl() );
    }

    /**
     * Returns the current page number.
     * Fetches global `$page` from `WP_Query` to prevent conflicts.
     *
     * @since 2.6.0
     * @since 3.2.4 1. Added overflow protection.
     *              2. Now always returns 1 on the admin screens.
     * @since 4.2.8 Now returns the last page on pagination overflow,
     *              but only when we're on a paginated static frontpage.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return int (R>0) $page Always a positive number.
     */
    public static function page() {

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition
        if ( null !== $memo = Cache::memo() )
            return $memo;

        if ( static::is_multipage() ) {
            $page = ( (int) \get_query_var( 'page' ) ) ?: 1;
            $max  = static::numpages();

            if ( $page > $max ) {
                // On overflow, WP returns the first page.
                // Exception: When we are on a paginated static frontpage, WP returns the last page...
                if ( static::is_static_front_page() ) {
                    $page = $max;
                } else {
                    $page = 1;
                }
            }
        } else {
            $page = 1;
        }

        return Cache::memo( $page );
    }

    /**
     * Returns the current page number.
     * Fetches global `$paged` from `WP_Query` to prevent conflicts.
     *
     * @since 2.6.0
     * @since 3.2.4 1. Added overflow protection.
     *              2. Now always returns 1 on the admin screens.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return int (R>0) $paged Always a positive number.
     */
    public static function paged() {

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition
        if ( null !== $memo = Cache::memo() )
            return $memo;

        if ( static::is_multipage() ) {
            $paged = ( (int) \get_query_var( 'paged' ) ) ?: 1;
            $max   = static::numpages();

            if ( $paged > $max ) {
                // On overflow, WP returns the last page.
                $paged = $max;
            }
        } else {
            $paged = 1;
        }

        return Cache::memo( $paged );
    }

    /**
     * Determines the number of available pages.
     *
     * This is largely taken from \WP_Query::setup_postdata(), however, the data
     * we need is set up in the loop, not in the header; where TSF is active.
     *
     * @since 3.1.0
     * @since 3.2.4 Now only returns "1" in the admin.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     * @global \WP_Query $wp_query
     *
     * @return int
     */
    public static function numpages() {

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition
        if ( null !== $memo = Cache::memo() )
            return $memo;

        if ( \is_admin() ) {
            // Disable pagination detection in admin: Always on page 1.
            return Cache::memo( 1 );
        }

        global $wp_query;

        if ( static::is_singular() && ! static::is_singular_archive() )
            $post = \get_post( static::get_the_real_id() );

        if ( ( $post ?? null ) instanceof \WP_Post ) {
            $content = Helper::get_content( $post );

            if ( str_contains( $content, '<!--nextpage-->' ) ) {
                $content = str_replace( "\n<!--nextpage-->", '<!--nextpage-->', $content );

                // Ignore nextpage at the beginning of the content.
                if ( str_starts_with( $content, '<!--nextpage-->' ) )
                    $content = substr( $content, 15 );

                $pages = explode( '<!--nextpage-->', $content );
            } else {
                $pages = [ $content ];
            }

            /**
             * Filter the "pages" derived from splitting the post content.
             *
             * "Pages" are determined by splitting the post content based on the presence
             * of `<!-- nextpage -->` tags.
             *
             * @since 4.4.0 WordPress core
             *
             * @param array    $pages Array of "pages" derived from the post content.
             *                 of `<!-- nextpage -->` tags..
             * @param \WP_Post $post  Current post object.
             */
            $pages = \apply_filters( 'breadcrumbs_for_elementor_content_pagination', $pages, $post );

            $numpages = \count( $pages );
        } elseif ( isset( $wp_query->max_num_pages ) ) {
            $numpages = (int) $wp_query->max_num_pages;
        } else {
            // Empty or faulty query, bail.
            $numpages = 0;
        }

        return Cache::memo( $numpages );
    }

    /**
     * Determines whether the current loop has multiple pages.
     *
     * @since 2.7.0
     * @since 3.1.0 1. Now also works on archives.
     *              2. Now is public.
     * @since 3.2.4 Now always returns false on the admin pages.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool True if multipage.
     */
    public static function is_multipage() {
        return static::numpages() > 1;
    }
}