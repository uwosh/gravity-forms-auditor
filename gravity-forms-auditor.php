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

register_activation_hook( __FILE__, 'create_gf_auditor_table' );
function create_gf_auditor_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . "form_auditor";
    $charset_collate = $wpdb->get_charset_collate();
    if( !table_exists( $table_name ) ){
        $query = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            last_run TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            forms_dump longtext NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $query );
    }
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
    $all_forms = get_all_gf();
    $all_forms_json = json_encode( $all_forms );
    $wpdb->query( $wpdb->prepare( 
        "INSERT INTO " . $wpdb->prefix . "form_auditor ( forms_dump ) VALUES ( %s )",
        $all_forms_json
    ) );
    echo 'before DB all_forms_json: ' . $all_forms_json . "\n\n\n";

    $result = $wpdb->get_results( "SELECT forms_dump FROM " . $wpdb->prefix . "form_auditor ORDER BY last_run DESC LIMIT 1;" );
    echo 'after DB all_forms_json: ' . $result->forms_dump;

	wp_die(); // this is required to terminate immediately and return a proper response
}

// a function that queries the $wpdb and returns all Gravity Forms data on the multisite
function get_all_gf() {
    global $wpdb;
    $gf_form_meta_tables = get_gf_tables();
    $all_forms = array();
    for( $i=0; $i<count($gf_form_meta_tables); $i++ ) { // looping through each table
        if( table_exists($gf_form_meta_tables[$i]) ){
            $query = "SELECT form_id, display_meta FROM " . $gf_form_meta_tables[$i];
            $rows = $wpdb->get_results( $query );
            $site = array();
            $site_id = $i+1;
            $site_descriptors = get_site_descriptors( $site_id );
            $site_name = $site_descriptors["blogname"];
            $admin_email = $site_descriptors["admin_email"];
            // Converting the JSON coming back from the DB to an array
            $forms = array();
            for( $j=0; $j<count( $rows ); $j++ ) {
                $form_id = $rows[$j]->form_id;
                $display_meta = json_decode( $rows[$j]->display_meta, true );
                $permalinks = get_pages_with_gf( $site_id, $form_id );
                $form = array( "form_id"=>$form_id, "display_meta"=>$display_meta, "permalinks"=>$permalinks );
                array_push( $forms, $form ); // adding form into forms
            }
            $site_forms = array( "site_id"=>$site_id, "site_name"=>$site_name, "admin_email"=>$admin_email, "forms"=>$forms );
            array_push( $all_forms, $site_forms );
        }else {
            continue;
        }
    }
    return $all_forms;
}

function get_site_descriptors( $site_id ) {
    global $wpdb;
    if( $site_id==1 ) {
        $blogname_query = 'SELECT option_value FROM ' . $wpdb->prefix . 'options WHERE option_name=\'blogname\'';
        $admin_email_query = 'SELECT option_value FROM ' . $wpdb->prefix . 'options WHERE option_name=\'admin_email\'';
    } else{
        $blogname_query = 'SELECT option_value FROM ' . $wpdb->prefix . $site_id . '_options WHERE option_name=\'blogname\'';
        $admin_email_query = 'SELECT option_value FROM ' . $wpdb->prefix . $site_id . '_options WHERE option_name=\'admin_email\'';
    }
    $blogname_result = $wpdb->get_results( $blogname_query );
    $admin_email_result = $wpdb->get_results( $admin_email_query );
    $blogname = $blogname_result[0]->option_value;
    $admin_email = $admin_email_result[0]->option_value;
    return array( "blogname"=>$blogname, "admin_email"=>$admin_email );
}

// a function that returns the permalinks where the form is found on the page 
function get_pages_with_gf( $site_id, $form_id ) {
    global $wpdb;
    if( $site_id==1 ) {
        $query = 'SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE \'%[gravityform id="' . $form_id . '"%\' AND post_type <> \'revision\'';
    } else{
        $query = 'SELECT ID FROM ' . $wpdb->prefix . $site_id . '_posts WHERE post_content LIKE \'%[gravityform id="' . $form_id . '"%\' AND post_type <> \'revision\'';
    }
    $result = $wpdb->get_results( $query );
    $permalinks = array();
    for( $i=0; $i<count( $result ); $i++ ) {
        array_push( $permalinks, get_permalink( $result[$i]->ID ) );
    }
    return $permalinks;
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