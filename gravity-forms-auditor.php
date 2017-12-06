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
    echo json_encode($gf_form_meta_tables);

	wp_die(); // this is required to terminate immediately and return a proper response
}

function get_gf_tables() {
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