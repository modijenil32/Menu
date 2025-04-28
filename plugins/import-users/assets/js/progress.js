jQuery(function($){
  var selected = {};

  // 1) File selection & details (same as before) …
  // After media select:
  // selected.id, selected.title, selected.url, selected.post_type

  $('#iu-start-import').on('click', function(e){
    e.preventDefault();
    $('#iu-start-import').hide();
    $('#iu-import-status, #iu-progress-panel').show().find('#iu-progress-bar').css('width','0%');
    
    // Kick off import
    $.post(iu_ajax.ajax_url, {
      action:     'iu_handle_import_file',
      security:   iu_ajax.nonce,
      file_id:    selected.id
    }, function(res){
      if (!res.success) {
        return $('#iu-import-status').html('<span style="color:red;">'+res.data.message+'</span>');
      }
      // Start polling: pass upload_id, total_rows, and file_id
      pollProgress(res.data.upload_id, res.data.total_rows, selected.id);
    }, 'json');
  });

  function pollProgress(upload_id, total_rows, file_id) {
    var iv = setInterval(function(){
      $.post(iu_ajax.ajax_url, {
        action:     'iu_check_progress',
        security:   iu_ajax.nonce,
        upload_id:  upload_id,
        total_rows: total_rows,
        file_id:    file_id
      }, function(res){
        if (!res.success) {
          clearInterval(iv);
          return $('#iu-import-status').html('<span style="color:red;">'+res.data.message+'</span>');
        }
        // Update UI
        $('#iu-progress-bar').css('width',res.data.percent+'%');
        $('#iu-progress-percent').text(res.data.percent+'%');
        $('#iu-progress-rows').text(res.data.processed+'/'+ total_rows);

        if (res.data.percent >= 100) {
          clearInterval(iv);
          $('#iu-import-status').html('<span style="color:green;">Import Complete!</span>');
          $('#iu-start-import').show();       // Show the button again
        }
      }, 'json');
    }, 1000);
  }
});
