<?php
add_action( 'wp_ajax_my_action', 'my_action_callback' );

if(isset($_GET['eName'])){
	$zipO = $_GET['zCode'];
	$miles = $_GET['milesFrom'];
	$eventName = $_GET['eName'];
	}
	

function my_action_callback() {
	global $wpdb; // this is how you get access to the database
	$json = doZipRadiusSearch($zipO,$miles,$eventName);
	echo $json;
	wp_die(); // this is required to terminate immediately and return a proper response
}

//returns an array of event info for events that are within range of the zip and distance chosen by user
function doZipRadiusSearch($zipO,$miles,$event){
	$ezcdTableName = $wpdb->prefix . "ezcd";
	$distTable_name = $wpdb->prefix . "waZipDist";
	$zipQuery = "SELECT zip FROM $ezcdTableName WHERE eventName = $event";
	$zipD = $wpdb->get_row($zipQuery);
	$distQuery = "SELECT zipD $distTable_name WHERE zipO = $zipO AND miles < $miles";
	$goodZips = $wpdb->query($distQuery);
	$nearbyEvents = array();
	$countRows = 0;
	$countCols = 0;
	foreach($goodZips as $goodZip){
		$nearbyEventsQuery = "SELECT * FROM $ezcdTableName WHERE zip = $goodZip";
		$row = $wpdb->get_row($nearbyEventsQuery);
		foreach($row as $colKey => $colVal){
			$nearbyEvents[$countRows][$colKey] = $colVal;
			$countCols++;
		}
		$countRows++;
	}
	return $nearbyEvents;
}
?>
