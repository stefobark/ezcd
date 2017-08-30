jQuery(document).ready(function($){
	

	$('#searchButton').on('click',function(){
		getResults();
	});
	
	$('#loadWoo').on('click',function(){
		loadWoo();
	  });
});

function loadWoo(){
	myUrl = ajaxObject.ajax_url;
	jQuery.ajax({
		  url : myUrl,
		  action: 'loadWooData',
		  success: function(result){
		  }
	  });
}

function getResults(){
	
		
		myUrl = ajaxObject.ajax_url;
		send = new Object();
		send.eName = jQuery("#eventNames").find(":selected").text();
		send.milesFrom = jQuery("#miles").val();
		send.zCode = jQuery("#zip").val();
		jQuery.ajax({
			  url : myUrl,
			  action: 'doZipRadiusSearch',
			  data: send,
			  dataType:"json",
			  success :
			  function(result){ 
	 				    table = jQuery('#results').DataTable( {
						data: result,
						retrieve: true,
						"columns": [
							{ "data": "eventName", "title":"Class" },
							{ "data": "date", "title":"Date", "render":function(data,type,full,meta){
									var split = data.split(" ");
									var date = convertDate(split[0]);
									return date;
								},
							},
							{ "data": "zip", "title":"Zip"},
							{ "data": "price", "title":"Price"},
							{ "data": "seats", "title":"Seats Available"},
							{ "data": "url", "title":"Course Page", "render": function ( data, type, full, meta ) {
										return '<a href="'+data+'">Learn More</a>';
									}
							}
						],
						"paging":   false,
						"ordering": false,
						"info":     true,
						"responsive" : true
					});
					console.log(result);
					table.destroy();
			  },
			  error :
			  function(xhr,status,error){
					console.log(xhr);
					console.log(status);
					console.log(error);
			   }
		});
	}
	
function convertDate(date){
	
	var dateBreak =  date.split("-");
	var year = dateBreak[0];
	var month = dateBreak[1];
	var day = dateBreak[2];
	
	switch(month) {
    case "12" :
        month = "DEC";
        break;
    case "11" :
        month = "NOV";
        break;
    case "10" :
        month = "OCT";
        break;
    case "09" :
        month = "SEP";
        break;
    case "08" :
        month = "AUG";
        break;
    case "07":
        month = "JUL";
        break;
    case "06" :
        month = "JUN";
        break;
    case "05" :
        month = "MAY";
        break;
    case "04" :
        month = "APR";
        break;
    case "03" :
        month = "MAR";
        break;
    case "02" :
        month = "FEB";
        break;
    case "01" :
        month = "JAN";
        break;
	}
	
	return month + " " + day + ", " + year;
}
