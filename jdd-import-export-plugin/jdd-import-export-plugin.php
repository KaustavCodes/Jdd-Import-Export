<?php
/*
Plugin Name:  Jdd Import Export Plugin
Plugin URI:   https://jadedsoftwares.com/my-import-export-plugin
Description:  Adds Import and Export functionality to WordPress post types
Version:      1.0.0
Author:       Kaustav Halder
Author URI:   https://jadedsoftwares.com 
License:      GPLv2 or later
*/

require_once('core-functions.php');
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


function my_enqueue_scripts() {
    wp_enqueue_script('my-plugin-script', plugin_dir_url(__FILE__) . 'scripts/import-exportcore.js', array('jquery'));

    // Make export URL available to script
    wp_localize_script('my-plugin-script', 'myPluginData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'export_nonce' => wp_create_nonce('my_export_action') 
    ));
}
add_action('admin_enqueue_scripts', 'my_enqueue_scripts');

//The Core FUnction contains the basic hooks for the project
$coreMethods = new CoreFunctions();


//Handle the import
function my_export_handler_function() {
    // Verify nonce 
    check_ajax_referer('my_export_action', 'security');

    $post_type = isset($_POST['postType']) ? sanitize_text_field($_POST['postType']) : 'post'; 

    $postIds = isset($_POST['postIds']) ? $_POST['postIds'] : ''; 

    //error_log( 'Export Handler Called. Post Type: ' . '    ' . $_POST['postType']);
    // convert $post_type to array on integer

    $allowed_post_count = 10;

    $license_key = get_option('license_key');

    if($license_key != '') {
        $allowed_post_count = -1;
    }
    

    // Query the database 
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish', // Assuming you want published posts
        'posts_per_page' => $allowed_post_count // Get all posts
    );

    if($postIds != '') {
        $post_id_array = explode(',', $postIds);

        //add posts_in to the $args array
        $args['post__in'] = $post_id_array;
    }
    

    $posts_query = new WP_Query($args);

    $export_data = array();
    $acfHeaders = array();
    $acfHeadersAdded = false;

    //Setting the excel header columns
    $headerFields = array();
    $headerFields[] = 'id';
    $headerFields[] = 'title';
    $headerFields[] = 'content';
    $headerFields[] = 'publish_status';
    $headerFields[] = 'post_type';

    // Iterate through posts 
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();
            $postId = get_the_id();

            $valueRow = array();

            $acf_fields = get_fields();

            //Need to Push the id, title and content of the current wordpress post to the $valueRow array
            $valueRow[] = get_the_id();
            $valueRow[] = get_the_title();
            $valueRow[] = get_the_content();
            $valueRow[] = get_post_status();
            $valueRow[] = get_post_type();

            if(!$acfHeadersAdded) {
                foreach($acf_fields as $key => $value) {
                    $acfHeaders[] = $key; 
                }
                $acfHeadersAdded = true;

                //contatinate the  $export_data and the $acfHeadersAdded
                $export_data[] = array_merge($headerFields, $acfHeaders);
            }

            if(isset($acfHeaders) && count($acfHeaders) > 0) {
                foreach($acfHeaders as $key => $value) {
                    //How to write message to wordpress debut log
                    $acfItem = $acf_fields[$value];

                    if(isset($acf_fields) && isset($acfItem)) {
                        $currentVal = '';
                        if (is_array($acfItem) && isset($acfItem[0]->ID)) {
                            // This is an ACF posts array
                            $currentVal = "[";

                            foreach($acfItem as $key => $value) {
                                $currentVal .= $value->ID . ',';
                            }

                                if(substr($currentVal, -1) == ',') {
                                $currentVal = substr($currentVal, 0, -1);
                            }

                            $currentVal .= "]";
                        } elseif (is_array($acfItem) && isset($acfItem['url']) && isset($acfItem['alt'])) {
                            // This is an ACF Image
                            $currentVal = $acfItem["ID"];
                        } else {
                            $currentVal = $acfItem;
                        }

                        $valueRow[] = $currentVal;
                        
                    } else {
                        $valueRow[] = '';
                    }
                }
            }

            $export_data[] = $valueRow;
        }
    }
    wp_reset_postdata(); // Restore original post data

    exportToExcel($export_data);
    exit; // Important to terminate the script
}


