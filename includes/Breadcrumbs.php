<?php
/**
 * @package The_SEO_Framework\Classes\Meta
 * @subpackage The_SEO_Framework\Meta\Breadcrumb
 */

namespace BCFE;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
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
 * Holds getters for breadcrumbs output.
 *
 * @since 5.0.0
 * @access protected
 *         Use tsf()->breadcrumbs() instead.
 */
class Breadcrumbs
{

    /**
     * Returns a list of breadcrumbs by URL and name.
     *
     * @param array|null $args The query arguments. Accepts 'id', 'tax', 'pta', and 'uid'.
     *                         Leave null to autodetermine query.
     * @return array[] The breadcrumb list, sequential : int position {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @todo add extra parameter for $options; create (class?) constants for them.
     *       -> Is tsf()->breadcrumbs()::CONSTANT possible?
     *       -> Then, forward the options to a class variable, and build therefrom. Use as argument for memo().
     *       -> Requested features (for shortcode): Remove home, remove current page.
     *       -> Requested features (globally): Remove/show archive prefixes, hide PTA/terms, select home name, select SEO vs custom title (popular).
     *       -> Add generation args to every crumb; this way we can perform custom lookups for titles after the crumb is generated.
     *
     * @since 5.0.0
     * @todo consider wp_force_plain_post_permalink()
     */
    public static function get_breadcrumb_list($args = null)
    {

        if (isset($args)) {
            Helper::normalize_generation_args($args);
            $list = static::get_breadcrumb_list_from_args($args);
        } else {
            $list = Helper::memo() ?? Helper::memo(static::get_breadcrumb_list_from_query());
        }

        /**
         * @param array[] The breadcrumb list, sequential : int position => {
         *    string url:  The breadcrumb URL.
         *    string name: The breadcrumb page title.
         * }
         * @param array|null $args The query arguments. Contains 'id', 'tax', 'pta', and 'uid'.
         *                         Is null when the query is auto-determined.
         * @since 5.0.0
         */
        return (array)\apply_filters(
            'breadcrumbs_for_elementor_breadcrumb_list',
            $list,
            $args,
        );
    }

