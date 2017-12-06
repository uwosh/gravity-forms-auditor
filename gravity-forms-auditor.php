/*
Plugin Name: Gravity Forms Auditor
Plugin URI: http://uwosh.edu/
Description: Upon request, returns a report of the configuration of all Gravity Forms on a multisite.
Author: Joseph Kerkhof
Author URI: https://twitter.com/musicaljoeker
Version: 0.1
Network: True
*/

<?php

function gf_auditor_menu() {
    add_menu_page( 'Gravity Forms Auditor', 'Gravity Forms Auditor', 'administrator', 'gravity-forms-auditor' );
}

add_action( 'network_admin_menu', 'gf_auditor_menu' );