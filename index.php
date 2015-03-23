<?php
/*
Plugin Name: CFS - Translatable
Plugin URI: https://github.com/leolweb/cfs-translatable/
Description: Add gettext and shortcode translation support for Custom Field Suite.
Version: 0.1
Author: Leonardo Laureti
Author URI: http://leolweb.com
License: GPLv2

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/>.
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class cfs_translatable_addon
{
	private $language;
	private $shortcodes;
	private $textdomain;
	private $pot;
	private $pot_language;
	private $pot_filename;
	private $pot_path;
	private $php;
	private $php_language;
	private $php_filename;
	private $php_path;

	function __construct() {
		/* cfg: CFS_TRANSLATABLE_SHORTCODES
		   constant - bool - Default: false */
		if ( ! defined( 'CFS_TRANSLATABLE_SHORTCODES' ) )
			define( 'CFS_TRANSLATABLE_SHORTCODES', false );

		/* cfg: CFS_TRANSLATABLE_TEXTDOMAIN
		   constant - string - Default: '' */
		if ( ! defined( 'CFS_TRANSLATABLE_TEXTDOMAIN' ) )
			define( 'CFS_TRANSLATABLE_TEXTDOMAIN', '' );

		/* cfg: CFS_TRANSLATABLE_DEFAULT_LANGUAGE
		   constant - string - Default: 'en' */
		if ( ! defined( 'CFS_TRANSLATABLE_DEFAULT_LANGUAGE' ) )
			define( 'CFS_TRANSLATABLE_DEFAULT_LANGUAGE', 'en' );

		/* cfg: CFS_TRANSLATABLE_GENERATE_POT
		   constant - bool - Default: false */
		if ( ! defined( 'CFS_TRANSLATABLE_GENERATE_POT' ) )
			define( 'CFS_TRANSLATABLE_GENERATE_POT', false );

		/* cfg: CFS_TRANSLATABLE_GENERATE_PHP
		   constant - bool - Default: false */
		if ( ! defined( 'CFS_TRANSLATABLE_GENERATE_PHP' ) )
			define( 'CFS_TRANSLATABLE_GENERATE_PHP', false );

		/* cfg: CFS_TRANSLATABLE_POT_LANGUAGE
		   constant - string - Default: 'en_US' */
		if ( ! defined( 'CFS_TRANSLATABLE_POT_LANGUAGE' ) )
			define( 'CFS_TRANSLATABLE_POT_LANGUAGE', 'en_US' );

		/* cfg: CFS_TRANSLATABLE_PATH
		   constant - string - Default: __DIR__ */
		if ( ! defined( 'CFS_TRANSLATABLE_PATH' ) )
			define( 'CFS_TRANSLATABLE_PATH', __DIR__ );

		/* cfg: CFS_TRANSLATABLE_FILENAME
		   constant - string - Default: 'cfs_translatable' */
		if ( ! defined( 'CFS_TRANSLATABLE_FILENAME' ) )
			define( 'CFS_TRANSLATABLE_FILENAME', 'cfs_translatable' );

		$this->language = CFS_TRANSLATABLE_DEFAULT_LANGUAGE;
		$this->shortcodes = CFS_TRANSLATABLE_SHORTCODES;
		$this->textdomain = CFS_TRANSLATABLE_TEXTDOMAIN;
		$this->pot = CFS_TRANSLATABLE_GENERATE_POT;
		$this->php = CFS_TRANSLATABLE_GENERATE_PHP;

		// Fire initialize after admin_init to get_current_screen() Fn
		add_action( 'current_screen',  array( $this, 'init' ) );

		// If generate pot and php if disabled skip some hooks
		if ( ! $this->pot && ! $this->php )
			return;

		$this->fields = array();

		$this->pot_language = CFS_TRANSLATABLE_POT_LANGUAGE;
		$this->pot_filename = CFS_TRANSLATABLE_FILENAME . '.pot';
		$this->pot_path = trailingslashit( CFS_TRANSLATABLE_PATH ) . $this->pot_filename;

		$this->php_filename = CFS_TRANSLATABLE__FILENAME . '_strings.php';
		$this->php_path = trailingslashit( CFS_TRANSLATABLE_PATH ) . $this->php_filename;

		// Add Fn parse_field() to filter 'cfs_translatable_parse_fields'
		add_filter( 'cfs_translatable_parse_fields', array( $this, 'parse_fields' ) );
		// Add Fn sanatize_field() to filter 'cfs_translatable_sanatize_fields'
		add_filter( 'cfs_translatable_sanatize_fields', array( $this, 'sanatize_fields' ) );

		// Add Fn generate() to action 'cfs_init' to generate pot and php
		add_action( 'cfs_init', array( $this, 'generate' ) );
	}

	/**
	 * Initialize
	 */
	public function init() {
		$screen = get_current_screen();

		// Apply filters to fields only outside CFS
		if ( 'cfs' == $screen->post_type )
			return;

		// If is enabled shortcodes
		// Add Fn set_languages() to action 'cfs_translatable_language' and fire up
		// Add Fn shortcode() to filter 'cfs_translatable_shortcode'
		if ( $this->shortcodes ) :
			add_action( 'cfs_translatable_language', array( $this, 'set_languages' ) );
			add_filter( 'cfs_translatable_shortcode', array( $this, 'shortcode' ) );

			// fire!
			do_action( 'cfs_translatable_language' );
		endif;

		// Add filters 'cfs_matching_groups' and 'cfs_get_input_fields' to CFS api
		add_filter( 'cfs_matching_groups', array( $this, 'translate_metabox_title' ) );
		add_filter( 'cfs_get_input_fields', array( $this, 'translate_input_fields' ) );
	}

	/**
	 * Retrieve all ids, export all fields with cfs_field_group->export() and parse them
	 *
	 * @return array $field_groups
	 */
	private function get_fields() {
		global $wpdb, $cfs;

		// Retrieve all ids, order by post_title
		$sql = "
		SELECT ID
		FROM $wpdb->posts
		WHERE post_type = 'cfs' AND post_status = 'publish'
		ORDER BY post_title";

		$results = $wpdb->get_results( $sql );
		$options = array();

		// Collect all ids
		foreach ( $results as $result )
			$options['field_groups'][] = $result->ID;

		// Apply filter 'cfs_translatable_export_options' for overrides
		$options = apply_filters( 'cfs_translatable_export_options', $options );
		$field_groups = $cfs->field_group->export( $options );

		// Return and apply filters 'cfs_translatable_parse_fields' for override
		return apply_filters( 'cfs_translatable_parse_fields', $field_groups );
	}

	/**
	 * Default filter 'cfs_translatable_parse_fields'
	 * Parse all fields then sanatize them
	 *
	 * @param array $field_groups
	 * @return array $fields
	 */
	public function parse_fields( $field_groups ) {
		$fields = array();

		//var_dump( $field_groups );

		foreach( $field_groups as $id => $group ) {
			// title
			$fields[] = addslashes( $group['post_title'] );

			// enter in cfs_fields
			foreach( $group['cfs_fields'] as $field ) {
				$fields[] = addslashes( $field['label'] );

				// notes
				if ( ! empty( $field['notes'] ) ) {
					$fields[] = addslashes( $field['notes'] );
				}

				// options
				if ( is_array( $field['options'] ) ) {
					// message
					if ( ! empty( $field['options']['message'] ) )
						$fields[] = addslashes( $field['options']['message'] );

					// row_label
					if ( ! empty( $field['options']['row_label'] ) )
						$fields[] = addslashes( $field['options']['row_label'] );

					// button_label
					if ( ! empty( $field['options']['button_label'] ) )
						$fields[] = addslashes( $field['options']['button_label'] );
				}
			}
		}

		//var_dump( $fields );

		// Apply filter 'cfs_translatable_sanatize_fields' for overrides
		return apply_filters( 'cfs_translatable_sanatize_fields', $fields );
	}

	/**
	 * Default filter 'cfs_translatable_sanatize_fields'
	 * Remove empty fields and made values unique
	 *
	 * @param array $fields
	 * @return array $fields
	 */
	public function sanatize_fields( $fields ) {
		$fields = array_filter( $fields );
		$fields = array_unique( $fields );

		return $fields;
	}

	/**
	 * Replace CFS metabox title
	 *
	 * @param array $matches
	 * @return array $matches
	 */
	public function translate_metabox_title( $matches ) {
		foreach( $matches as $id => $value ) {
			$matches[$id] = $this->proxy( $value );
		}

		// Apply filter 'cfs_translatable_translate_metabox_title' for overrides
		return apply_filters( 'cfs_translatable_translate_metabox_title', $matches );
	}

	/**
	 * Replace CFS metabox title
	 *
	 * @param array $fields
	 * @return array $fields
	 */
	public function translate_input_fields( $fields ) {
		foreach( $fields as $field ) {
			$field->label = $this->proxy( $field->label );

			if ( ! empty( $field->notes ) )
				$field->notes = $this->proxy( $field->notes );

			if ( is_array( $field->options ) ) {
				if ( ! empty( $field->options['message'] ) )
					$field->options['message'] =
						$this->proxy( $field->options['message'] );

				if ( ! empty( $field->options['row_label'] ) )
					$field->options['row_label'] =
						$this->proxy( $field->options['row_label'] );

				if ( ! empty( $field->options['button_label'] ) )
					$field->options['button_label'] =
						$this->proxy( $field->options['button_label'] );
			}
		}

		//var_dump( $fields );

		// Apply filter 'cfs_translatable_translate_input_fields' for overrides
		return apply_filters( 'cfs_translatable_translate_input_fields', $fields );
	}

	/**
	 * Default action to set languages
	 * locale = ISO 639-1
	 * locale_iso = ISO 639-2
	 * locale_fallback = CFS_TRANSLATABLE_DEFAULT_LANGUAGE
	 *
	 * @return stdClass $this->lang
	 */
	public function set_languages() {
		$this->lang = new stdClass;
		$this->lang->locale = get_bloginfo( 'language' );
		$this->lang->locale_iso = substr( $this->lang->locale, 0, 2 );
		$this->lang->locale_fallback = $this->language;

		return $this->lang;
	}

	/**
	 * Default shortcode
	 * Find all occurrences, language inside brackets and content between
	 *
	 * Format: [language]content[/language]
	 *
	 * @return stdClass $this->lang
	 */
	public function shortcode( $text ) {
		$lang = $this->lang;

		//var_dump( $lang );

		if ( preg_match_all( '/\\[(.+?)\\](.+?)\\[\\/(.+?)\\]/', $text, $matches ) ) {
			$contents = array_combine( $matches[1], $matches[2] );

			//var_dump( $contents );

			if ( isset( $contents[$lang->locale_iso] ) ) :
				return $contents[$lang->locale_iso];
			elseif ( isset( $contents[$lang->locale] ) ) :
				return $contents[$lang->locale];
			elseif ( isset( $contents[$lang->locale_fallback] ) ) :
				return $contents[$lang->locale_fallback];
			else :
				return $text;
			endif;
		}

		return $text;
	}

	/**
	 * Fn to proxy passed text string(s)
	 * Switch between shortcode and gettext
	 *
	 * @param string $text
	 * @return string $text
	 */
	private function proxy( $text ) {
		if ( $this->shortcodes )
			// Apply filter 'cfs_translatable_shortcode' for overrides
			return apply_filters( 'cfs_translatable_shortcode', $text );

		return __( $text, $this->textdomain );
	}

	/**
	 * Get default pot header
	 *
	 * @return string $pot_header
	 */
	private function get_pot_header() {
		$pot_date = date( 'Y-m-d H:iP' );
		$pot_lang = $this->language;

		$pot_header  = "msgid \"\"\nmsgstr \"\"\n";
		$pot_header .= "\"POT-Creation-Date: $pot_date\\n\"\n";
		$pot_header .= "\"Language: $pot_lang\\n\"\n";
		$pot_header .= "\"MIME-Version: 1.0\\n\"\n";
		$pot_header .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
		$pot_header .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
		$pot_header .= "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n";

		// Apply filter 'cfs_translatable_pot_header' for overrides
		return apply_filters( 'cfs_translatable_pot_header', $pot_header );
	}

	/**
	 * Prepare fields to export in pot and/or php
	 * Store fields in $pot_fields and $pho_fields
	 *
	 * @return bool
	 */
	private function prepare_fields() {
		$fields = $this->get_fields();
		$textdomain = $this->textdomain;
		$php_filename = $this->php_filename;

		if ( count( $fields ) === 0 )
			return false;

		$this->pot_fields = '';
		$this->php_fields = '';

		$i = 1;

		foreach ( $fields as $field ) :
			$this->pot_fields .= "\n\n";
			$this->pot_fields .= "#. cfs_translatable\n";

			if ( $this->php ) :
				$i++;
				$this->pot_fields .= "#: $php_filename:$i\n";
				$this->php_fields .= "__( '$field', '$textdomain' );\n";
			endif;

			$this->pot_fields .= "msgid \"$field\"\n";
			$this->pot_fields .= "msgstr \"\"";
		endforeach;

		return true;	
	}

	/**
	 * Generate pot and/or php
	 *
	 * @return bool
	 */
	private function generate() {
		// Prepare fields
		if( ! $this->prepare_fields() )
			return false;

		// Generate pot
		if ( $this->pot )
			$handle = $this->generate_pot();

		// Generate php
		if ( $this->php )
			$handle = $this->generate_php();

		return $handle;
	}

	/**
	 * Generate pot
	 *
	 * @return bool
	 */
	private function generate_pot() {
		$pot_header = $this->get_pot_header();
		$pot_fields = apply_filters( 'cfs_translatable_pot_fields', $this->pot_fields );

		// Add action 'cfs_translate_generate_pot'
		do_action( 'cfs_translatable_generate_pot' );

		return $this->save_file( $this->pot_path, $pot_header, $pot_fields );
	}

	/**
	 * Generate php
	 *
	 * @return bool
	 */
	private function generate_php() {
		$php_header = "<?php\n";
		$php_fields = apply_filters( 'cfs_translatable_php_fields', $this->php_fields );

		// Add action 'cfs_translate_generate_php'
		do_action( 'cfs_translatable_generate_php' );

		return $this->save_file( $this->php_path, $php_header, $php_fields );
	}

	/**
	 * Save a file from passed header and fields
	 *
	 * @param string $path
	 * @param string $header
	 * @param string $fields
	 * @return bool
	 */
	private function save_file( $path, $header, $fields ) {
		// Assembly $header + $fields
		$content = $header . $fields;

		// Add action 'cfs_translate_save_file'
		do_action( 'cfs_translatable_save_file' );

		if ( empty( $content ) )
			return false;

		// Open stream
		if ( ! $fp = fopen( $path, 'w' ) )
			die( sprintf( 'Cannot open file (%s)', $path ) );

		// Save file
		if ( fwrite( $fp, $content ) === false )
			die( sprintf( 'Cannot write to file (%s)', $path ) );

		// Close stream
		fclose( $fp );

		return true;
	}
}

new cfs_translatable_addon();