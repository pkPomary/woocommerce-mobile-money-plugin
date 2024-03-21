(function($) {

    $(document).ready(function () {
        $(document.body).on('updated_checkout', function() {
            var defaultValue = $('#mm_operator_field select').val();
            checkValue(defaultValue);
    
            $('#mm_operator_field select').on('change', function() {
               checkValue(this.value);
            });
        })
    })

    function checkValue(value){
        var code;
        if(value == "MTN Money"){
            code = mmpayment_data.mtnmoney_ussd_code;
        }
        if(value == "Telecel Cash"){
            code = mmpayment_data.telecelcash_ussd_code;
        }
        if(value == "AT Money"){
            code = mmpayment_data.atmoney_ussd_code;
        }
        if(code != ""){
            var message = "Dial <b>" +  code + "</b>";
            $("#mm_instruction").html(message);
        }
    }

})( jQuery );
