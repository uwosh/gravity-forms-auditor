<?php
/*
Plugin Name: Gravity Forms Auditor
Plugin URI: http://uwosh.edu/
Description: Upon request, this plugin returns a report of the configuration of all Gravity Forms on a multisite.
Author: Joseph Kerkhof
Author URI: https://twitter.com/musicaljoeker
Version: 0.1
Network: True
*/

add_action( 'network_admin_menu', 'gf_auditor_menu' );
function gf_auditor_menu() {
    add_menu_page( 'Form Auditor', 'Form Auditor', 'administrator', 'gravity-forms-auditor', 'gf_auditor', 'dashicons-clipboard' );
}

// The plugin menu page
function gf_auditor() {
    ?>
    <script type="text/javascript" >
	jQuery(document).ready(function($) {
		var data = {
			'action': 'run_report'//,
			// 'whatever': 1234
		};
        jQuery('#submit').click(function() {
            jQuery.post(ajaxurl, data, function(response) {
                console.log('Got this from the server: ' + response);
            });
        });
		
	});
	</script> 
    <h1>Gravity Forms Auditor</h1>
    <?php
    submit_button( 'Run Report' );
}

// Registering the run report AJAX call
add_action( 'wp_ajax_run_report', 'report_runner' );
function report_runner() {
	global $wpdb;
    // $whatever = intval( $_POST['whatever'] );
    $gf_form_meta_tables = get_gf_tables();
    $forms_metadata = array();
    for( $i=0; $i<count($gf_form_meta_tables); $i++ ) { // looping through each table
        if( table_exists($gf_form_meta_tables[$i]) ){
            $query = "SELECT form_id, display_meta FROM " . $gf_form_meta_tables[$i];
            $rows = $wpdb->get_results( $query );

            $site = array();
            $site_id = $i+1;
            // echo 'site_id: ' . $site_id;
            // Converting the JSON coming back from the DB to an array
            $forms = array();
            for( $j=0; $j<count( $rows ); $j++ ) {
                $form_id = $rows[$j]->form_id;
                $display_meta = $rows[$j]->display_meta;
                $form = array( "form_id"=>$form_id, "display_meta"=>$display_meta );
                array_push( $forms, $form ); // adding form into forms

                // echo 'form_id: ' . $form_id;
                // echo 'display_meta: ' . $display_meta;
                // echo 'Row: ' . print_r( $rows[$j] );
                // $rows[$j]->display_meta = json_decode( $rows[$j]->display_meta, true );
            }
            $site_forms = array( $site_id, $forms );
            echo 'site_forms: ' . json_encode( $site_forms );
            // array_unshift( $rows, $i+1 ); // adding the site id to the beginning of the array
            // array_push( $forms_metadata, $rows );
        }else {
            continue;
        }
    }

    // echo json_encode( $forms_metadata );

	wp_die(); // this is required to terminate immediately and return a proper response
}

// a function to check if the MySQL table exists
function table_exists( $table ) {
    global $wpdb;
    $result = $wpdb->get_results( "SHOW TABLES LIKE '" . $table . "'" );
    if(count($result) > 0) {
        return true;
    }else {
        return false;
    }
}

// Returns an array of table names for Gravity Forms metadata
function get_gf_tables() {
    global $wpdb;
    $num_sites = get_blog_count();
    $gf_form_meta_tables = array();
    for( $i=1; $i<=$num_sites; $i++ ) {
        if( $i==1 ) {
            $table_str = $wpdb->prefix . 'rg_form_meta';
            array_push($gf_form_meta_tables, $table_str);
        }else {
            $table_str = $wpdb->prefix . $i . '_' . 'rg_form_meta';
            array_push($gf_form_meta_tables, $table_str);
        }
    }
    return $gf_form_meta_tables;
}