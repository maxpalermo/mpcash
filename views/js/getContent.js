$(document).ready(function(){
    $("[id^='input_text_']").each(function(){
        //console.log('input text: ' + this.id);
        $(this).attr('onblur', 'twoDec(this);');
        $(this).attr('onfocus', 'selectAll(this);');
        
        $(this).val(Number(this.value).toFixed(2));
        //console.log('value = ' + this.value );
    });
    
    $("select[id*='_select_']").each(function(){
            //console.log('formatting ' + this.id);
            var result_text = "{l s='No match:' mod='mpCash'}";
            
        if (
                this.id !== 'input_select_fee_type' &&
                this.id !== 'input_select_tax_rate' &&
                this.id !== 'input_select_id_order_state' &&
                this.id !== 'input_select_carriers'
            )    
            $(this).attr('multiple','');
            
            $(this).chosen({ no_result_text: result_text, width : "350px" });
            if(String(this.name).indexOf('[]')>0) {
                var input_id = '#' + String(this.name).replace('[]','_hidden');
                var hidden_values = $(input_id).val();
                console.log('multiple values for ' + this.name + ': ' + $(input_id).val());
                var values = String(hidden_values).split(',');
                
                $(this).val(values).trigger('chosen:updated').change();
            }
        });
});

function twoDec(element)
{
    //console.log("formatCurrency element: " + element.id);
    $(element).val(Number(element.value).toFixed(2));
    //console.log("value: " + element.value);
}

function selectAll(element)
{
    //console.log("selectAll element: " + element.id);
    $(element).select();
}