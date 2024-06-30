<?php

namespace BCFE;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use BCFE\Sanitize;


class Helper
{
    /**
     * @since 5.0.0
     * @var array[] Stored primary term IDs cache.
     */
    private static $pt_memo = [];

    /**
     * Stores and returns memoized values for the caller.
     *
     * This method is not forward-compatible with PHP: It expects values it doesn't want populated,
     * instead of filtering what's actually useful for memoization. For example, it expects `file`
     * and `line` from debug_backtrace() -- those are expected to be dynamic from the caller, and
     * we set them to `0` to prevent a few opcode calls, rather than telling which array indexes
     * we want exactly. The chance this failing in a future update is slim, for all useful data of
     * the callee is given already via debug_backtrace().
     * We also populate the `args` value "manually" for it's faster than using debug_backtrace()'s
     * `DEBUG_BACKTRACE_PROVIDE_OBJECT` option.
     *
     * We should keep a tap on debug_backtrace changes. Hopefully, they allow us to ignore
     * more than just args.
     *
     * This method does not memoize the object via debug_backtrace. This means that the
     * objects will have values memoized cross-instantiations.
     *
     * Example usage:
     * ```
     * function expensive_call( $arg ) {
     *     print( "expensive $arg!" );
     *     return $arg * 2;
     * }
     * function my_function( $arg ) {
     *    return memo( null, $arg );
     *        ?? memo( expensive_call( $arg ), $arg );
     * }
     * my_function( 1 ); // prints "expensive 1!", returns 2.
     * my_function( 1 ); // returns 2.
     * my_function( 2 ); // prints "expensive 2!", returns 4.
     *
     * function test() {
     *     return memo() ?? memo( expensive_call( 42 ) );
     * }
     * test(); // prints "expensive 42", returns 84.
     * test(); // returns 84.
     * ```
     *
     * @param mixed $value_to_set The value to set.
     * @param mixed ...$args Extra arguments, that are used to differentiaty callbacks.
     *                            Arguments may not contain \Closure()s.
     * @return mixed : {
     *    mixed The cached value if set and $value_to_set is null.
     *       null When no value has been set.
     *       If $value_to_set is set, the new value.
     * }
     * @api
     *
     * @since 4.2.0
     * @see umemo() -- sacrifices cleanliness for performance.
     * @see fmemo() -- sacrifices everything for readability.
     */
    static public function memo($value_to_set = null, ...$args)
    {

        static $memo = [];

        // phpcs:ignore, WordPress.PHP.DiscouragedPHPFunctions -- No objects inserted, nor ever unserialized.
        $hash = serialize(
            [
                'args' => $args,
                'file' => 0,
                'line' => 0,
            ]
            // phpcs:ignore, WordPress.PHP.DevelopmentFunctions -- This is the only efficient way.
            + debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1],
        );

        if (isset($value_to_set))
            return $memo[$hash] = $value_to_set;

