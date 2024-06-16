<?php
//declare a php class with function my_import_export_buttons
class CoreFunctions {
    public function __construct() {
        // Assumes you want it for standard 'posts'
        add_action('admin_head-edit.php', array($this, 'add_buttons'));
    }

    public function add_buttons() {
        global $current_screen;
        $currentPost = $current_screen->post_type;

        // Target only the post listing screen
        // if ('post' !== $current_screen->post_type) {
        //     return;
        // }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add buttons next to "Add New"
                setTimeout(() => {
                    $('.wrap h1').append( 
                    '<button type="button" data-type="<?php echo $currentPost ?>" class="jdd_export-button page-title-action">Export</button>' +
                    '<input type="file" id="jdd_excelFile" style="display: none;" accept=".xlsx,.xls">' +
                    '<button type="button" data-type="<?php echo $currentPost ?>" class="jdd_import-button page-title-action">Import</button>' 
                );
                }, 0);
                
            });
        </script>
        <?php
    }
}