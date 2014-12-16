<?php
/*
Plugin Name: WP-CFM ACF
Plugin URI: http://level-level.com
Description: WP-CFM for Advanced Custom Fields groups
Version: 0.1.0
Author: Level Level
Author URI: http://level-level.com
License: GPLv2

Copyright 2014 Level Level

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
class LL_WP_CFM_CONFIG
{
	function __construct() {
		add_filter( 'wpcfm_configuration_items', array( &$this, 'acf_settings' ) );
	}

	function filter_acf_bundles($result, $bundle){
		$acf_prefix = 'acf_';

		if( !isset( $bundle['config'] ) ||
			empty( $bundle['config'])
		)
			return;

		foreach( $bundle['config'] as $group_name => $group_data){
			if(
				strlen($group_name) > 0 &&
				substr($group_name, 0, strlen($acf_prefix) ) === $acf_prefix &&
				isset( $group_data["key"])
			){
				$group = array(
							'post_title' => str_replace( $acf_prefix, '', $group_name),
							'post_name'  => $group_data["key"]
						 );
				$result[] = $group;
			}
		}

		return $result;
	}

	function acf_settings($items){

		$acf_prefix = 'acf_';

		$bundles = WPCFM()->helper->get_file_bundles();

		if( isset($bundles ) )
			$acf_groups_from_config = array_reduce($bundles, array( &$this, 'filter_acf_bundles') );

		// Load acf groups
		$acf_groups = $this->get_acf_groups();

		if( isset($acf_groups_from_config)){
			$acf_groups = array_merge( $acf_groups, $acf_groups_from_config );
		}

		// merge
		foreach( $acf_groups as $group){
			$group_title = $acf_prefix . sanitize_title_with_dashes($group['post_title']);

			$items[$group_title] = array(
				'value' => $this->acf_push($group['post_name']),
				'group' => 'ACF groups',
				'callback' => array(&$this, 'acf_pull')
			);

		}

		return $items;
	}

	function get_acf_groups(){
		global $wpdb;

		$acf_groups_sql = "SELECT post_title, post_name FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field-group' AND post_status = 'publish'";
		$acf_groups = $wpdb->get_results( $acf_groups_sql, ARRAY_A );


		return $acf_groups;
	}

	// Retrieve ACF groups
	function acf_pull($params){

		if( ! class_exists( 'acf_settings_export' ) )
			return;

		$field_group = $params['new_value'];

		if( $existing_group = acf_get_field_group($field_group['key']) ){
			$field_group['ID'] = $existing_group['ID'];
			$existing_fields = acf_get_fields( $existing_group );

			// Remove fields
			foreach( $existing_fields as $field){
				wp_delete_post( $field['ID'], true );
			}
		}

		// extract fields
		$fields = acf_extract_var($field_group, 'fields');


		// format fields
		$fields = acf_prepare_fields_for_import( $fields );


		// save field group
		$field_group = acf_update_field_group( $field_group );


		// add to ref
		$ref[ $field_group['key'] ] = $field_group['ID'];


		// add to order
		$order[ $field_group['ID'] ] = 0;


		// add fields
		foreach( $fields as $index => $field ) {

			// add parent
			if( empty($field['parent']) ) {

				$field['parent'] = $field_group['ID'];

			} elseif( isset($ref[ $field['parent'] ]) ) {

				$field['parent'] = $ref[ $field['parent'] ];

			}

			// add field menu_order
			if( !isset($order[ $field['parent'] ]) ) {

				$order[ $field['parent'] ] = 0;

			}

			$field['menu_order'] = $order[ $field['parent'] ];
			$order[ $field['parent'] ]++;

			// save field
			$field = acf_update_field( $field );


			// add to ref
			$ref[ $field['key'] ] = $field['ID'];

		}
	}

	function acf_push($group_id){

		if( ! class_exists( 'acf_settings_export' ) )
			return;

		// load field group
		$field_group = acf_get_field_group( $group_id );

		// validate field group
		if( empty($field_group) )
			return;

		// load fields
		$fields = acf_get_fields( $field_group );


		// prepare fields
		$fields = acf_prepare_fields_for_export( $fields );


		// add to field group
		$field_group['fields'] = $fields;


		// extract field group ID
		$id = acf_extract_var( $field_group, 'ID' );


		// add to json array
		return $field_group;
	}

};

new LL_WP_CFM_CONFIG;
