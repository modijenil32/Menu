jQuery(function($){
    let total=0, offset=0, filename='', postType='', ext='';
  
    $('#ui-select').click(()=> $('#ui-file').click());
    $('#ui-file').change(()=>{
      const f = $('#ui-file')[0].files[0];
      if(!f) return;
      filename=f.name;
      $('#ui-file-info').text(`${filename} (${(f.size/1024/1024).toFixed(2)} MB)`);
      // upload file
      let fd=new FormData();
      fd.append('action','ui_upload_file');
      fd.append('file',f);
      fd.append(' _ajax_nonce', uiAjax.nonce);
      $.ajax({
        url: uiAjax.url, method:'POST', data:fd, processData:false, contentType:false,
        success(r){
          total = r.data.total;
          postType = r.data.post_type;
          offset = 0;
          $('#ui-total').text(total);
          $('#ui-processed').text('0');
          $('#ui-skipped').text('0');
          $('#ui-start').prop('disabled',false);
        },
        error(e){ alert(e.responseJSON.data); }
      });
    });
  
    $('#ui-import-form').submit(e=>{
      e.preventDefault();
      $('#ui-progress').show();
      $('#ui-status').text('importing');
      $('#ui-start,#ui-select').prop('disabled',true);
      processChunk();
    });
  
    function processChunk(){
      let chunk = uiAjax.chunkSize;
      $.post(uiAjax.url,{
        action:'ui_process_chunk',
        offset:offset,
        chunk:chunk,
        total:total,
        _ajax_nonce:uiAjax.nonce
      }, r=>{
        let p=r.data.processed, s=r.data.skipped;
        offset += p+s;
        $('#ui-processed').text(offset);
        $('#ui-skipped').text(s);
        let pct=Math.min(100,Math.round(offset/total*100));
        $('#ui-import-progress').val(pct);
        if(offset<total){
          processChunk();
        } else {
          $('#ui-status').text('completed');
          location.reload();
        }
      });
    }
  });
  