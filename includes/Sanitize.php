<?php
/**
 * @package The_SEO_Framework\Classes\Data\Filter\Sanitize
 * @subpackage The_SEO_Framework\Data\Filter
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
 * Holds a collection of data sanitization methods.
 *
 * @since 5.0.0
 * @access protected
 *         Use tsf()->filter()->sanitize() instead.
 */
class Sanitize {

    /**
     * Sanitizes metadata content.
     * Returns single-line, trimmed text without duplicated spaces, nbsp, or tabs.
     * Also converts back-solidi to their respective HTML entities for non-destructive handling.
     * Also adds a capital P, dangit.
     * Finally, it texturizes the content.
     *
     * @since 5.0.0
     *
     * @param string $text The text.
     * @return string One line sanitized text.
     */
    public static function metadata_content( $text ) {

        if ( ! \is_scalar( $text ) || ! \strlen( $text ) ) return '';

        return \wptexturize(
            \capital_P_dangit(
                static::backward_solidus_to_entity(
                    static::lone_hyphen_to_entity(
                        static::remove_repeated_spacing(
                            trim(
                                static::tab_to_space(
                                    static::newline_to_space(
                                        static::nbsp_to_space(
                                            (string) $text,
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Sanitizes text by removing repeated spaces.
     *
     * @since 2.8.2
     * @since 2.9.4 Now no longer fails when first two characters are spaces.
     * @since 3.1.0 1. Now also catches non-breaking spaces.
     *              2. Now uses a regex pattern.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `s_dupe_space`.
     *              3. Now replaces the spaces with the original spacing type, instead of only \u20.
     *
     * @param string $text The input text with possible repeated spacing.
     * @return string The input string without repeated spaces.
     */
    public static function remove_repeated_spacing( $text ) {
        return preg_replace_callback(
            '/(\p{Zs}){2,}/u',
            // Unicode support sans mb_*: Calculate the bytes of the match and then remove that length.
            static fn( $matches ) => substr( $matches[1], 0, \strlen( $matches[1] ) ),
            $text,
        );
    }

    /**
     * Replaces non-transformative hyphens with entity hyphens.
     * Duplicated simple hyphens are preserved.
     *
     * Regex challenge, make the columns without an x light up:
     * xxx - xx - xxx- - - xxxxxx xxxxxx- xxxxx - -
     * --- - -- - ---- - - ------ ------- ----- - -
     *
     * The answer? `/((-{2,3})(*SKIP)-|-)(?(2)(*FAIL))/`
     * Sybre-kamisama desu.
     *
     * @since 4.0.5
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `s_hyphen`.
     *
     * @param string $text String with potential hyphens.
     * @return string A string with safe HTML encoded hyphens.
     */
    public static function lone_hyphen_to_entity( $text ) {
        // str_replace is faster than putting these alternative sequences in the `-|-` regex below.
        // That'd be this: "/((?'h'-|&\#45;|\xe2\x80\x90){2,3}(*SKIP)(?&h)|(?&h))(?(h)(*FAIL))/u"
        return str_replace(
            [ '&#45;', "\xe2\x80\x90" ], // Should we consider &#000...00045;?
            '&#x2d;',
            preg_replace( '/((-{2,3})(*SKIP)-|-)(?(2)(*FAIL))/', '&#x2d;', $text ),
        );
    }

    /**
     * Replaces non-break spaces with regular spaces.
     *
     * This addresses a quirk in TinyMCE, where paragraph newlines are populated with nbsp.
     * TODO: Perhaps we should address that quirk directly, instead of removing indiscriminately.
     *       e.g., like `strip_newline_urls` and `strip_paragraph_urls`.
     *
     * @since 2.8.2
     * @since 3.1.0 Now catches all non-breaking characters.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `s_nbsp`.
     *
     * @param string $text String with potentially unwanted nbsp values.
     * @return string A spacey string.
     */
    public static function nbsp_to_space( $text ) {
        return str_replace( [ '&nbsp;', '&#160;', '&#xA0;', "\xc2\xa0" ], ' ', $text );
    }

    /**
     * Replaces backslash with entity backslash.
     *
     * @since 2.8.2
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `s_bsol`.
     *              3. No longer removes backslashes.
     *
     * @param string $text String with potentially unwanted \ values.
     * @return string A string with safe HTML encoded backslashes.
     */
    public static function backward_solidus_to_entity( $text ) {
        return str_replace( '\\', '&#92;', $text );
    }

    /**
     * Converts multilines to single lines.
     *
     * @since 2.8.2
     * @since 3.1.0 Simplified method.
     * @since 4.1.0 1. Made this method about 25~92% faster (more replacements = more faster). 73% slower on empty strings (negligible).
     *              2. Now also strips form-feed and vertical whitespace characters--might they appear in the wild.
     *              3. Now also strips horizontal tabs (reverted in 4.1.1).
     * @since 4.1.1 1. Now uses real bytes, instead of sequences (causing uneven transformations, plausibly emptying content).
     *              2. No longer transforms horizontal tabs. Use `s_tabs()` instead.
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `s_singleline`.
     * @link https://www.php.net/manual/en/regexp.reference.escape.php
     *
     * @param string $text The input value with possible multiline.
     * @return string The input string without multiple lines.
     */
    public static function newline_to_space( $text ) {
        // Use x20 because it's a human-visible real space.
        return trim(
            strtr( $text, "\x0A\x0B\x0C\x0D", "\x20\x20\x20\x20" ),
        );
    }

    /**
     * Removes tabs and replaces it with spaces.
     *
     * @since 2.8.2
     * @since 4.1.1 Now uses real bytes, instead of sequences (causing uneven transformations, plausibly emptying content).
     * @since 5.0.0 1. Moved from `\The_SEO_Framework\Load`.
     *              2. Renamed from `s_tabs`.
     * @link https://www.php.net/manual/en/regexp.reference.escape.php
     *
     * @param string $text The input value with possible tabs.
     * @return string The input string without tabs.
     */
    public static function tab_to_space( $text ) {
        // Use x20 because it's a human-visible real space.
        return strtr( $text, "\x09", "\x20" );
    }
}
