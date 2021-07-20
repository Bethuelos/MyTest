jQuery(function ($) {
    
    $(".a2w_delete_order_id").on('click', function(){
        var id = $('#post_ID').val();
        a2w_order_id_manually( id );
        return false;
    });
    
    $(".a2w_order_id_manually").on('click', function(){
        var codes = prompt("Please input Order ID(s) (example: ID1, ID2, etc.):");
        
        if (codes == null || codes == "") {
            //user cancelled prompt
        } else {
            var id = $('#post_ID').val();
            a2w_add_order_id_manually(id, codes);
        }
        
        return false;    
    });
    
    
    
    $(".a2w_delete_codes").on('click', function(){
        var id = $('#post_ID').val();
        a2w_delete_tracking_codes( id );
        return false;
    });
    
    $(".a2w_codes_manually").on('click', function(){
        var tracking_codes = prompt("Please input tracking codes (example: code1, code2, etc.):");
        
        if (tracking_codes == null || tracking_codes == "") {
            //user cancelled prompt
        } else {
            var id = $('#post_ID').val();
            a2w_add_tracking_codes_manually(id, tracking_codes);
        }
        
        return false;    
    });
    
    var a2w_delete_tracking_codes = function(id){
        var data = {'action': 'a2w_delete_tracking_codes', 'id' : id};
        jQuery.post(ajaxurl, data).done(function (response) {
                    var json = jQuery.parseJSON(response);
                    
                    if (json.state !== 'ok') {
                        alert('error');
                        console.log(json);
                    } else {
                        $('.a2w_tracking_code_data').remove();
                        $(".a2w_delete_codes").remove();
                    }
                       
                }).fail(function (xhr, status, error) {    
                 });
    }
    
    var a2w_add_tracking_codes_manually = function(id, codes){
        var data = {'action': 'a2w_add_tracking_codes_manually', 'id' : id, 'tracking_codes': codes};
        jQuery.post(ajaxurl, data).done(function (response) {
            var json = jQuery.parseJSON(response);
            
            if (json.state !== 'ok') {
                alert('error');
                console.log(json);
            } else {
                //reload page and show the message "order updated"
                var url = window.location.href;    
                if (url.indexOf('?') > -1){
                   url += '&message=1'
                }else{
                   url += '?message=1'
                }
                window.location.href = url;
    
            }
                       
        }).fail(function (xhr, status, error) {    
        });
    }
    
    
    
    var a2w_order_id_manually = function(id){
        var data = {'action': 'a2w_delete_order_id', 'id' : id};
        jQuery.post(ajaxurl, data).done(function (response) {
                    var json = jQuery.parseJSON(response);
                    
                    if (json.state !== 'ok') {
                        alert('error');
                        console.log(json);
                    } else {
                        $('.a2w_external_order_id').remove();
                        $(".a2w_delete_order_id").remove();
                    }
                       
                }).fail(function (xhr, status, error) {    
                 });
    }
    
    var a2w_add_order_id_manually = function(id, codes){
        var data = {'action': 'a2w_add_order_id_manually', 'id' : id, 'codes': codes};
        jQuery.post(ajaxurl, data).done(function (response) {
            var json = jQuery.parseJSON(response);
            
            if (json.state !== 'ok') {
                alert('error');
                console.log(json);
            } else {
                //reload page and show the message "order updated"
                var url = window.location.href;    
                if (url.indexOf('?') > -1){
                   url += '&message=1'
                }else{
                   url += '?message=1'
                }
                window.location.href = url;
    
            }
                       
        }).fail(function (xhr, status, error) {    
        });
    }
	
});

