<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline">Import Users</h1>

    <!-- File Details -->
    <div id="iu-file-details" style="display:none; margin: 20px 0;">
        <table class="form-table">
            <tr>
                <th>File:</th>
                <td><span id="iu-file-title"></span></td>
            </tr>
            <tr>
                <th>URL:</th>
                <td><a id="iu-file-url" href="#" target="_blank"></a></td>
            </tr>
            <tr>
                <th>Size:</th>
                <td><span id="iu-file-size"></span> bytes</td>
            </tr>
            <tr>
                <th>Type:</th>
                <td><span id="iu-file-type"></span></td>
            </tr>
        </table>
    </div>

    <!-- Select & Import Buttons -->
    <p>
        <button id="iu-select-file" type="button" class="button">Select File</button>
        <button id="iu-start-import" type="button" class="button button-primary" disabled>Import</button>
    </p>

    <!-- Import Status -->
    <div id="iu-import-status" style="display:none; margin-top:20px;">
        <p id="iu-import-message">Preparing import...</p>
    </div>

    <!-- Progress Section -->
    <div id="iu-progress-panel" style="display:none; margin-top:20px;">
        <div style="background:#eee; height:20px; width:100%; border-radius:5px; overflow:hidden;">
            <div id="iu-progress-bar" style="height:20px; width:0; background:#0073aa;"></div>
        </div>
        <p style="margin-top:5px;">
            <strong><span id="iu-progress-percent">0%</span></strong> completed | 
            <span id="iu-progress-rows">0/0 rows</span>
        </p>

        <!-- Simple Loader Text -->
        <div id="iu-loader" style="display:none; text-align:center; margin-top:10px;">
            <p id="iu-loader-text" style="font-weight:bold;">Loading... 0%</p>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    var frame, sel = {};

    // Select file
    $('#iu-select-file').on('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame = wp.media({
            title: 'Select CSV/XML',
            library: { type: ['text/csv','application/xml','text/xml'] },
            button: { text: 'Use this' },
            multiple: false
        });
        frame.on('select', function(){
            var a = frame.state().get('selection').first().toJSON();
            sel = {
                id: a.id,
                title: a.title,
                url: a.url,
                size: a.filesize || a.fileSize || 0,
                type: a.post_mime_type
            };
            $('#iu-file-title').text(sel.title);
            $('#iu-file-url').attr('href', sel.url).text(sel.url);
            $('#iu-file-size').text(sel.size);
            $('#iu-file-type').text(sel.type);
            $('#iu-file-details').fadeIn();
            $('#iu-start-import').prop('disabled', false);
        });
        frame.open();
    });

    // Start Import
    $('#iu-start-import').on('click', function(e){
        e.preventDefault();
        $(this).hide();
        $('#iu-import-status, #iu-progress-panel').show();
        $('#iu-progress-bar').css('width','0%');
        $('#iu-progress-percent').text('0%');
        $('#iu-progress-rows').text('0/0');
        $('#iu-loader').show();
        $('#iu-loader-text').text('Loading... 0%');
        $('#iu-import-message').text('Starting import...');

        $.post(iu_ajax.ajax_url, {
            action: 'iu_handle_import_file',
            security: iu_ajax.nonce,
            file_id: sel.id
        }, function(r){
            if(!r.success){
                $('#iu-loader').hide();
                $('#iu-import-message').html('<span style="color:red;">'+r.data.message+'</span>');
                return;
            }
            poll(r.data.upload_id, r.data.total_rows, r.data.file_name, r.data.file_url, r.data.post_type);
        }, 'json');
    });

    // Poll progress
    function poll(uid, total, fname, furl, ptype){
        var iv = setInterval(function(){
            $.post(iu_ajax.ajax_url, {
                action: 'iu_check_progress',
                security: iu_ajax.nonce,
                upload_id: uid,
                total_rows: total,
                file_name: fname,
                file_url: furl,
                post_type: ptype
            }, function(r){
                if(!r.success){
                    clearInterval(iv);
                    $('#iu-loader').hide();
                    $('#iu-import-message').html('<span style="color:red;">'+r.data.message+'</span>');
                    return;
                }
                var pct = r.data.percent;
                var proc = r.data.processed;
                $('#iu-progress-bar').css('width', pct+'%');
                $('#iu-progress-percent').text(pct+'%');
                $('#iu-progress-rows').text(proc+'/'+total);
                $('#iu-loader-text').text('Loading... '+pct+'%');
                $('#iu-import-message').text('Importing... Please wait');

                if(pct >= 100){
                    clearInterval(iv);
                    $('#iu-loader').hide();
                    $('#iu-import-message').html('<span style="color:green;">Import Complete!</span>');
                    setTimeout(function(){
                        window.location = iu_ajax.history_url;
                    }, 1000);
                }
            }, 'json');
        }, 1000);
    }
});
</script>
