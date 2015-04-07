jQuery(document).ready(function(){
 
});

function _ddf_add_field() {
    var data  = jQuery("#ddf_add_field_form").serialize();
    var params = new Object();
    params.action = "ddf_field_new";
    params.name = jQuery("#fieldname").val();
    params.id = jQuery("#id").val();
    params.fieldlabel = jQuery("#fieldlabel").val();
    params.fieldtype = jQuery("#fieldtype").val();
    params.field_options = jQuery("#field_options").val();

    jQuery.post('admin-ajax.php', params ,function(data) {
		jQuery("#field_list").html(data);
	});
    
    
}


function _ddf_edit(id) {
    var params = new Object();
    params.action = "ddf_field_edit";
    params.id = id
    jQuery.post('admin-ajax.php', params ,function(data) {
		jQuery("#field_list").html(data);
	});
}
function _ddf_delete(id) {
    if(!confirm('Do you really want to delete this field?')) {
        return;
    }
    var params = new Object();
    params.action = "ddf_field_delete";
    params.id = id
    jQuery.post('admin-ajax.php', params ,function(data) {
		jQuery("#field_list").html(data);
	});
}