<?php
/* Template Name: Import Users */
get_header();
?>

<div class="import-section">
    <h3>Import Users</h3>
    <form id="import-form" method="post" enctype="multipart/form-data">
        <input type="file" id="import-file" name="file" required />
        <button id="import-button" type="button">Start Import</button>
    </form>

    <div id="progress-container" style="display: none;">
        <div id="progress-bar" style="width: 0%; height: 20px; background-color: green;"></div>
        <span id="progress-status">Processed: 0 / 0</span>
    </div>
    <div id="import-status"></div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle file upload and import
        $('#import-button').on('click', function(e) {
            e.preventDefault();  // Prevent the default form submission behavior

            var fileInput = $('#import-file'); // File input element
            var formData = new FormData();
            formData.append('file', fileInput[0].files[0]);
            formData.append('action', 'iu_import_file');
            formData.append('security', '<?php echo wp_create_nonce('iu_import_nonce'); ?>');

            // Disable the import button and show progress
            $('#import-button').prop('disabled', true);
            $('#progress-container').show();
            $('#import-status').text('Importing...');

            // Perform AJAX file upload
            $.ajax({
                url: ajaxurl,  // Ensure 'ajaxurl' is defined, or use the correct URL for your AJAX handler
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success) {
                        var totalRows = response.data.total_rows;
                        var uploadedRows = 0;

                        var progressInterval = setInterval(function() {
                            uploadedRows += 10; // Simulate progress (adjust accordingly)
                            var percent = Math.min(100, (uploadedRows / totalRows) * 100);
                            $('#progress-bar').css('width', percent + '%');
                            $('#progress-status').text('Processed: ' + uploadedRows + ' / ' + totalRows);

                            if (percent >= 100) {
                                clearInterval(progressInterval);
                                $('#import-status').text('Import Completed!');
                                $('#import-button').prop('disabled', false); // Re-enable the button
                            }
                        }, 500);
                    } else {
                        $('#import-status').text('Import failed.');
                        $('#import-button').prop('disabled', false); // Re-enable the button
                    }
                },
                error: function() {
                    $('#import-status').text('Import failed.');
                    $('#import-button').prop('disabled', false); // Re-enable the button
                }
            });
        });
    });
</script>

<?php get_footer(); ?>
