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
    <script src="<?php echo plugins_url( "gravity-forms-auditor/js/moment.js" ); ?>"></script>
    <h1>Gravity Forms Auditor</h1>
    <?php
    global $wpdb;
    if( table_exists( $wpdb->prefix . "form_auditor" ) ){
        $query = "SELECT last_run FROM " . $wpdb->prefix . "form_auditor ORDER BY last_run DESC LIMIT 1";
        $result = $wpdb->get_results( $query );
        $last_run = $result[0]->last_run;
        ?>
        <p>Last report run: <span id="last-run"></span></p>
        <script>
        jQuery(document).ready(function($) {
            var lastRun = moment("<?php echo $last_run; ?>").format('MMMM Do YYYY, h:mm a');
            jQuery("#last-run").html(lastRun);
        });
        </script>
        <?php
    } else{
        ?>
        <p>The report has never been run.</p>
        <?php
    }
    submit_button( 'Run Report' );
}

// Registering the run report AJAX call
add_action( 'wp_ajax_run_report', 'report_runner' );
function report_runner() {
	global $wpdb;
    // $whatever = intval( $_POST['whatever'] );

    // getting the last forms dump
    $result = $wpdb->get_results( "SELECT forms_dump FROM " . $wpdb->prefix . "form_auditor ORDER BY last_run DESC LIMIT 1;" );
    $old_forms_json = $result[0]->forms_dump;
    $old_forms = json_decode( $old_forms_json, true );

    // getting the latest dump
    $new_forms = get_all_gf();
    $new_forms_json = json_encode( $new_forms );
    // inserting forms dump into DB
    $wpdb->query( $wpdb->prepare( 
        "INSERT INTO " . $wpdb->prefix . "form_auditor ( forms_dump ) VALUES ( %s )",
        $new_forms_json
    ) );

    $new_forms_flattened = flatten_display_meta( $new_forms );
    $old_forms_flattened = flatten_display_meta( $old_forms );
    // echo print_r( $old_forms_flattened );

    $diffs = array();
    for( $i=0; $i<count( $new_forms_flattened ); $i++ ){
        $new_site_id = $new_forms_flattened[$i]["site_id"];
        $new_site_forms = $new_forms_flattened[$i]["forms"];
        for( $j=0; $j<count( $new_site_forms ); $j++ ){
            $new_form_id = $new_site_forms[$j]["form_id"];
            $is_form_in_dump = is_form_in_dump( $new_site_id, $new_form_id, $old_forms_flattened );
            if( $is_form_in_dump[0] ){
                $new_display_meta = $new_site_forms[$j]["display_meta"];
                echo 'new_display_meta: ' . $new_display_meta . "\n\n\n";
                $old_display_meta = $old_forms_flattened[$is_form_in_dump[1]]["forms"][$is_form_in_dump[2]]["display_meta"];
                // echo 'is_form_in_dump[1]: ' . $is_form_in_dump[1] . "\n\n\n";
                // echo 'is_form_in_dump[2]: ' . $is_form_in_dump[2] . "\n\n\n";
                echo 'old_display_meta: ' . $old_display_meta . "\n\n\n";
                $diff = array_diff( $new_display_meta, $old_display_meta );
                // echo 'diff: ' . $diff . "\n\n\n";
                if( count( $diff )>0 ){
                    array_push( $diffs, array( "site_id"=>$new_site_id, "form_id"=>$new_form_id ) ); // adding the form to the array of differences
                }
            } else{
                array_push( $diffs, array( "site_id"=>$new_site_id, "form_id"=>$new_form_id ) ); // adding the form to the array of differences
            }
        }
    }

    // echo 'diffs: ' . print_r( $diffs );

	wp_die(); // this is required to terminate immediately and return a proper response
}

// returns a boolean if the form is in a dump based on site_id and form_id
function is_form_in_dump( $site_id, $form_id, $dump ){
    for( $i=0; $i<count( $dump ); $i++ ){
        if( $dump[$i]["site_id"]==$site_id ){
            $forms = $dump[$i]["forms"];
            for( $j=0; $j<count( $forms ); $j++ ){
                if( $forms[$j]["form_id"]==$form_id ){
                    return array( true, $i, $j) ; // found it
                }
            }
        }
    }
    return array( false ); // went thru all of the sites and forms and didn't find it
}

// flattens the display_meta part of the JSON dump
function flatten_display_meta( $arr_dump ) {
    for( $i=0; $i<count( $arr_dump ); $i++ ){
        $forms = $arr_dump[$i]["forms"];
        for( $j=0; $j<count( $forms ); $j++ ){
            $display_meta_arr = $forms[$j]["display_meta"];
            $display_meta_flattened = json_encode( $display_meta_arr ); // squish
            $forms[$j]["display_meta"] = $display_meta_flattened;
        }
        $arr_dump[$i]["forms"] = $forms;
    }
    return $arr_dump;
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