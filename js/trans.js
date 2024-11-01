jQuery(document).ready(function(){
   jQuery("input[name=payment_method]:radio").on("click",function () { 
       if(jQuery(this).val()==='transax'){
          if(jQuery(this).parent().children('.creditcard').length === 0){
             jQuery(this).parent().append('<div class="creditcard"><label>CC NUMBER</label><input type="text" name="ccnumber" id="ccnumber" /><div class="error"/><label>CC Expiration Date</label><select id="ccexpmonth"><option value="" selected disabled>Month</option><option value="01">01</option><option value="02">02</option><option value="03">03</option><option value="04">04</option><option value="05">05</option><option value="06">06</option><option value="07">07</option><option value="08">08</option><option value="09">09</option><option value="10">10</option><option value="11">11</option><option value="12">12</option></select><select id="ccexpyear"><option value="" selected disabled>Year</option>'); 
			 var currentyear = (new Date()).getFullYear();
			 for (var i = 0; i<=10; i++)
			 {
				 jQuery(this).parent().append('<option value="' + currentyear + '">' + currentyear + '</option>' );
				 currentyear++;
			 }
			 jQuery(this).parent().append('</select><input type="hidden" name="ccexpirydate" id="ccexpirydate"/><div class="error"/><label>CVV Number</label><input type="text" name="cvvnumber" id="cvvnumber" maxlength="4" /><div class="error"/></div>');

         } 
       }else{
           jQuery('.payment_method_transax').children('.creditcard').remove();
       } 
   });
   
   //Start Validation
   jQuery('#ccnumber,#cvvnumber').on("keypress",function(e){
       if (e.which !== 8 && e.which !== 0 && (e.which < 48 || e.which > 57)) {
        jQuery(this).next('.error').html('Only Number Allowed');
        return false;
       }else{
        jQuery(this).next('.error').html('');
       }
   });
   
   jQuery('#ccexpmonth','#ccexpyear').on("change", function(){
	   var month = jQuery('#ccexpmonth').val();
	   var year = jQuery('#ccexpyear').val();
	   if(month != '' and year != '') {
		   jQuery('#ccexpirydate').val(month+year.toString());
	   }
   });
});