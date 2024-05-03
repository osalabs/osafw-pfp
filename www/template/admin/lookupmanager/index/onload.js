    var mode = '<~f[mode]>';
var is_readonly = '<~is_readonly>';
if (is_readonly=='True') return;

$(document).on('click', '.on-mode-switch', function(e){
  var value = $(this).val();
  $('#FFilter').find('input[name="f[mode]"]').val( value );
  $('#FFilter').submit();
});

if (mode){
    //table edit mode
    var $tedit = $('.table-edit');
    $tedit.data('newid',0);

    $tedit.on('click', '.on-row-del', function (e) {
        e.preventDefault();
    //    if (!confirm('Are you sure to delete the row?')) return;

        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        if ($tr.data('isnew')){
            //new row - remove
            $tr.remove();
        }else{
            //existing record - mark to delete
            $tr.prepend('<input type="hidden" name="del['+id+']" value="1">');
            $tr.slideUp();
        }
        $('#DataF .btn-primary').prop('disabled', false);
    });

    //submit checkbox unchecked/off value
    $tedit.on('change', 'input[type=checkbox]', function (e) {
        var $this = $(this);
        var name = $this.attr('name')
        if (name.startsWith('fnew')) return; //not needed for new items

        if (!$this.prop('checked'))
            $this.after('<input type="hidden" name="'+name+'" value="0">');
        else
            $this.next('input[name="'+name+'"]').remove();
    });

    $(document).on('click', '.on-cancel', function (e) {
        //just re-read page
        window.location.reload();
    });

    $tedit.on('keyup change', ':input', function (e) {
        $('#DataF .btn-primary').prop('disabled', false);
    });
    $tedit.on('click', 'input[type=checkbox]', function (e) {
        $('#DataF .btn-primary').prop('disabled', false);
    });
    $tedit.on('change', 'select', function (e) {
        $('#DataF .btn-primary').prop('disabled', false);
    });

    $(document).on('click', '.on-add-new', function (e) {
        e.preventDefault();
        add_new_row();
    });
    add_new_row(); //by default - add new empty row

    $tedit.find('input[type=text]:visible:first').focus();

}else{
    //list mode
    //make_table_list(".list");
}

function add_new_row(){
    //add empty row
    var nextid = $tedit.data('newid')+1;
    $tedit.data('newid', nextid);

    var $tpl = $tedit.find('.tpl').clone().removeClass('tpl d-none');
    $tpl.data('id', nextid);
    $tpl.data('isnew', true);
    $tpl.find('input.rowid').prop('name', 'new['+nextid+']');
    $tpl.find(':input[name^=fnew0]').each(function(index, el) {
        el.name = el.name.replace(/fnew0/,'fnew'+nextid);
    });
    $tedit.find('tbody').append($tpl);

    $tpl.find('input[type=text]:visible:first').focus();

    //calendar (datepicker) component
    $tpl.find('.date').datepicker({format: 'mm/dd/yyyy'})
        .on('changeDate', function (e) {
            if (e.viewMode=='years' || e.viewMode=='months') return; //do not trigger change yet, while user selecting year/month
            $(this).find('input').trigger('change');
        });
}