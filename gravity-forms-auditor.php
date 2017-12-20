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
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            forms_dump longtext NOT NULL,
            file_name VARCHAR(50) NOT NULL,
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
		var run_difference_report = {
			'action': 'run_report'//,
			// 'whatever': 1234
		};
        var run_full_report = {
			'action': 'run_full_report'
		};
        jQuery('#submit').click(function() {
            jQuery.post(ajaxurl, run_difference_report, function(response) {
                console.log('Got this from the server: ' + response);
                
                // sending the user to fetch the report
                // window.location.href = response;
            });
        });
		jQuery('#download-old-report').click(function() {
            var filename = jQuery('#select-old-report').find(":selected").val();
            if (filename != "select-a-report") { // checks to make sure that you didn't try to download the "Select a Report" option
                window.location.href = "<?php echo wp_upload_dir()["baseurl"] . "/gf-audits/" ?>" + filename;
            }
        });
        jQuery('#run-full-report').click(function() {
            jQuery.post(ajaxurl, run_full_report, function(response) {
                console.log('Got this from the server: ' + response);
                
                // sending the user to fetch the report
                // window.location.href = response;
            });
        });
	});
	</script>
    <script src="<?php echo plugins_url( "gravity-forms-auditor/js/moment.js" ); ?>"></script>
    <h1>Gravity Forms Auditor</h1>
    <?php
    global $wpdb;
    $table = $wpdb->prefix . "form_auditor";
    $query = "SELECT id FROM " . $table;
    $result = $wpdb->get_results( $query );
    if( count( $result )>0 ){
        // getting a list of all runs
        $all_reports_query = "SELECT timestamp, file_name FROM " . $wpdb->prefix . "form_auditor ORDER BY timestamp DESC";
        $result = $wpdb->get_results( $all_reports_query );
        // getting the last run
        $last_run = $result[0]->timestamp;
        ?>
        <p>Last report run on: <span id="last-run"></span></p>
        <p>Select a previous report to download</p>
        <select id="select-old-report">
            <option value="select-a-report">Select a Report</option>
            <?php
            foreach( $result as $key=>$report ){
                echo '<option id="report' . $key . '" value="' . $report->file_name . '"></option>';
            }
            ?>
        </select>
        <script>
        jQuery(document).ready(function($) {
            var lastRun = moment("<?php echo $last_run; ?>").format('MMMM Do YYYY, h:mm a');
            jQuery("#last-run").html(lastRun);
            <?php
            foreach( $result as $key=>$report ){
                $selector = "report" . $key;
                ?>
                var <?php echo $selector; ?> = moment("<?php echo $report->timestamp; ?>").format('MMMM Do YYYY, h:mm a');
                jQuery("#<?php echo $selector; ?>").html(<?php echo $selector; ?>);
                <?php
            }
            ?>
        });
        </script>
        <?php
        submit_button( "Download Selected Report", "large", "download-old-report" );
    } else{
        ?>
        <p>Last report run on: The report has never been run.</p>
        <?php
    }
    ?>
    <p>Run a new report on all differences since last report.</p>
    <?php
    submit_button( "Run New Difference Report" );
    ?>
    <p>Run a full report on all Gravity Forms.</p>
    <?php
    submit_button( "Run Full Report", "primary", "run-full-report" );
}

// Registering the run report AJAX call for running the full report
add_action( 'wp_ajax_run_full_report', 'full_report_runner' );
function full_report_runner() {
    echo "test";



    // terminating the script
    wp_die();
}

// Registering the run report AJAX call for running the difference report
add_action( 'wp_ajax_run_report', 'report_runner' );
function report_runner() {
    global $wpdb;
    // $whatever = intval( $_POST['whatever'] );

    // getting the last forms dump
    $result = $wpdb->get_results( "SELECT forms_dump FROM " . $wpdb->prefix . "form_auditor ORDER BY timestamp DESC LIMIT 1;" );
    $old_forms_json = $result[0]->forms_dump;
    $old_forms = json_decode( $old_forms_json, true );
    echo 'old_forms: ' . json_encode($old_forms);

    // getting the latest dump
    $new_forms = get_all_gf();
    $new_forms_json = json_encode( $new_forms );

    // generating the file name for the report
    $date = new DateTime();
    $filename = "WP-Gravity-Forms-Audit-" . $date->getTimestamp() . ".xlsx";

    // inserting forms dump into DB
    $wpdb->query( $wpdb->prepare( 
        "INSERT INTO " . $wpdb->prefix . "form_auditor ( forms_dump, file_name ) VALUES ( %s, %s )",
        $new_forms_json,
        $filename
    ) );

    $new_forms_flattened = flatten_display_meta( $new_forms );
    $old_forms_flattened = flatten_display_meta( $old_forms );

    // echo "old_forms_flattened: " . $old_forms_flattened;
    // echo "new_forms_flattened: " . $new_forms_flattened;
    $diffs = get_diffs( $old_forms_flattened, $new_forms_flattened );
    // echo "diffs: " . print_r( $diffs );
    
    generate_report( $diffs, $new_forms, $filename );

    // returning the URL of the report back to the browser
    // echo wp_upload_dir()["baseurl"] . "/gf-audits/" . $filename;

	wp_die(); // this is required to terminate immediately and return a proper response
}