    /**
     * Gets a list of breadcrumbs, based on expected or current query.
     *
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_breadcrumb_list_from_query()
    {

        if (Query::is_real_front_page()) {
            $list = static::get_front_page_breadcrumb_list();
        } elseif (Query::is_singular()) {
            $list = static::get_singular_breadcrumb_list();
        } elseif (Query::is_archive()) {
            if (Query::is_editable_term()) {
                $list = static::get_term_breadcrumb_list();
            } elseif (\is_post_type_archive()) {
                $list = static::get_pta_breadcrumb_list();
            } elseif (Query::is_author()) {
                $list = static::get_author_breadcrumb_list();
            } elseif (\is_date()) {
                $list = static::get_date_breadcrumb_list();
            }
        } elseif (Query::is_search()) {
            $list = static::get_search_breadcrumb_list();
        } elseif (\is_404()) {
            $list = static::get_404_breadcrumb_list();
        }

        return $list ?? [];
    }

    /**
     * Gets a list of breadcrumbs, based on input arguments query.
     *
     * @param array $args The query arguments. Accepts 'id', 'tax', 'pta', and 'uid'.
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_breadcrumb_list_from_args($args)
    {

        if ($args['tax']) {
            $list = static::get_term_breadcrumb_list($args['id'], $args['tax']);
        } elseif ($args['pta']) {
            $list = static::get_pta_breadcrumb_list($args['pta']);
        } elseif ($args['uid']) {
            $list = static::get_author_breadcrumb_list($args['uid']);
        } elseif (Query::is_real_front_page_by_id($args['id'])) {
            $list = static::get_front_page_breadcrumb_list();
        } elseif ($args['id']) {
            $list = static::get_singular_breadcrumb_list($args['id']);
        }

        return $list ?? [];
    }

    /**
     * Gets a list of breadcrumbs for the front page.
     *
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_front_page_breadcrumb_list()
    {
        return [static::get_front_breadcrumb()];
    }

    /**
     * Gets a list of breadcrumbs for a singular object.
     *
     * @param ?int | \WP_Post $id The post ID or post object. Leave null to autodetermine.
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_singular_breadcrumb_list($id = null)
    {

        // Blog queries can be tricky. Use get_the_real_id to be certain.
        $post = \get_post($id ?? Query::get_the_real_id());

        if (empty($post))
            return [];

        $crumbs = [];
        $post_type = \get_post_type($post);

        // Get Post Type Archive, only if hierarchical.
        if (\get_post_type_object($post_type)->has_archive ?? false) {
            $crumbs[] = [
                'url' => Helper::get_bare_pta_url($post_type),
                'name' => Helper::get_bare_title(['pta' => $post_type]),
            ];
        }

        // Get Primary Term.
        $taxonomies = array_keys(array_filter(
            Helper::get_hierarchical('objects', $post_type),
            'is_taxonomy_viewable',
        ));
        $taxonomy = reset($taxonomies); // TODO make this an option; also which output they want to use.
        $primary_term_id = $taxonomy ? Helper::get_primary_term_id($post->ID, $taxonomy) : 0;

        // If there's no ID, then there's no term assigned.
        if ($primary_term_id) {
            $ancestors = \get_ancestors(
                $primary_term_id,
                $taxonomy,
                'taxonomy',
            );

            foreach (array_reverse($ancestors) as $ancestor_id) {
                $crumbs[] = [
                    'url' => Helper::get_bare_term_url($ancestor_id, $taxonomy),
                    'name' => Helper::get_bare_title([
                        'id' => $ancestor_id,
                        'tax' => $taxonomy,
                    ]),
                ];
            }

            $crumbs[] = [
                'url' => Helper::get_bare_term_url($primary_term_id, $taxonomy),
                'name' => Helper::get_bare_title([
                    'id' => $primary_term_id,
                    'tax' => $taxonomy,
                ]),
            ];
        }

        // get_post_ancestors() has no filter. get_ancestors() isn't used for posts in WP.
        foreach (array_reverse($post->ancestors) as $ancestor_id) {
            $crumbs[] = [
                'url' => Helper::get_bare_singular_url($ancestor_id),
                'name' => Helper::get_bare_title(['id' => $ancestor_id]),
            ];
        }

        if (isset($id)) {
            $crumbs[] = [
                'url' => Helper::get_bare_singular_url($post->ID),
                'name' => Helper::get_bare_title(['id' => $post->ID]),
            ];
        } else {
            $crumbs[] = [
                'url' => Helper::get_bare_singular_url(),
                'name' => Helper::get_bare_title(),
            ];
        }

        return [
            static::get_front_breadcrumb(),
            ...$crumbs,
        ];
    }

    /**
     * Gets a list of breadcrumbs for a term object.
     *
     * @param int|null $term_id The term ID.
     * @param string $taxonomy The taxonomy. Leave empty to autodetermine.
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_term_breadcrumb_list($term_id = null, $taxonomy = '')
    {

        $crumbs = [];

        if (isset($term_id)) {
            $taxonomy = $taxonomy ?: \get_term($term_id)->taxonomy ?? '';
            $ancestors = \get_ancestors($term_id, $taxonomy, 'taxonomy');

            foreach (array_reverse($ancestors) as $ancestor_id) {
                $crumbs[] = [
                    'url' => Helper::get_bare_term_url($ancestor_id, $taxonomy),
                    'name' => Helper::get_bare_title([
                        'id' => $ancestor_id,
                        'tax' => $taxonomy,
                    ]),
                ];
            }

            $crumbs[] = [
                'url' => Helper::get_bare_term_url($term_id, $taxonomy),
                'name' => Helper::get_bare_title([
                    'id' => $term_id,
                    'tax' => $taxonomy,
                ]),
            ];
        } else {
            $taxonomy = Query::get_current_taxonomy();
            $ancestors = \get_ancestors(Query::get_the_real_id(), $taxonomy, 'taxonomy');

            foreach (array_reverse($ancestors) as $ancestor_id) {
                $crumbs[] = [
                    'url' => Helper::get_bare_term_url($ancestor_id, $taxonomy),
                    'name' => Helper::get_bare_title([
                        'id' => $ancestor_id,
                        'tax' => $taxonomy,
                    ]),
                ];
            }

            $crumbs[] = [
                'url' => Helper::get_bare_term_url(),
                'name' => Helper::get_bare_title(),
            ];
        }

        return [
            static::get_front_breadcrumb(),
            ...$crumbs,
        ];
    }

    /**
     * Gets a list of breadcrumbs for an post type archive.
     *
     * @param ?string $post_type The post type archive's post type.
     *                           Leave null to autodetermine query and allow pagination.
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_pta_breadcrumb_list($post_type = null)
    {

        $crumbs = [];

        if (isset($post_type)) {
            $crumbs[] = [
                'url' => Helper::get_pta_url($post_type),
                'name' => Helper::get_bare_title(['pta' => $post_type]),
            ];
        } else {
            $crumbs[] = [
                'url' => Helper::get_bare_pta_url(),
                'name' => Helper::get_bare_title(),
            ];
        }

        return [
            static::get_front_breadcrumb(),
            ...$crumbs,
        ];
    }

    /**
     * Gets a list of breadcrumbs for an author archive.
     *
     * @param ?int $id The author ID. Leave null to autodetermine.
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_author_breadcrumb_list($id = null)
    {

        $crumbs = [];

        if (isset($id)) {
            $crumbs[] = [
                'url' => Helper::get_author_url($id),
                'name' => Helper::get_bare_title(['uid' => $id]),
            ];
        } else {
            $crumbs[] = [
                'url' => Helper::get_bare_author_url(),
                'name' => Helper::get_bare_title(),
            ];
        }

        return [
            static::get_front_breadcrumb(),
            ...$crumbs,
        ];
    }

    /**
     * Gets a list of breadcrumbs for a date archive.
     *
     * Unlike other breadcrumb trials, this one doesn't support custom queries.
     * This is because `Helper::get_bare_title()` accepts no custom date queries.
     *
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_date_breadcrumb_list()
    {
        return [
            static::get_front_breadcrumb(),
            [
                'url' => Helper::get_bare_date_url(
                    \get_query_var('year'),
                    \get_query_var('monthnum'),
                    \get_query_var('day'),
                ),
                'name' => Helper::get_bare_title(),
            ],
        ];
    }

    /**
     * Gets a list of breadcrumbs for a search query.
     *
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_search_breadcrumb_list()
    {
        return [
            static::get_front_breadcrumb(),
            [
                'url' => Helper::get_search_url(),
                'name' => Helper::get_search_query_title(), // discrepancy
            ],
        ];
    }

    /**
     * Gets a list of breadcrumbs for 404 page.
     *
     * @return array[] The breadcrumb list : {
     *    string url:  The breadcrumb URL. In this case, it's empty.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_404_breadcrumb_list()
    {
        return [
            static::get_front_breadcrumb(),
            [
                'url' => '',
                'name' => Helper::get_404_title(), // discrepancy
            ],
        ];
    }

    /**
     * Gets a single breadcrumb for the front page.
     *
     * @return array The frontpage breadcrumb : {
     *    string url:  The breadcrumb URL.
     *    string name: The breadcrumb page title.
     * }
     * @since 5.0.0
     *
     */
    private static function get_front_breadcrumb()
    {
        return [
            'url' => Helper::get_bare_front_page_url(),
            'name' => Helper::get_front_page_title(), // discrepancy
        ];
    }

}