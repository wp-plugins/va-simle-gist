<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*
Plugin Name: VA Simple Gist
Plugin URI: http://visualive.jp/
Description: This plugin rewrites the HTML to embed remote content based on a provided URL of Github Gist.
Author: KUCKLU
Version: 1.0.3
Author URI: http://visualive.jp/
Text Domain: va-simple-gist
Domain Path: /langs
License: GNU General Public License v3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

VisuAlive WordPress Plugin, Copyright (C) 2015 VisuAlive and KUCKLU.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/**
 * VA Simple Gist
 *
 * @package WordPress
 * @subpackage VA Simple Gist
 * @author KUCKLU <kuck1u@visualive.jp>
 * @copyright Copyright (c) 2015 KUCKLU, VisuAlive.
 * @license GPLv3 http://opensource.org/licenses/gpl-3.0.php
 * @link http://visualive.jp/
 */
$va_simple_gist_plugin_data = get_file_data( __FILE__, array( 'ver' => 'Version', 'langs' => 'Domain Path', 'mo' => 'Text Domain' ) );
define( 'VA_SIMPLE_GIST_PLUGIN_URL',  plugin_dir_url(__FILE__) );
define( 'VA_SIMPLE_GIST_PLUGIN_PATH', plugin_dir_path(__FILE__) );
define( 'VA_SIMPLE_GIST_DOMAIN',      dirname( plugin_basename(__FILE__) ) );
define( 'VA_SIMPLE_GIST_VERSION',     $va_simple_gist_plugin_data['ver'] );
define( 'VA_SIMPLE_GIST_TEXTDOMAIN',  $va_simple_gist_plugin_data['mo'] );


class VA_SIMPLE_GIST {
    /**
     * Holds the singleton instance of this class
     */
    static $instance = false;

    /**
     * Singleton
     * @static
     */
    public static function init() {
        if ( ! self::$instance ) {
            self::$instance = new VA_SIMPLE_GIST;
        }

        return self::$instance;
    }

    function __construct() {
        add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
    }

    public function plugins_loaded() {
        add_filter( 'the_content',        array( &$this, 'gist_url_to_replace' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts') );
    }

    /**
     * Gist url to replace.
     *
     * @param  string $content
     * @return string new $content
     */
    public function gist_url_to_replace( $content ) {
        $content_protect_pattern = '/<(a|pre|code|s(?:cript|tyle)|xmp)(?:\s*|(?:\s+[^>]+))>(.*?)<\/\\1\s*>/is';
        $content_replace_pattern = '#https?:\/\/gist\.github\.com\/([\w]+)\/([a-zA-Z0-9]+)?(\#file(\-|_)([-_.a-zA-Z0-9]+))?#i';
        $i                       = 0;
        $tmpName                 = "__TMP__";

        do {
            if ( !isset( $GLOBALS[$tmpName . $i] ) ) {
                $tmpName .= $i;
                break;
            }
            if ($i > 10) {
                $tmpName .= md5( mt_rand() ) . md5( mt_rand() );
                break;
            }
            $i++;
        } while ( true );

        $GLOBALS[$tmpName] = array();
        $content           = preg_replace_callback( $content_protect_pattern, create_function( '$matches', '$tmp =& $GLOBALS["' . $tmpName . '"]; $tmp[] = $matches[0];' . 'return "<\\x00," . count( $tmp ) . ",\\x01>";'), $content );
        $content           = preg_replace_callback( $content_replace_pattern, array( &$this, 'content_replace' ), $content );
        $content           = preg_replace_callback( "/<\\x00,(\d+),\\x01>/", create_function( '$matches', '$tmp =& $GLOBALS["' . $tmpName . '"];' . 'return $tmp[$matches[1] - 1];' ), $content );

        return $content;
    }

    /**
     * Content replace.
     *
     * @param  array $matches
     * @return string
     */
    public function content_replace( $matches ) {
        $base      = 'https://gist.github.com';
        $script    = sprintf( '%s/%s/%s.js', $base, $matches[1], $matches[2] );
        $extension = apply_filters( 'va-simple-gist/extension', array(
            'html', 'css', 'js', 'sass', 'scss', 'php', 'sql', 'conf', 'rb', 'sh'
        ) );

        if ( isset( $matches[5] ) ) {
            preg_match( '/(.*?)[\-\.]([a-z]+)\z/im', $matches[5], $file );

            if ( in_array( $file[2], $extension ) )
                $script = sprintf( '%s?file=%s.%s', $script, $file[1], $file[2] );
            else
                $script = sprintf( '%s?file=%s', $script, $matches[5] );
        }

        return '<script src="' . esc_url( $script ) . '"></script>';
    }

    public function wp_enqueue_scripts() {
        wp_enqueue_style( 'vp-simple-gist-style', VA_SIMPLE_GIST_PLUGIN_URL . 'assets/css/style.css' );
    }
}
$GLOBALS['VA_SIMPLE_GIST'] = VA_SIMPLE_GIST::init();