function exportToExcel($export_data) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->fromArray($export_data, null, 'A1');

    // Redirect output to a client’s web browser (Xlsx)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="export_data.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

add_action('wp_ajax_my_export_handler', 'my_export_handler_function');


//Handle the upload
function handle_excel_upload() {
    //Get current logged in user id in wordpress
    $user_id = get_current_user_id();

    $result_message = "Failed to import";

    // Check for file upload
    if (!empty($_FILES['file'])) {
        // Handle the file upload
        // The uploaded file is in $_FILES['file']

        $file = $_FILES['file']['tmp_name'];

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $header = array_shift($rows); // The first row is the header

        $allowedItemCount = 1;

        $license_key = get_option('license_key');

        

        if($license_key != '') {
            $allowedItemCount = -1;
        }

        $hitLimit = false;


        $itemImported = 0;

        foreach ($rows as $row) {

            if($itemImported > $allowedItemCount && $allowedItemCount != -1) {
                $hitLimit = true;
                $result_message = "You have exceeded the allowed import count. You can only import $allowedItemCount item with the free version of the plugin";
                break;
            }

            $post_id = $row[0]; // The first column is the post_id
            $post_title = $row[1]; // The second column is the post title
            $post_content = $row[2]; // The third column is the post content
            $post_status = $row[3]; // The fourth column is the post status
            $post_type = $row[4]; // The fourth column is the post status

            // Update the post or create a new one if post_id is zero

            if($post_id == 0) {
                $post_id = wp_insert_post(array(
                    'post_title'    => $post_title,
                    'post_content'  => $post_content,
                    'post_status'   => $post_status,
                    'post_author'   => $user_id, // or any other author id
                    'post_type'     => $post_type,
                ));
            } else {
                $post_data = array(
                    'ID' => $post_id ? $post_id : null,
                    'post_title' => $post_title,
                    'post_content' => $post_content,
                    'post_status' => $post_status,
                );
                $post_id = wp_update_post($post_data);
            }
            

            // Update the ACF fields
            foreach ($header as $index => $field_name) {
                if ($index > 4) { // Skip the first four columns (post_id, post_title, post_content, post_status)
                    $field_value = $row[$index];

                    //check if $field_value starts and ends with [ and ]. If so, it is an array and convert it to an array of integers by splitting csv
                    if(substr($field_value, 0, 1) == '[' && substr($field_value, -1) == ']') {
                        $field_value = explode(',', substr($field_value, 1, -1));
                        $field_value = array_map('intval', $field_value);
                    }

                    update_field($field_name, $field_value, $post_id);
                }
            }

            $itemImported++;
        }
    }

    if(!$hitLimit) {
        $result_message = "Import successful";
    }

    echo $result_message;
    die();
}

add_action('wp_ajax_handle_excel_upload', 'handle_excel_upload');


// Add a new submenu under Settings
add_action('admin_menu', 'jdd_plugin_menu');
function jdd_plugin_menu() {
    add_options_page(
        'Jdd Import Export Settings',
        'Jdd Import Export',
        'manage_options',
        'jdd_plugin-settings',
        'jdd_plugin_settings_page'
    );
}

// Display the plugin settings page
function jdd_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>My Plugin</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('my-plugin-settings');
            do_settings_sections('my-plugin-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                <th scope="row">License Key</th>
                <td><input type="text" name="license_key" value="<?php echo esc_attr(get_option('license_key')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register and define the settings
add_action('admin_init', 'my_plugin_settings_init');
function my_plugin_settings_init(){
    register_setting('my-plugin-settings', 'license_key');
}

?>