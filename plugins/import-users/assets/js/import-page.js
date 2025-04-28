jQuery(document).ready(function($) {
  var fileInput, fileID, fileName, fileURL, fileSize, fileType;

  // Select File
  $('#iu-select-file').on('click', function() {
      fileInput = wp.media({
          title: 'Select File',
          button: {
              text: 'Select'
          },
          multiple: false
      }).open().on('select', function() {
          var file = fileInput.state().get('selection').first().toJSON();
          fileID = file.id;
          fileName = file.title;
          fileURL = file.url;
          fileSize = file.size;
          fileType = file.mime;

          // Display file details
          $('#iu-file-title').text(fileName);
          $('#iu-file-url').attr('href', fileURL).text(fileURL);
          $('#iu-file-size').text(fileSize);
          $('#iu-file-type').text(fileType);

          $('#iu-select-file').prop('disabled', true);
          $('#iu-start-import').prop('disabled', false);
          $('#iu-file-details').show();
      });
  });

  // Start Import
  $('#iu-start-import').on('click', function() {
      var data = {
          action: 'iu_handle_import_file',
          security: iu_ajax.nonce,
          file_id: fileID
      };

      // Start Import AJAX request
      $.post(iu_ajax.ajax_url, data, function(response) {
          if (response.success) {
              var uploadID = response.data.upload_id;
              $('#iu-progress-panel').show();
              trackProgress(uploadID);
          } else {
              alert(response.data.message);
          }
      });
  });

  // Track Progress
  function trackProgress(uploadID) {
      var data = {
          action: 'iu_check_progress',
          security: iu_ajax.nonce,
          upload_id: uploadID,
          total_rows: 100,
          file_name: fileName,
          file_url: fileURL,
          post_type: fileType
      };

      $.post(iu_ajax.ajax_url, data, function(response) {
          if (response.success) {
              $('#iu-progress-percent').text(response.data.percent + '%');
              $('#iu-progress-rows').text(response.data.processed + '/100');

              if (response.data.percent < 100) {
                  setTimeout(function() {
                      trackProgress(uploadID);
                  }, 1000);
              } else {
                  $('#iu-import-status').text('Import Complete').show();
                  $('#iu-progress-bar').css('width', '100%');
              }
          } else {
              alert('Error: ' + response.data.message);
          }
      });
  }
});
