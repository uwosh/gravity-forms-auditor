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

function gf_auditor_menu() {
    add_menu_page( 'Form Auditor', 'Form Auditor', 'administrator', 'gravity-forms-auditor', 'gf_auditor', 'dashicons-clipboard' );
}

add_action( 'network_admin_menu', 'gf_auditor_menu' );

// Registering an ajax action
function my_action() {
	global $wpdb; // this is how you get access to the database

	$whatever = intval( $_POST['whatever'] );

	$whatever += 10;

    echo $whatever;

	wp_die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_my_action', 'my_action' );

function gf_auditor() {
    ?>
    <script type="text/javascript" >
	jQuery(document).ready(function($) {

		var data = {
			'action': 'my_action',
			'whatever': 1234
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery('#submit').click(function() {
            console.log('#submit clicked');
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