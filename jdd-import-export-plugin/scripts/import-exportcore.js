jQuery(document).ready(function($) {
    $('body').on('click', '.jdd_export-button', function() {
        var postType = $(this).attr('data-type');
        let $this = $(this);
        var postType = $this.attr('data-type');


        var postIds = '';

        if($('.wp-list-table tbody .check-column input[type="checkbox"]:checked').length > 0) {
            $('.wp-list-table tbody .check-column input[type="checkbox"]:checked').each(function() {
                postIds += $(this).val() + ',';
            });

            // Remove the last comma
            postIds = postIds.slice(0, -1);
        }

        $.ajax({
            url: myPluginData.ajax_url,
            type: 'POST',
            data: {
                action: 'my_export_handler',
                security: myPluginData.export_nonce,
                postType: postType,
                postIds: postIds.toString()
                // Add any additional data for filtering if needed
            },
            // Adjust ajax to expect a blob response
            xhrFields: {
                responseType: 'blob'
            },
            success: function(response) {
                alert("Export Success");
                // Force download of the export file (response)
                // You may need to adapt this logic slightly
                
                var blob = new Blob([response], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'export_data.xlsx';
                link.click();
            }
        });
    });

    $('body').on('click', '.jdd_import-button', function() {
        document.querySelector('#jdd_excelFile').click();
    });

    $('body').on('change', '#jdd_excelFile', function() {
        var file = this.files[0];
        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'handle_excel_upload'); // The action hook name

        $.ajax({
            url: ajaxurl, // The AJAX URL, should be defined by WordPress
            type: 'POST',
            data: formData,
            processData: false, // Important!
            contentType: false, // Important!
            success: function(response) {
                if(response.trim() == 'success') {
                    alert('Data Imported Successfully');
                    window.location.reload();
                } else {
                    alert(response);
                }
            }
        });
    });
});