// a function that generates the report and makes it available for download
function generate_report( $diffs, $dump, $filename ) {
    require( "PHPExcel/PHPExcel.php" );
    $phpExcel = new PHPExcel;
    $phpExcel->getProperties()->setTitle("Gravity Forms Changes");
    $phpExcel->getProperties()->setCreator("Joseph Kerkhof");
    $phpExcel->getProperties()->setDescription("The configuration of every new Gravity Forms form.");
    $writer = PHPExcel_IOFactory::createWriter($phpExcel, "Excel2007");
    $sheet = $phpExcel->getActiveSheet();
    $sheet->setTitle("WordPress Forms Audit");

    // setting the headers in the sheet
    $sheet->getCell('A1')->setValue('WordPress Site ID');
    $sheet->getCell('B1')->setValue('Site Name');
    $sheet->getCell('C1')->setValue('Site URL');
    $sheet->getCell('D1')->setValue('Site Owner Email');
    $sheet->getCell('E1')->setValue('Site Admins');
    $sheet->getCell('F1')->setValue('Form ID');
    $sheet->getCell('G1')->setValue('Form Title');
    $sheet->getCell('H1')->setValue('Pages Form Appears On');
    $sheet->getCell('I1')->setValue('Field Label');
    $sheet->getCell('J1')->setValue('Field Type');

    // Inserting form data
    $row_counter = 2; // start writing at row 2 in the spreadsheet
    for( $i=0; $i<count( $diffs ); $i++ ){
        $site_id = $diffs[$i]["site_id"];
        $form_id = $diffs[$i]["form_id"];
        for( $j=0; $j<count( $dump ); $j++ ){
            if( $dump[$j]["site_id"]==$site_id ) {
                $sheet->getCell('A' . (string) $row_counter)->setValue($dump[$j]["site_id"]);
                $sheet->getCell('B' . (string) $row_counter)->setValue($dump[$j]["site_name"]);
                $sheet->getCell('C' . (string) $row_counter)->setValue($dump[$j]["site_url"]);
                $sheet->getCell('D' . (string) $row_counter)->setValue($dump[$j]["admin_email"]);
                $sheet->getCell('E' . (string) $row_counter)->setValue(implode(", " ,$dump[$j]["site_admins"]));
                $forms = $dump[$j]["forms"];
                for( $k=0; $k<count( $forms ); $k++ ){
                    if( $forms[$k]["form_id"]==$form_id ){
                        $sheet->getCell('F' . (string) $row_counter)->setValue($forms[$k]["form_id"]);
                        $sheet->getCell('G' . (string) $row_counter)->setValue($forms[$k]["display_meta"]["title"]);
                        $sheet->getCell('H' . (string) $row_counter)->setValue(implode(", ", $forms[$k]["permalinks"]));

                        $row_counter++;
                        $fields = $forms[$k]["display_meta"]["fields"];
                        for( $l=0; $l<count( $fields ); $l++){
                            $sheet->getCell('I' . (string) $row_counter)->setValue($fields[$l]["label"]);
                            $sheet->getCell('J' . (string) $row_counter)->setValue($fields[$l]["type"]);
                            $row_counter++;
                        }
                    }
                }
            }
        }
    }

    // creating the directory if it doesn't exist
    if ( !file_exists( wp_upload_dir()["basedir"] . "/gf-audits" ) ) {
        mkdir( wp_upload_dir()["basedir"] . "/gf-audits" , 0777, true);
    }
    // saving the report
    $writer->save( wp_upload_dir()["basedir"] . "/gf-audits/" . $filename );
}

// a function that takes two dumps and returns an array with a site id and form id with the differences between the dumps
function get_diffs($old_dump, $new_dump){
    $diffs = array();
    for( $i=0; $i<count( $new_dump ); $i++ ){
        $new_site_id = $new_dump[$i]["site_id"];
        $new_site_forms = $new_dump[$i]["forms"];
        for( $j=0; $j<count( $new_site_forms ); $j++ ){
            $new_form_id = $new_site_forms[$j]["form_id"];
            $is_form_in_dump = is_form_in_dump( $new_site_id, $new_form_id, $old_dump );
            if( $is_form_in_dump[0] ){
                $new_display_meta = $new_site_forms[$j]["display_meta"];
                $old_display_meta = $old_dump[$is_form_in_dump[1]]["forms"][$is_form_in_dump[2]]["display_meta"];
                $are_strings_same = $new_display_meta==$old_display_meta;
                if( !$are_strings_same ){
                    array_push( $diffs, array( "site_id"=>$new_site_id, "form_id"=>$new_form_id ) ); // adding the form to the array of differences
                }
            } else{
                array_push( $diffs, array( "site_id"=>$new_site_id, "form_id"=>$new_form_id ) ); // adding the form to the array of differences
            }
        }
    }
    return $diffs;
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
            $site_url = get_site_url( $site_id );
            $admin_email = $site_descriptors["admin_email"];

            // getting the users for this site
            $admins = array();
            $site_users = get_users( array( "blog_id"=>$site_id, "role"=>"administrator" ) );
            foreach( $site_users as $user ){
                array_push( $admins, $user->user_email );
            }

            // Converting the JSON coming back from the DB to an array
            $forms = array();
            for( $j=0; $j<count( $rows ); $j++ ) {
                $form_id = $rows[$j]->form_id;
                $display_meta = json_decode( $rows[$j]->display_meta, true );
                $permalinks = get_pages_with_gf( $site_id, $form_id );
                $form = array( "form_id"=>$form_id, "display_meta"=>$display_meta, "permalinks"=>$permalinks );
                array_push( $forms, $form ); // adding form into forms
            }
            $site_forms = array( "site_id"=>$site_id, "site_name"=>$site_name, "site_url"=>$site_url, "admin_email"=>$admin_email, "site_admins"=>$admins, "forms"=>$forms );
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