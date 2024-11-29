//Jquery for show page insert, insert image
var img_src = '';

jQuery(document).ready(function($) {
        jQuery( ".mct-ai-tabs #tabs" ).tabs();  //Set up tabs display
        
        $("#ai-dialog").dialog({ //Set up dialog box for images
            autoOpen : false, 
            dialogClass  : 'wp-dialog', 
            modal : true, 
            closeOnEscape: true,
            buttons: {
            "Insert": function(){myc_insert();$( this ).dialog( "close" );  },
            "Featured": function() {myc_feature();$( this ).dialog( "close" );},
            Cancel: function() {$( this ).dialog( "close" ); }}
        });
        
        $('.switch-html').click(function(){$('div#ai-showpg-msg').css('display',"none")});//shut off msg
        $('.switch-tmce').click(function(){$('div#ai-showpg-msg').css('display',"")});//turn on msg
        //Hover highlight
        var selector = "div.ui-tabs-panel p, div.ui-tabs-panel ul, div.ui-tabs-panel ol, div.ui-tabs-panel table, div.ui-tabs-panel h1, div.ui-tabs-panel h2, div.ui-tabs-panel h3, div.ui-tabs-panel h4, div.ui-tabs-panel h5, div.ui-tabs-panel h6 ";
        jQuery( selector ).hover(function(){
            if ($("#no-element-copy").prop('checked')) return;
            if ($("textarea#content.wp-editor-area").is(":visible")) return; //Doesn't work in Text tab
            if ($(this).attr('id') == 'idx-entry') return
            $(this).css("background-color","yellow");
            },function(){
            $(this).css("background-color","");
        });
        //click on element
        jQuery( selector ).click(function(){
            if ($("#no-element-copy").prop('checked')) return;
            if ($("textarea#content.wp-editor-area").is(":visible")) return;
            if ($(this).attr('id') == 'idx-entry') return
            $(this).css("background-color","");
            var elem = $(this).clone();
            elem.find('img').remove();
            //if (elem.find('img').length != 0) return false;
            if (elem.html().length == 0) return false;
            //var selection = $('<div/>').append($(this).clone()).html();
           var selection = $('<div/>').append(elem).html();
           if (!document.body.classList.contains( 'block-editor-page' )) {
               tinyMCE.execCommand("mceInsertContent", false, selection);
           } else {
               myc_guten_insert(selection);
           }
           return false;
        });
        //click on image
        jQuery( "div.ui-tabs-panel img" ).click(function(e){
            if ($("#no-element-copy").prop('checked')) return;
            if ($("textarea#content.wp-editor-area").is(":visible")) return;
            e.stopPropagation();
            img_src = $(this).attr('src');
           $("#ai-dialog").dialog("open");
           return false;
           //var selection = $('<div/>').append($(this).clone()).html();
           //tinyMCE.execCommand("mceInsertContent", false, selection);
           //return false;
        });
        //click on index article
        $('a#idx-article').click(function() {
            var tab = $(this).attr('href');
            $( ".mct-ai-tabs #tabs" ).tabs( "option", "active", tab );
            return false;
        })
    });
    //insert image into post
    function myc_insert(){
        if (document.body.classList.contains( 'block-editor-page' )) { //Gutenberg editor
            ThisBlock = wp.data.select( "core/editor" ).getSelectedBlock();
            if (ThisBlock === null) {
                alert("No Image Block Selected. Please click on an Empty Image block where you would like the image inserted then use click-copy.");
                return;
            }
            blockUid=ThisBlock.clientId;
            if (ThisBlock.name != "core/image"){
                alert("No Image Block Selected. Please click on an Empty Image block where you would like the image inserted then use click-copy.");
                return;
            }
            if (typeof ThisBlock.originalContent !== 'undefined'){
                alert("Image Block Not Empty! Please click on an Empty Image block where you would like the image inserted then use click-copy.");
                return;
            }
        }
        var pid = jQuery('#ai_post_id').attr('value');
        var nonce = jQuery('#showpg_nonce').attr('value');
        var title = jQuery('#ai_title_alt').attr('value');
        var align = jQuery('input[name=ai_img_align]:checked').val();
        var size = jQuery('input[name=ai_img_size]:checked').val();
        var data = { pid: pid,
              nonce: nonce,
              title: title,
              imgsrc: img_src,
              align: align,
              size: size,
              type: 'insert',
              action: 'mct_ai_showpg_ajax'};
        jQuery('#ai-saving').css('display', 'inline');
        jQuery.post(mct_ai_showpg.ajaxurl, data, function (data) {
            var status = jQuery(data).find('response_data').text();
            var img_str = jQuery(data).find('supplemental imgstr').text();
            jQuery('#ai-saving').css('display', 'none');
            if (status == 'Ok') {
              selText = jQuery('<div/>').append(img_str).html();
              if (document.body.classList.contains( 'block-editor-page' )) {
                  selText = '<div class="wp-block-image"><figure class="align'+align+'">'+selText+'</figure></div>';
                  wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {attributes:
                              {alt:title,align:align,linkDestination:"custom",url:selText.match(/src="([^"]*)/)[1],
                      href:selText.match(/href="([^"]*)/)[1]}},{originalContent:selText});
                   //wp.data.dispatch( 'core/editor' ).savePost();
                   //location.reload(true);
                   alert("Save the post then Reload the Page with your Browser to see the image!");
              } else {
                  tinyMCE.execCommand("mceInsertContent", false, selText);
              }
            } else {
                alert(status);
            }
        });
        return false;
    };
    //Set as featured image
    function myc_feature(){
        var pid = jQuery('#ai_post_id').attr('value');
        var nonce = jQuery('#showpg_nonce').attr('value');
        var title = jQuery('#ai_title_alt').attr('value');
        var data = { pid: pid,
              nonce: nonce,
              imgsrc: img_src,
              title: title,
              type: 'feature',
              action: 'mct_ai_showpg_ajax'};
        jQuery('#ai-saving').css('display', 'inline');
        jQuery.post(mct_ai_showpg.ajaxurl, data, function (data) {
            var status = jQuery(data).find('response_data').text();
            jQuery('#ai-saving').css('display', 'none');
            if (status != 'Ok') {
                alert(status);
            }
            var html_str = jQuery(data).find('supplemental imgstr').text();
            WPSetThumbnailHTML(html_str);
        });
        return false;
    };
    
    function myc_guten_insert(selText){
        
        ThisBlock = wp.data.select( "core/editor" ).getSelectedBlock();
        if (ThisBlock === null) {
            alert("No Block Selected. Please click on the block where you would like the text inserted then use click-copy.");
            return;
        }
        blockUid=ThisBlock.clientId;
        if (ThisBlock.name === "core/quote"){
            html=ThisBlock.attributes.value;
            htmlvalue = html+selText;
            wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {attributes: 
                        {value: htmlvalue}} );
        } else if (ThisBlock.name === "core/paragraph"){
            html=ThisBlock.attributes.content;
            selText = selText.replace(/<p[^>]*>/g, '').replace(/<\/p>/g, '');
            if (html == '') {
                wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {attributes: 
                   {content: selText}} );
            } else {
                block = wp.blocks.createBlock( 'core/paragraph', { content: selText } );
                blkindex = wp.data.select("core/editor").getBlockIndex(blockUid);
                wp.data.dispatch( 'core/editor' ).insertBlocks( block, blkindex+1 );
            }
            //htmlvalue = html+selText;
            //wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {attributes: 
              //          {content: htmlvalue}} );
        } else if (ThisBlock.name === "core/freeform"){
            html=ThisBlock.attributes.content;
            len=html.length;
            if (html.substring(len-8,len+1) === '</a></p>'){ //Link at end, get in front of it
                ins = html.search('<p id="mct-ai-attriblink">');
                if (html.substring(ins-14,ins-1) == '</blockquote>') ins = ins-14;
                html1 = html.slice(0,ins);
                html2 = html.slice(ins,len+1);
                htmlvalue = html1+selText+html2;
            } else {
                htmlvalue = html+selText;
            }
            wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {attributes: 
                        {content: htmlvalue}} );
        } else {
            alert("Block must be type Paragraph, Quote or Classic.  For other block types, turn off Quick Copy (checkbox in upper right \n\
            of Saved Page metabox) and use regular copy and paste.");
        }
        /*
        textvalue = wp.richText.create({
            html:html
        });
        insert_index = textvalue.text.length;
        selText = wp.richText.create({
            html:selText
        });
        //selText = wp.richText.insert(selText,'/n',selText.text.length,selText.text.length); //Add newline to inserted selection
        //Select=window.getSelection();
        textvalue=wp.richText.insert(textvalue,selText,insert_index,insert_index);  //Select.anchorOffset,Select.focusOffset
        htmlvalue=wp.richText.toHTMLString({value:textvalue});
        
        wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {attributes: 
                    {value: htmlvalue}} );
        */
    };