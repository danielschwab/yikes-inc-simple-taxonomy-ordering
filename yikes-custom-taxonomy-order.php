<?php
/*
Plugin Name: YIKES Custom Taxonomy Order
Plugin URI: http://www.yikesinc.com
Description: Custom drag & drop taxonomy ordering.
Author: Evan Herman, Yikes Inc.
Version: 0.1
Author URI: http://www.YikesInc.com
Text Domain: yikes-custom-taxonomy-order
Domain Path: /languages
*/

/*  Copyright 2015  Yikes Inc  (email : info@yikesinc.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Only load class if it hasn't already been loaded
if ( ! class_exists( 'Yikes_Custom_Taxonomy_Order' ) ) {

	// Yep Yep!
	class Yikes_Custom_Taxonomy_Order {
		
		/*
		*	Main Constructor
		*/	
		function __construct() {
			// admin init
			add_action( 'admin_head', array( $this, 'yikes_custom_tax_order_admin_init' ) );
			// front end init
			add_action( 'init', array( $this, 'yikes_custom_tax_order_front_end_init' ) );
			// handle the AJAX request
			add_action( 'wp_ajax_update_taxonomy_order', array( $this, 'yikes_handle_ajax_request' ) );
		}
		
		/*
		*	Init and load the files as needed
		*	@since 0.1
		*/
		public function yikes_custom_tax_order_admin_init() {
			/* Admin Side Re-Order of Hierarchical Taxonomies */
			if( is_admin() ) {
				// get global screen data
				$screen = get_current_screen();
				// confirm $screen and $screen->base is set
				if( isset( $screen ) && isset( $screen->base ) ) {
					// confirm we're on the edit-tags page
					if( $screen->base == 'edit-tags' ) {
						// ensuere that our terms have a `tax_position` value set, so they display properly
						$this->yikes_ensure_terms_have_tax_position_value( $screen );
						// confirm that the tax_position arg is set and no orderby param has been set
						if( ! isset( $_GET['orderby'] ) && $this->is_taxonomy_position_enabled( $screen->taxonomy ) ) {
							// enqueue our scripts/styles
							$this->enqueue_scripts_and_styles();
							// ensure post types have tax_position set
							add_filter( 'admin_init', array( $this, 'yikes_ensure_tax_position_set' ) );
							// re-order the posts
							add_filter( 'terms_clauses', array( $this, 'yikes_alter_tax_order' ), 10, 3 );
						}
					}
				}
			}
		}
		
		/*
		*	Properly order the taxonomies on the front end
		*	@since 0.1
		*/
		public function yikes_custom_tax_order_front_end_init() {
			/* Front End Re-Order of Hierarchical Taxonomies */
			if( ! is_admin() ) {
				add_filter( 'terms_clauses', array( $this, 'yikes_alter_tax_order' ), 10, 3 );
			}
		}
		
		/*
		*	Enqueue any scripts/styles we need
		*	@since 0.1
		*/
		public function enqueue_scripts_and_styles() {
		
			// styles
			wp_enqueue_style( 'yikes-tax-drag-drop-styles', plugin_dir_url( __FILE__ ) . 'lib/css/yikes-tax-drag-drop.css' );
			
			// enqueue jquery ui drag and drop
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			
			// enqueue our custom script
			wp_enqueue_script( 'yikes-tax-drag-drop', plugin_dir_url(__FILE__) . 'lib/js/yikes-tax-drag-drop.js', array( 'jquery-ui-core', 'jquery-ui-sortable' ), true );
			wp_localize_script( 'yikes-tax-drag-drop', 'localized_data', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			) );
			
		}
		
		/*
		*	Make sure each taxonomy has some tax_position set in term meta
		*	if not, assign a value to 'tax_position' in wp_termmeta
		*/
		public function yikes_ensure_terms_have_tax_position_value( $screen ) {
			if( isset( $screen ) && isset( $screen->taxonomy ) ) {
				$terms = get_terms( $screen->taxonomy, array( 'hide_empty' => false ) );
				$x = 1;
				foreach( $terms as $term ) {
					if( ! get_term_meta( $term->term_id, 'tax_position', true ) ) {
						update_term_meta( $term->term_id, 'tax_position', $x );
						$x++;
					}
				}
			}
		}
		
		/*
		*	Re-Order the taxonomies based on the tax_position value
		*	@since 0.1
		*/
		public function yikes_alter_tax_order( $pieces, $taxonomies, $args ) {
			foreach( $taxonomies as $taxonomy ) {
				// confirm the tax is set to hierarchical -- else do not allow sorting
				if( $this->is_taxonomy_position_enabled( $taxonomy ) ) {
					global $wpdb;
					$pieces['join'] .= " INNER JOIN $wpdb->termmeta AS term_meta ON t.term_id = term_meta.term_id";
					$pieces['orderby'] = "ORDER BY term_meta.meta_value";
				}
			}
			return $pieces;
		}
			
		/* 
		*	Handle The AJAX Request 
		*	@since 0.1		
		*/
		public function yikes_handle_ajax_request() {
			$array_data = $_POST['updated_array'];
			foreach( $array_data as $taxonomy_data ) {
				update_term_meta( $taxonomy_data[0], 'tax_position', (int) ( $taxonomy_data[1] + 1 ) );
			}
			wp_die();
			exit;
		}
		
		/*
		*	Helper function to confirm 'tax_position' is set to true (allowing sorting of taxonomies)
		*	eg: For an example on how to enable tax_position/sorting for taxonomies, please see:
		*	@since 0.1
		*	@return true/false
		*/
		public function is_taxonomy_position_enabled( $taxonomy_name ) {
			// Confirm a taxonomy name was passed in
			if( ! $taxonomy_name ) {
				return false;
			}
			$tax_object = get_taxonomy( $taxonomy_name );
			if( $tax_object && is_object( $tax_object ) ) {
				if( $tax_object->tax_position ) {
					return true;
				} else {
					return false;
				}
			}
			return false;
		}
	
	}
	
}

// init
new Yikes_Custom_Taxonomy_Order;

?>