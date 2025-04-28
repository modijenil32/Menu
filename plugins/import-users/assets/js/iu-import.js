jQuery(document).ready(function($) {
    $('#import-button').on('click', function(e) {
        e.preventDefault();  // Prevent the default form submission behavior

        var fileInput = $('#import-file'); // File input element
        var formData = new FormData();
        formData.append('file', fileInput[0].files[0]);
        formData.append('action', 'iu_import_file');
        formData.append('security', iu_import_vars.nonce);

        $('#import-button').prop('disabled', true);
        $('#progress-container').show();
        $('#import-status').text('Importing...');

        // Perform the AJAX upload
        $.ajax({
            url: iu_import_vars.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    var totalRows = response.data.total_rows;
                    var uploadId = response.data.upload_id;

                    var progressInterval = setInterval(function() {
                        $.post(iu_import_vars.ajaxurl, {
                            action: 'iu_check_progress',
                            upload_id: uploadId,
                            total_rows: totalRows,
                            security: iu_import_vars.nonce
                        }, function(progressResponse) {
                            if (progressResponse.success) {
                                var percent = progressResponse.data.percent;
                                $('#progress-bar').css('width', percent + '%');
                                $('#progress-status').text('Processed: ' + Math.floor((percent / 100) * totalRows) + ' / ' + totalRows);
                                
                                // If progress reaches 100%, stop the interval
                                if (percent >= 100) {
                                    clearInterval(progressInterval);
                                    $('#import-status').text('Import Completed!');
                                    $('#import-button').prop('disabled', false);
                                }
                            }
                        });
                    }, 500);
                } else {
                    $('#import-status').text('Import failed.');
                    $('#import-button').prop('disabled', false);
                }
            },
            error: function() {
                $('#import-status').text('Import failed.');
                $('#import-button').prop('disabled', false);
            }
        });
    });
});