        return $memo[$hash] ?? null;
    }

    /**
     * Stores and returns memoized values for the caller.
     * This is 10 times faster than memo(), but requires from you a $key.
     *
     * We're talking milliseconds over thousands of iterations, though.
     *
     * Example usage:
     * ```
     * function expensive_call( $arg ) {
     *     print( "expensive $arg!" );
     *     return $arg * 2;
     * }
     * function my_function( $arg ) {
     *    return umemo( __METHOD__, null, $arg );
     *        ?? umemo( __METHOD__, expensive_call( $arg ), $arg );
     * }
     * my_function( 1 ); // prints "expensive 1!", returns 2.
     * my_function( 1 ); // returns 2.
     * my_function( 2 ); // prints "expensive 2!", returns 4.
     * ```
     *
     * @param string $key The key you want to use to memoize. It's best to use the method name.
     *                             You can share a unique key between various functions.
     * @param mixed $value_to_set The value to set.
     * @param mixed ...$args Extra arguments, that are used to differentiate callbacks.
     *                             Arguments may not contain \Closure()s.
     * @return mixed : {
     *    mixed The cached value if set and $value_to_set is null.
     *       null When no value has been set.
     *       If $value_to_set is set, the new value.
     * }
     * @since 4.2.0
     * @see memo() -- sacrifices performance for cleanliness.
     * @see fmemo() -- sacrifices everything for readability.
     * @api
     *
     */
    static public function umemo($key, $value_to_set = null, ...$args)
    {

        static $memo = [];

        // phpcs:ignore, WordPress.PHP.DiscouragedPHPFunctions -- No objects are inserted, nor is this ever unserialized.
        $hash = serialize([$key, $args]);

        if (isset($value_to_set))
            return $memo[$hash] = $value_to_set;

        return $memo[$hash] ?? null;
    }

    /**
     * Normalizes generation args to prevent PHP warnings.
     * This is the standard way TSF determines the type of query.
     *
     * 'uid' is reserved. It is already used in Author::build(), however.
     *
     * @param array|null $args The query arguments. Accepts 'id', 'tax', 'pta', and 'uid'.
     *                         Leave null to have queries be autodetermined.
     *                         Passed by reference.
     * @see https://github.com/sybrew/the-seo-framework/issues/640#issuecomment-1703260744.
     *      We made an exception about passing by reference for this function.
     *
     * @since 5.0.0
     */
    public static function normalize_generation_args(&$args)
    {

        if (\is_array($args)) {
            $args += [
                'id' => 0,
                'tax' => $args['taxonomy'] ?? '',
                'taxonomy' => $args['tax'] ?? '', // Legacy support.
                'pta' => '',
                'uid' => 0,
            ];
        } else {
            $args = null;
        }
    }

    /**
     * Returns the primary term ID for post.
     *
     * @param int $post_id The post ID.
     * @param string $taxonomy The taxonomy name.
     * @return int   The primary term ID. 0 if not found.
     * @since 3.0.0
     * @since 4.1.5 1. Now validates if the stored term ID's term exists (for the post or at all).
     *              2. The first and second parameters are now required.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     */
    public static function get_primary_term_id($post_id, $taxonomy)
    {
        return static::get_primary_term($post_id, $taxonomy)->term_id ?? 0;
    }

    /**
     * Returns the primary term for post.
     *
     * @param int $post_id The post ID.
     * @param string $taxonomy The taxonomy name.
     * @return ?\WP_Term The primary term. Null if cannot be generated.
     * @since 3.0.0
     * @since 4.1.5   1. Added memoization.
     *                2. The first and second parameters are now required.
     * @since 4.1.5.1 1. No longer causes a PHP warning in the unlikely event a post's taxonomy gets deleted.
     *                2. This method now converts the post meta to an integer, making the comparison work again.
     * @since 4.2.7 Now correctly memoizes when no terms for a post can be found.
     * @since 4.2.8 Now correctly returns when no terms for a post can be found.
     * @since 5.0.0 1. Now always tries to return a term if none is set manually.
     *              2. Now returns `null` instead of `false` on failure.
     *              3. Now considers headlessness.
     *              4. Moved from `\The_SEO_Framework\Load`.
     * @since 5.0.2 Now selects the last child of a primary term if its parent has the lowest ID.
     *
     */
    public static function get_primary_term($post_id, $taxonomy)
    {

        if (isset(static::$pt_memo[$post_id][$taxonomy]))
            return static::$pt_memo[$post_id][$taxonomy] ?: null;

        // Keep lucky first when exceeding nice numbers. This way, we won't overload memory in memoization.
        if (\count(static::$pt_memo) > 69)
            static::$pt_memo = \array_slice(static::$pt_memo, 0, 7, true);

        $is_headless = false;

        if ($is_headless) {
            $primary_id = 0;
        } else {
            $primary_id = (int)\get_post_meta($post_id, "_primary_term_{$taxonomy}", true) ?: 0;
        }

        // Users can alter the term list via quick/bulk edit, but cannot set a primary term that way.
        // Users can also delete a term from the site that was previously assigned as primary.
        // So, test if the term still exists for the post.
        // Although 'get_the_terms()' is an expensive function, it memoizes, and
        // is always called by WP before we fetch a primary term. So, 0 overhead here.
        $terms = \get_the_terms($post_id, $taxonomy);
        $primary_term = null;

        if ($terms && \is_array($terms)) {
            if ($primary_id) {
                // Test for is_array in the unlikely event a post's taxonomy is gone ($terms = WP_Error)
                foreach ($terms as $term) {
                    if ($primary_id === $term->term_id) {
                        $primary_term = $term;
                        break;
                    }
                }
            } else {
                $term_ids = array_column($terms, 'term_id');
                asort($term_ids);
                $primary_term = $terms[array_key_first($term_ids)] ?? null;

                if ($primary_term && \count($terms) > 1) {
                    // parent_id => child_id; could be 0 => child_id if it has no parent.
                    $child_by_parent = array_column($terms, 'term_id', 'parent');
                    // term_id => $term index; related to $terms, flipped to speed up lookups.
                    $term_by_term_id = array_flip($term_ids);

                    // Chain the isset because it expects an array.
                    while (isset(
                        $child_by_parent[$primary_term->term_id],
                        $term_by_term_id[$child_by_parent[$primary_term->term_id]],
                        $terms[$term_by_term_id[$child_by_parent[$primary_term->term_id]]], // this is always an object.
                    )) {
                        $primary_term = $terms[$term_by_term_id[$child_by_parent[$primary_term->term_id]]];
                    }
                }
            }
        }

        /**
         * @param ?\WP_Term $primary_term The primary term. Null if cannot be generated.
         * @param int $post_id The post ID.
         * @param string $taxonomy The taxonomy name.
         * @param bool $is_headless Whether the meta are headless.
         * @since 5.0.0
         */
        static::$pt_memo[$post_id][$taxonomy] = \apply_filters(
            'the_seo_framework_primary_term',
            $primary_term,
            $post_id,
            $taxonomy,
            $is_headless,
        ) ?: false;

        return static::$pt_memo[$post_id][$taxonomy] ?: null;
    }

    /**
     * Returns hierarchical taxonomies for post type.
     *
     * @param string $get What to get. Accepts 'names' or 'objects'.
     * @param string $post_type The post type. Will default to current post type.
     * @return object[]|string[] The post type taxonomy objects or names.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_hierarchical_taxonomies_as`.
     *
     * @since 3.0.0
     * @since 4.0.5 The `$post_type` fallback now uses a real query ID, instead of `$GLOBALS['post']`.
     * @since 4.1.0 Now filters taxonomies more graciously--expecting broken taxonomies returned in the filter.
     */
    public static function get_hierarchical($get = 'objects', $post_type = '')
    {

        $post_type = $post_type ?: Query::get_current_post_type();

        if (!$post_type)
            return [];

        $taxonomies = array_filter(
            \get_object_taxonomies($post_type, 'objects'),
            static fn($t) => !empty($t->hierarchical),
        );

        // If names isn't $get, assume objects.
        return 'names' === $get ? array_keys($taxonomies) : $taxonomies;
    }

    /**
     * Returns taxonomical permalink without query adjustments.
     *
     * @param int|null $term_id The term ID.
     * @param string $taxonomy The taxonomy. Leave empty to autodetermine.
     * @return string The taxonomical canonical URL, if any.
     * @since 5.0.0
     *
     */
    public static function get_bare_term_url($term_id = null, $taxonomy = '')
    {

        if (empty($term_id)) {
            $term_id = Query::get_the_real_id();
            $taxonomy = Query::get_current_taxonomy();
        }

        $url = \get_term_link($term_id, $taxonomy);

        if (empty($url) || !\is_string($url))
            return '';

        return \sanitize_url(
            static::set_preferred_url_scheme($url),
            ['https', 'http'],
        );
    }

    /**
     * Sets URL to preferred URL scheme.
     * Does not sanitize output.
     *
     * @param string $url The URL to set scheme for.
     * @return string The URL with the preferred scheme.
     * @since 2.8.0
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     */
    public static function set_preferred_url_scheme($url)
    {
        return static::set_url_scheme($url, static::get_preferred_url_scheme());
    }

    /**
     * Sets URL scheme for input URL.
     * WordPress core function, without filter.
     *
     * @param string $url Absolute url that includes a scheme.
     * @param string $scheme Optional. Scheme to give $url. Currently 'http', 'https', or 'relative'.
     * @return string url with chosen scheme.
     * @since 4.0.0 Removed the deprecated parameter.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Removed support for $scheme type 'admin', 'login', 'login_post', and 'rpc'.
     *
     * @since 2.4.2
     * @since 3.0.0 $use_filter now defaults to false.
     * @since 3.1.0 The third parameter ($use_filter) is now $deprecated.
     */
    public static function set_url_scheme($url, $scheme = null)
    {

        $url = static::make_fully_qualified_url($url);

        switch ($scheme) {
            case 'https':
            case 'http':
            case 'relative':
                break;
            default:
                $scheme = Query::is_ssl() ? 'https' : 'http';
        }

        if ('relative' === $scheme) {
            $url = ltrim(preg_replace('/^\w+:\/\/[^\/]*/', '', $url));

            if ('/' === ($url[0] ?? ''))
                $url = '/' . ltrim($url, "/ \t\n\r\0\x0B");
        } else {
            $url = preg_replace('#^\w+://#', $scheme . '://', $url);
        }

        return $url;
    }

    /**
     * Makes a fully qualified URL by adding the scheme prefix.
     * Always adds http prefix, not https.
     *
     * NOTE: Expects the URL to have either a scheme, or a relative scheme set.
     *       Domain-relative URLs will not be parsed correctly.
     *       '/path/to/folder/` will become `http:///path/to/folder/`
     *
     * @param string $url The current maybe not fully qualified URL. Required.
     * @return string $url
     * @see `static::set_url_scheme()` to set the correct scheme.
     * @see `static::convert_path_to_url()` to create URLs from paths.
     *
     * @since 2.6.5
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     */
    public static function make_fully_qualified_url($url)
    {

        if ('//' === substr($url, 0, 2))
            return "http:$url";

        if ('http' !== substr($url, 0, 4))
            return "http://{$url}";

        return $url;
    }

    /**
     * Returns an unbranded, unpaginated, and unprotected title
     * from custom fields or an autogenerated fallback.
     *
     * @param array|null $args The query arguments. Accepts 'id', 'tax', 'pta', and 'uid'.
     *                         Leave null to autodetermine query.
     * @return string The unmodified title output.
     * @since 5.0.0
     *
     */
    public static function get_bare_title($args = null)
    {
        return static::get_bare_generated_title($args);
    }

    /**
     * Returns the raw filtered autogenerated meta title.
     *
     * @param array|null $args The query arguments. Accepts 'id', 'tax', 'pta', and 'uid'.
     *                           Leave null to autodetermine query.
     * @return string The raw generated title output.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @since 4.0.0
     * @since 4.2.0 1. The first parameter can now be voided.
     *              2. The first parameter is now rectified, so you can leave out indexes.
     *              3. Now supports the `$args['pta']` index.
     */
    public static function get_bare_generated_title($args = null)
    {

        isset($args) and static::normalize_generation_args($args);

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition -- I know.
        if (null !== $memo = static::memo(null, $args)) return $memo;

//        Title\Utils::remove_default_title_filters(false, $args);

        $title = isset($args)
            ? static::generate_title_from_args($args)
            : static::generate_title_from_query();

//        Title\Utils::reset_default_title_filters();

        /**
         * Filters the title from query.
         *
         * @NOTE: This filter doesn't consistently run on the SEO Settings page.
         *        You may want to avoid this filter for the homepage and pta, by returning the default value.
         * @param string $title The title.
         * @param array|null $args The query arguments. Contains 'id', 'tax', 'pta', and 'uid'.
         *                          Is null when the query is auto-determined.
         * @since 3.1.0
         * @since 4.2.0 Now supports the `$args['pta']` index.
         */
        $title = (string)\apply_filters(
            'the_seo_framework_title_from_generation',
            $title ?: static::get_untitled_title(),
            $args,
        );

        return static::memo(
            \strlen($title) ? Sanitize::metadata_content($title) : '',
            $args,
        );
    }

    /**
     * Generates a title, based on expected query, without additions or prefixes.
     *
     * @param array $args The query arguments. Required. Accepts 'id', 'tax', 'pta', and 'uid'.
     * @return string The generated title. Empty if query can't be replicated.
     * @since 5.0.0
     *
     */
    public static function generate_title_from_args($args)
    {

        static::normalize_generation_args($args);

        // TODO switch get_query_type_from_args()?
        if ($args['tax']) {
            $title = static::get_archive_title(\get_term($args['id'], $args['tax']));
        } elseif ($args['pta']) {
            $title = static::get_archive_title(\get_post_type_object($args['pta']));
        } elseif ($args['uid']) {
            $title = static::get_archive_title(\get_userdata($args['uid']));
        } elseif (Query::is_real_front_page_by_id($args['id'])) {
            $title = static::get_front_page_title();
        } elseif ($args['id']) {
            $title = static::get_post_title($args['id']);
        }

        return $title ?? '';
    }

    /**
     * Returns the archive title. Also works in admin.
     *
     * @NOTE Taken from WordPress core. Altered to work for metadata and in admin.
     * @param \WP_Term|\WP_User|\WP_Post_Type|\WP_Error|null $object The Term object or error.
     *                                                               Leave null to autodetermine query.
     * @return string The generated archive title.
     * @see WP Core get_the_archive_title()
     *
     * @since 5.0.0
     *
     */
    public static function get_archive_title($object = null)
    {

        if ($object && \is_wp_error($object))
            return '';

        return static::get_archive_title_list($object)[0];
    }

    /**
     * Returns the archive title items. Also works in admin.
     *
     * @NOTE Taken from WordPress core. Altered to work for metadata.
     * @param \WP_Term|\WP_User|\WP_Post_Type|null $object The Term object.
     *                                                     Leave null to autodetermine query.
     * @return String[title,prefix,title_without_prefix] The generated archive title items.
     * @see WP Core get_the_archive_title()
     *
     * @since 5.0.0
     *
     */
    public static function get_archive_title_list($object = null)
    {

        [$title, $prefix] = $object
            ? static::get_archive_title_from_object($object)
            : static::get_archive_title_from_query();

        $title_without_prefix = $title;

        /**
         * @param String[title,prefix,title_without_prefix] $items                The generated archive title items.
         * @param \WP_Term|\WP_User|\WP_Post_Type|null $object The archive object.
         *                                                                        Is null when query is autodetermined.
         * @param string $title_without_prefix Archive title without prefix.
         * @param string $prefix Archive title prefix.
         * @since 5.0.0
         */
        return \apply_filters(
            'the_seo_framework_generated_archive_title_items',
            [
                $title,
                $prefix,
                $title_without_prefix,
            ],
            $object,
            $title,
            $title_without_prefix,
            $prefix,
        );
    }

    /**
     * Returns the generated archive title by evaluating the input Term only.
     *
     * @param \WP_Term|\WP_User|\WP_Post_Type $object The Term object.
     * @return string[$title,$prefix] The title and prefix.
     * @since 5.0.0
     *
     */
    public static function get_archive_title_from_object($object)
    {

        $title = \__('Archives', 'default');
        $prefix = '';

        if (!empty($object->taxonomy)) {
            $title = static::get_term_title($object);

            switch ($object->taxonomy) {
                case 'category':
                    $prefix = \_x('Category:', 'category archive title prefix', 'default');
                    break;
                case 'post_tag':
                    $prefix = \_x('Tag:', 'tag archive title prefix', 'default');
                    break;
                default:
                    $prefix = sprintf(
                    /* translators: %s: Taxonomy singular name. */
                        \_x('%s:', 'taxonomy term archive title prefix', 'default'),
                        static::get_label($object->taxonomy),
                    );
            }
        } elseif ($object instanceof \WP_Post_Type) {
            $title = static::get_post_type_archive_title($object->name);
            $prefix = \_x('Archives:', 'post type archive title prefix', 'default');
        } elseif ($object instanceof \WP_User) {
            $title = static::get_user_title($object->ID);
            $prefix = \_x('Author:', 'author archive title prefix', 'default');
        }

        return [$title, $prefix];
    }

    /**
     * Fetches single term title.
     *
     * It can autodetermine the term; so, perform your checks prior calling.
     *
     * Taken from WordPress core. Altered to work in the Admin area.
     *
     * @param null|\WP_Term $term The term name, required in the admin area.
     * @return string The generated single term title.
     * @see WP Core single_term_title()
     *
     * @since 5.0.0
     *
     */
    public static function get_term_title($term = null)
    {

        $term ??= \get_queried_object();

        // We're allowing `0` as a term name here. https://core.trac.wordpress.org/ticket/56518
        if (!isset($term->name)) return '';

        switch ($term->taxonomy) {
            case 'category':
                /**
                 * Filter the category archive page title.
                 *
                 * @param string $term_name Category name for archive being displayed.
                 * @since WP Core 2.0.10
                 *
                 */
                $title = \apply_filters('single_cat_title', $term->name);
                break;
            case 'post_tag':
                /**
                 * Filter the tag archive page title.
                 *
                 * @param string $term_name Tag name for archive being displayed.
                 * @since WP Core 2.3.0
                 *
                 */
                $title = \apply_filters('single_tag_title', $term->name);
                break;
            default:
                /**
                 * Filter the custom taxonomy archive page title.
                 *
                 * @param string $term_name Term name for archive being displayed.
                 * @since WP Core 3.1.0
                 *
                 */
                $title = \apply_filters('single_term_title', $term->name);
        }

        return \strlen($title) ? Sanitize::metadata_content($title) : '';
    }

    /**
     * Generates front page title.
     *
     * This is an alias of get_blogname(). The difference is that this is used for
     * the front-page title output solely, whereas the other one has a mixed usage.
     *
     * @return string The generated front page title.
     * @since 5.0.0
     *
     */
    public static function get_front_page_title()
    {
        return Sanitize::metadata_content(static::get_public_blog_name());
    }

    /**
     * Fetches single term title.
     *
     * @NOTE Taken from WordPress core. Altered to work in the Admin area.
     * @param string $post_type The post type.
     * @return string The generated post type archive title.
     * @see WP Core post_type_archive_title()
     *
     * @since 5.0.0
     *
     */
    public static function get_post_type_archive_title($post_type = '')
    {

        $post_type = $post_type ?: Query::get_current_post_type();

        if (\is_array($post_type))
            $post_type = reset($post_type);

        if (!\in_array($post_type, static::get_public_pta(), true))
            return '';

        /**
         * Filters the post type archive title.
         *
         * @param string $post_type_name Post type 'name' label.
         * @param string $post_type Post type.
         * @since WP Core 3.1.0
         *
         */
        $title = \apply_filters(
            'post_type_archive_title',
            static::get_label($post_type, false),
            $post_type,
        );

        return \strlen($title) ? Sanitize::metadata_content($title) : '';
    }

    /**
     * Returns the post type object label. Either plural or singular.
     *
     * @param string $post_type The post type. Required.
     * @param bool $singular Whether to get the singlural or plural name.
     * @return string The Post Type name/label, if found.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_post_type_label`.
     *
     * @since 3.1.0
     */
    public static function get_label($post_type, $singular = true)
    {
        return \get_post_type_object($post_type)->labels->{
        $singular ? 'singular_name' : 'name'
        } ?? '';
    }

    /**
     * Gets all post types that have PTA and could support SEO.
     * Memoizes the return value.
     *
     * @return string[] Public post types with post type archive support.
     * @since 4.2.8 Added filter `the_seo_framework_public_post_type_archives`.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_public_post_type_archives`.
     *
     * @since 4.2.0
     */
    public static function get_public_pta()
    {
        return static::umemo(__METHOD__)
            ?? static::umemo(
                __METHOD__,
                /**
                 * Do not consider using this filter. Properly register your post type, noob.
                 *
                 * @param string[] $post_types The public post types.
                 * @since 4.2.8
                 */
                (array)\apply_filters(
                    'the_seo_framework_public_post_type_archives',
                    array_values(
                        array_filter(
                            static::get_all_public(),
                            static fn($post_type) => \get_post_type_object($post_type)->has_archive ?? false,
                        )
                    )
                )
            );
    }

    /**
     * Gets all post types that could possibly support SEO.
     * Memoizes the return value.
     *
     * @return string[] All public post types.
     * @since 4.1.4 Now resets the index keys of the return value.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_public_post_types`.
     *              3. Is now public.
     *
     * @since 4.1.0
     */
    public static function get_all_public()
    {
        return static::umemo(__METHOD__)
            ?? static::umemo(
                __METHOD__,
                /**
                 * Do not consider using this filter. Properly register your post type, noob.
                 *
                 * @param string[] $post_types The public post types.
                 * @since 4.2.0
                 */
                (array)\apply_filters(
                    'the_seo_framework_public_post_types',
                    array_values(array_filter(
                        array_unique(array_merge(
                            static::get_all_forced_supported(),
                            // array_keys() because get_post_types() gives a sequential array.
                            array_keys((array)\get_post_types(['public' => true]))
                        )),
                        'is_post_type_viewable',
                    ))
                )
            );
    }

    /**
     * Returns a list of builtin public post types.
     *
     * @return string[] Forced supported post types.
     * @since 4.2.0 Removed memoization.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_forced_supported_post_types`.
     *              3. Is now public.
     *
     * @since 3.1.0
     */
    public static function get_all_forced_supported()
    {
        /**
         * @param string[] $forced Forced supported post types
         * @since 3.1.0
         */
        return (array)\apply_filters(
            'the_seo_framework_forced_supported_post_types',
            array_values(\get_post_types([
                'public' => true,
                '_builtin' => true,
            ])),
        );
    }

    /**
     * Fetches public blogname (site title).
     * Memoizes the return value.
     *
     * Do not consider this function safe for printing!
     *
     * @return string $blogname The sanitized blogname.
     * @since 5.0.0
     *
     */
    public static function get_public_blog_name()
    {
        return static::umemo(__METHOD__)
            ?? static::umemo(
                __METHOD__,
                get_option('site_title')
            );
    }

    /**
     * Fetches user title.
     *
     * @param int $user_id The user ID.
     * @return string The generated post type archive title.
     * @since 5.0.0
     *
     */
    public static function get_user_title($user_id = 0)
    {
        return Sanitize::metadata_content(
            \get_userdata($user_id ?: Query::get_the_real_id())->display_name ?? ''
        );
    }

    /**
     * Returns the generated archive title by evaluating the input Term only.
     *
     * @return string[$title,$prefix] The title and prefix.
     * @since 5.0.0
     *
     */
    public static function get_archive_title_from_query()
    {

        $title = \__('Archives', 'default');
        $prefix = '';

        if (Query::is_category()) {
            $title = static::get_term_title();
            $prefix = \_x('Category:', 'category archive title prefix', 'default');
        } elseif (Query::is_tag()) {
            $title = static::get_term_title();
            $prefix = \_x('Tag:', 'tag archive title prefix', 'default');
        } elseif (Query::is_author()) {
            $title = static::get_user_title();
            $prefix = \_x('Author:', 'author archive title prefix', 'default');
        } elseif (\is_date()) {
            if (\is_year()) {
                $title = \get_the_date(\_x('Y', 'yearly archives date format', 'default'));
                $prefix = \_x('Year:', 'date archive title prefix', 'default');
            } elseif (\is_month()) {
                $title = \get_the_date(\_x('F Y', 'monthly archives date format', 'default'));
                $prefix = \_x('Month:', 'date archive title prefix', 'default');
            } elseif (\is_day()) {
                $title = \get_the_date(\_x('F j, Y', 'daily archives date format', 'default'));
                $prefix = \_x('Day:', 'date archive title prefix', 'default');
            }
        } elseif (\is_tax('post_format')) {
            if (\is_tax('post_format', 'post-format-aside')) {
                $title = \_x('Asides', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-gallery')) {
                $title = \_x('Galleries', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-image')) {
                $title = \_x('Images', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-video')) {
                $title = \_x('Videos', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-quote')) {
                $title = \_x('Quotes', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-link')) {
                $title = \_x('Links', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-status')) {
                $title = \_x('Statuses', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-audio')) {
                $title = \_x('Audio', 'post format archive title', 'default');
            } elseif (\is_tax('post_format', 'post-format-chat')) {
                $title = \_x('Chats', 'post format archive title', 'default');
            }
        } elseif (\is_post_type_archive()) {
            $title = static::get_post_type_archive_title();
            $prefix = \_x('Archives:', 'post type archive title prefix', 'default');
        } elseif (Query::is_tax()) {
            $term = \get_queried_object();

            if ($term) {
                $title = static::get_term_title($term);
                $prefix = sprintf(
                /* translators: %s: Taxonomy singular name. */
                    \_x('%s:', 'taxonomy term archive title prefix', 'default'),
                    Sanitize::metadata_content(static::get_label($term->taxonomy ?? '')),
                );
            }
        }

        return [$title, $prefix];
    }

    /**
     * Returns Post Title from ID.
     *
     * @NOTE Taken from WordPress core. Altered to work in the Admin area and when post_title is actually supported.
     * @param int|\WP_Post $id The Post ID or post object.
     * @return string The generated post title.
     * @see WP Core single_post_title()
     *
     * @since 5.0.0
     *
     */
    public static function get_post_title($id = 0)
    {

        // Blog queries can be tricky. Use get_the_real_id to be certain.
        $post = \get_post($id ?: Query::get_the_real_id());

        if (isset($post->post_title) && \post_type_supports($post->post_type, 'title')) {
            /**
             * Filters the page title for a single post.
             *
             * @param string $post_title The single post page title.
             * @param \WP_Post $post The current queried object as returned by get_queried_object().
             * @since WP Core 0.71
             *
             */
            $title = \apply_filters('single_post_title', $post->post_title, $post);
        }

        if (isset($title) && \strlen($title))
            return Sanitize::metadata_content($title);

        return '';
    }

    /**
     * Returns untitled title.
     *
     * @return string The untitled title.
     * @since 5.0.0
     *
     */
    public static function get_untitled_title()
    {
        // FIXME: WordPress no longer outputs 'Untitled' for the title.
        // Though, it still holds this translation in wp_widget_rss_output(), which isn't going anywhere.
        return \__('Untitled', 'default');
    }

    /**
     * Generates a title, based on current query, without additions or prefixes.
     *
     * @return string The generated title.
     * @since 5.0.0
     *
     */
    public static function generate_title_from_query()
    {

        if (Query::is_real_front_page()) {
            $title = static::get_front_page_title();
        } elseif (Query::is_singular()) {
            $title = static::get_post_title();
        } elseif (Query::is_archive()) {
            $title = static::get_archive_title();
        } elseif (Query::is_search()) {
            $title = static::get_search_query_title();
        } elseif (\is_404()) {
            $title = static::get_404_title();
        }

        return $title ?? '';
    }

    /**
     * Returns search title.
     *
     * @return string The generated search title.
     * @since 5.0.0
     *
     */
    public static function get_search_query_title()
    {
        return Sanitize::metadata_content(
        /* translators: %s: search phrase */
            sprintf(\__('Search Results for &#8220;%s&#8221;', 'default'), \get_search_query(true))
        );
    }

    /**
     * Returns 404 title.
     *
     * @return string The generated 404 title.
     * @since 5.0.0
     *
     */
    public static function get_404_title()
    {
        return Sanitize::metadata_content(
        /**
         * @param string $title The 404 title.
         * @since 5.0.0 Now defaults to Core translatable "Page not found."
         * @since 2.5.2
         */
            (string)\apply_filters(
                'the_seo_framework_404_title',
                \__('Page not found', 'default')
            )
        );
    }

    /**
     * Returns post type archive canonical URL without query adjustments.
     *
     * @since 5.0.0
     *
     * @param null|string $post_type The post type archive's post type.
     *                          Leave null to autodetermine query and allow pagination.
     * @return string The post type archive canonical URL, if any.
     */
    public static function get_bare_pta_url( $post_type = null ) {

        $url = \get_post_type_archive_link( $post_type ?? Query::get_current_post_type() );

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( $url ),
            [ 'https', 'http' ],
        );
    }


    /**
     * Returns singular permalink without query adjustments.
     *
     * @since 5.0.0
     *
     * @param int|null $post_id The post ID to get the URL from. Leave null to autodetermine.
     * @return string The singular canonical URL without complex optimizations.
     */
    public static function get_bare_singular_url( $post_id = null ) {

        $url = \get_permalink( $post_id );

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( $url ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Returns post type archive canonical URL.
     *
     * @since 5.0.0
     *
     * @param ?string $post_type The post type archive's post type.
     *                           Leave null to autodetermine query and allow pagination.
     * @return string The post type archive canonical URL, if any.
     */
    public static function get_pta_url( $post_type = null ) {

        if ( isset( $post_type ) )
            return static::get_bare_pta_url( $post_type );

        $url = \get_post_type_archive_link( $post_type ?? Query::get_current_post_type() );

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( static::add_pagination_to_url(
                $url,
                Query::paged(),
                true,
            ) ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Adds pagination to input URL.
     *
     * @since 4.2.3
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @param string $url      The fully qualified URL.
     * @param int    $page     The page number. Should be bigger than 1 to paginate.
     * @param bool   $use_base Whether to use pagination base.
     *                         If null, it will autodetermine.
     *                         Should be true on archives and the homepage (blog and static!).
     *                         False on singular post types.
     * @return string The fully qualified URL with pagination.
     */
    public static function add_pagination_to_url( $url, $page = null, $use_base = null ) {

        $page ??= max( Query::paged(), Query::page() );

        if ( $page < 2 )
            return $url;

        $use_base
            ??= Query::is_real_front_page()
            || Query::is_archive()
            || Query::is_singular_archive()
            || Query::is_search();

        if ( static::using_pretty_permalinks() ) {
            $_query = parse_url( $url, \PHP_URL_QUERY );

            // Remove queries, add them back later.
            if ( $_query )
                $url = strtok( $url, '?' );

            if ( $use_base ) {
                $url = \user_trailingslashit(
                    \trailingslashit( $url ) . "{$GLOBALS['wp_rewrite']->pagination_base}/$page",
                    'paged',
                );
            } else {
                $url = \user_trailingslashit( \trailingslashit( $url ) . $page, 'single_paged' );
            }

            if ( $_query )
                $url = static::append_query_to_url( $url, $_query );
        } else {
            if ( $use_base ) {
                $url = \add_query_arg( 'paged', $page, $url );
            } else {
                $url = \add_query_arg( 'page', $page, $url );
            }
        }

        return $url;
    }

    /**
     * Determines whether pretty permalinks are enabled.
     *
     * @since 5.0.0
     * @todo consider wp_force_plain_post_permalink()
     *
     * @return bool
     */
    public static function using_pretty_permalinks() {
        return static::memo() ?? static::memo( '' !== \get_option( 'permalink_structure' ) );
    }

    /**
     * Appends given query to given URL.
     *
     * This is a "dumb" replacement of WordPress's add_query_arg(), but much faster
     * and with more straightforward query and fragment handlers.
     *
     * @since 5.0.0
     *
     * @param string $url   A fully qualified URL.
     * @param string $query A fully qualified query taken from parse_url( $url, \PHP_URL_QUERY );
     * @return string A fully qualified URL with appended $query.
     */
    public static function append_query_to_url( $url, $query ) {

        if ( str_contains( $url, '#' ) ) {
            $fragment = strstr( $url, '#' );
            $url      = str_replace( $fragment, '', $url );
        } else {
            $fragment = '';
        }

        if ( str_contains( $url, '?' ) )
            return "$url&$query{$fragment}";

        return "$url?$query{$fragment}";
    }

    /**
     * Returns author canonical URL.
     *
     * @since 5.0.0
     *
     * @param ?int $id The author ID. Leave null to autodetermine.
     * @return string The author canonical URL, if any.
     */
    public static function get_author_url( $id = null ) {

        if ( isset( $id ) )
            return static::get_bare_author_url( $id );

        $url = \get_author_posts_url( Query::get_the_real_id() );

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( static::add_pagination_to_url(
                $url,
                Query::paged(),
                true,
            ) ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Returns author canonical URL without query adjustments.
     *
     * @since 5.0.0
     *
     * @param int|null $id The author ID. Leave null to autodetermine.
     * @return string The author canonical URL, if any.
     */
    public static function get_bare_author_url( $id = null ) {

        $url = \get_author_posts_url( $id ?? Query::get_the_real_id() );

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( $url ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Returns date canonical URL without query adjustments.
     *
     * @since 5.0.0
     *
     * @param int  $year  The year.
     * @param ?int $month The month.
     * @param ?int $day   The day.
     * @return string The date canonical URL, if any.
     */
    public static function get_bare_date_url( $year, $month = null, $day = null ) {

        if ( $day ) {
            $url = \get_day_link( $year, $month, $day );
        } elseif ( $month ) {
            $url = \get_month_link( $year, $month );
        } else {
            $url = \get_year_link( $year );
        }

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( $url ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Returns search canonical URL.
     * Automatically adds pagination if the input matches the query.
     *
     * @since 5.0.0
     *
     * @param string $search_query The search query. Mustn't be escaped.
     *                             When left empty, the current query will be used.
     * @return string The search canonical URL.
     */
    public static function get_search_url( $search_query = null ) {

        if ( isset( $search_query ) )
            return static::get_bare_search_url( $search_query );

        $url = \get_search_link();

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( static::add_pagination_to_url(
                $url,
                Query::paged(),
                true,
            ) ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Returns search canonical URL without query adjustments.
     *
     * @since 5.0.0
     *
     * @param string $search_query The search query. Mustn't be escaped.
     * @return string The date canonical URL, if any.
     */
    public static function get_bare_search_url( $search_query ) {

        $url = \get_search_link( $search_query );

        if ( empty( $url ) ) return '';

        return \sanitize_url(
            static::set_preferred_url_scheme( $url ),
            [ 'https', 'http' ],
        );
    }

    /**
     * Returns home URL without query adjustments.
     *
     * @since 5.0.0
     *
     * @return string The home URL.
     */
    public static function get_bare_front_page_url() {
        return static::umemo( __METHOD__ ) ?? static::umemo(
            __METHOD__,
            \sanitize_url(
                static::slash_front_page_url( static::set_preferred_url_scheme(
                    static::get_front_page_url(),
                ) ),
                [ 'https', 'http' ],
            ),
        );
    }

    /**
     * Slashes the root (home) URL.
     *
     * @since 5.0.0
     *
     * @param string $url The root URL.
     * @return string The root URL plausibly with added slashes.
     */
    public static function slash_front_page_url( $url ) {

        $parsed = parse_url( $url );

        // Don't slash the home URL if it's been modified by a (translation) plugin.
        if ( empty( $parsed['query'] ) ) {
            if ( isset( $parsed['path'] ) && '/' !== $parsed['path'] ) {
                // Paginated URL or subdirectory.
                $url = \user_trailingslashit( $url, 'home' );
            } else {
                $url = \trailingslashit( $url );
            }
        }

        return $url;
    }

    /**
     * Returns the home URL. Created because the WordPress method is slow for it
     * performs bad "set_url_scheme" calls. We rely on this method for some
     * plugins filter `home_url`.
     * Memoized.
     *
     * @since 5.0.0
     *
     * @return string The home URL.
     */
    public static function get_front_page_url() {
        return static::umemo( __METHOD__ ) ?? static::umemo( __METHOD__, \get_home_url() );
    }

    /**
     * Determines whether the blog page exists.
     * This is not always a "blog as page" -- for that, use `tsf()->query()->is_blog_as_page()`.
     *
     * @since 5.0.4
     *
     * @return bool
     */
    public static function has_blog_page() {
        return ! static::has_page_on_front() || \get_option( 'page_for_posts' );
    }

    /**
     * Returns a list of post types shared with the taxonomy.
     *
     * @since 4.0.0
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_post_types_from_taxonomy`.
     *
     * @param string $taxonomy Optional. The taxonomy to check. Defaults to current screen/query taxonomy.
     * @return array List of post types.
     */
    public static function get_post_types( $taxonomy = '' ) {

        $taxonomy = $taxonomy ?: Query::get_current_taxonomy();
        $tax      = $taxonomy ? \get_taxonomy( $taxonomy ) : null;

        return $tax->object_type ?? [];
    }

    /**
     * Determines whether a page or blog is on front.
     *
     * @since 2.6.0
     * @since 3.1.0 Removed caching.
     * @since 5.0.0 Moved from `\The_SEO_Framework\Load`.
     *
     * @return bool
     */
    public static function has_page_on_front() {
        return 'page' === \get_option( 'show_on_front' );
    }

    /**
     * Returns an array of hierarchical post types.
     *
     * @since 4.0.0
     * @since 4.1.0 Now gets hierarchical post types that don't support rewrite, as well.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_hierarchical_post_types`.
     *
     * @return string[] All public hierarchical post types.
     */
    public static function get_all_hierarchical() {
        return static::memo() ?? static::memo(
            \get_post_types(
                [
                    'hierarchical' => true,
                    'public'       => true,
                ],
                'names',
            )
        );
    }

    /**
     * Returns an array of nonhierarchical post types.
     *
     * @since 4.0.0
     * @since 4.1.0 Now gets non-hierarchical post types that don't support rewrite, as well.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `get_nonhierarchical_post_types`.
     *
     * @return array The public nonhierarchical post types.
     */
    public static function get_all_nonhierarchical() {
        return static::memo() ?? static::memo(
            \get_post_types(
                [
                    'hierarchical' => false,
                    'public'       => true,
                ],
                'names',
            )
        );
    }

    /**
     * Determines whether a page on front is actually assigned.
     *
     * @since 5.0.5
     *
     * @return bool
     */
    public static function has_assigned_page_on_front() {
        return static::has_page_on_front() && \get_option( 'page_on_front' );
    }

    /**
     * Fetches Post content.
     *
     * @since 5.0.0
     *
     * @param \WP_Post|int $post The Post or Post ID.
     * @return string The post content.
     */
    public static function get_content( $post = null ) {

        $post = \get_post( $post ?: Query::get_the_real_id() );

        // '0' is not deemed content. Return empty string for it's a slippery slope.
        // We only allow that for TSF's custom fields.
        return ! empty( $post->post_content ) && \post_type_supports( $post->post_type, 'editor' )
            ? $post->post_content
            : '';
    }

    /**
     * Returns preferred $url scheme.
     * Which can automatically be detected when not set, based on the site URL setting.
     * Memoizes the return value.
     *
     * @since 5.0.0
     *
     * @return string The preferred URl scheme.
     */
    public static function get_preferred_url_scheme() {

        // phpcs:ignore, WordPress.CodeAnalysis.AssignmentInCondition -- I know.
        if ( null !== $memo = static::memo() ) return $memo;

        // May be 'https', 'http', or 'automatic'.
        switch ( get_option( 'canonical_scheme' ) ) {
            case 'https':
                $scheme = 'https';
                break;
            case 'http':
                $scheme = 'http';
                break;
            case 'automatic':
            default:
                $scheme = static::detect_site_url_scheme();
        }

        /**
         * @since 2.8.0
         * @param string $scheme The current URL scheme.
         */
        return static::memo( (string) \apply_filters( 'the_seo_framework_preferred_url_scheme', $scheme ) );
    }

    /**
     * Detects site's URL scheme from site options.
     * Falls back to is_ssl() when the hom misconfigured via wp-config.php
     *
     * NOTE: Some (insecure, e.g. SP) implementations for the `WP_HOME` constant, where
     * the scheme is interpreted from the request, may cause this to be unreliable.
     * We're going to ignore those edge-cases; they're doing it wrong.
     *
     * However, should we output a notification? Or let them suffer until they use Monitor to find the issue for them?
     * Yea, Monitor's great for that. Gibe moni plos.
     *
     * @since 5.0.0
     *
     * @return string The detected URl scheme, lowercase.
     */
    public static function detect_site_url_scheme() {
        return strtolower( static::get_parsed_front_page_url()['scheme'] ?? (
        Query::is_ssl() ? 'https' : 'http'
        ) );
    }

    /**
     * Fetches the parsed home URL.
     * Memoizes the return value.
     *
     * @since 5.0.0
     *
     * @return string The home URL host.
     */
    public static function get_parsed_front_page_url() {
        return static::umemo( __METHOD__ )
            ?? static::umemo( __METHOD__, parse_url( static::get_front_page_url() ) );
    }

}
