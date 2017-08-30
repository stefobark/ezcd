<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/*
Plugin Name: Events by Zipcode Distance
Plugin URI:  https://webbarker.biz/ezcd/
Description: Manage events and provide zipcode search functionality
Version:     0.01
Author:      Steven Barker
Author URI:  https://webbarker.biz
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function ezcdDeactivate(){
	global $wpdb;
	$ezcdName =  $wpdb->prefix . "ezcd";
	$distName = $wpdb->prefix . "waZipDist";
	$sql = "DROP TABLE IF EXISTS $ezcdName";
	$sqlTwo = "DROP TABLE IF EXISTS $distName";

    $wpdb->query($sql);
    $wpdb->query($sqlTwo);

}
//add all the things [scripts and styles]
ezcdAdd();

//are we talking to ourselves?
add_action('init','check');

//did they tell us to take in the wooCommerce products
if(isset($_POST['loadWoo'])){
	add_action('init','loadWooData');
} 

//register our menu page.
function ezcdRegisterMenu() {
     add_menu_page(__('EZCD','ezcd'), __('EZCD','ezcd'), 'manage_options', 'ezcd', 'makePage' );
}

//bring all the fancy extra scripts from various jquery plugins
function dataTables_scripts() {   
    wp_enqueue_script( 'dataTables', 'https://cdn.datatables.net/v/bs/dt-1.10.13/r-2.1.0/sc-1.4.2/datatables.min.js' );
	wp_enqueue_script('responsive', 'https://cdn.datatables.net/responsive/1.0.7/js/dataTables.responsive.min.js');
}

//bring all the fancy extra styles from various jquery plugins
function dataTables_styles() {   
    wp_enqueue_style( 'dataTables', 'https://cdn.datatables.net/v/bs/dt-1.10.13/r-2.1.0/sc-1.4.2/datatables.min.css"/' );
}

//is woocommerce is active?
function isWooActive(){
	if ( class_exists( 'WooCommerce' ) ) {
	  return true;
	} else {
	  return false;
	}
}


//this actually adds the extra css and js
function ezcdAdd(){
	
	//build / destroy tables and do other necessary things to initialize and destroy ezcd
	register_activation_hook( __FILE__, 'buildTables' );
	register_deactivation_hook( __FILE__, 'ezcdDeactivate' );
	
	//build menu bar option thing
	add_action('admin_menu','ezcdRegisterMenu');

	//our ajax jquery stuff is brought in
	add_action('wp_enqueue_scripts', 'loadAjaxScripts');
	
	add_action('wp_enqueue_scripts', 'dataTables_scripts');
	add_action('wp_enqueue_styles', 'dataTables_styles');

	//add shortcode for putting the searchbar anywhere
	add_shortcode('searchBar','searchBar');

}

//returns an array of event info for events that are within range of the zip and distance chosen by user
function doZipRadiusSearch($zipO,$miles,$eventName){
	global $wpdb;

	$ezcdTableName = $wpdb->prefix . "ezcd";
	$distTable_name = $wpdb->prefix . "waZipDist";
	
	//get event row from ezcd table (not the distance table) that matches 
	//event names from the user input.
	$zipQuery = "SELECT * 
				 FROM $ezcdTableName 
				 WHERE `eventName` = '$eventName'";
				 
	//run the query so we can grab the 
	$zipResults = $wpdb->get_results($zipQuery);
	$zipOfEvent = $zipResults[0]->zip;

	//now we should have the zipcode of the event, the zipcode that the
	//user chose, and the radius the user is searching within. so, we now
	//build the query that grabs all zipcodes withing $miles of $zipOfEvent
	$distQuery = "	SELECT * 
					FROM $distTable_name 
					WHERE zipO = $zipO 
					AND distance < $miles
				";
	//we should be now be grabbing a list of zipcodes that are within the range
	//specified by the user. then, we can use this to see if our event's
	//zipcode is within this list
	$goodZips = $wpdb->get_results($distQuery);
	
	//so we now have a list of good zipcodes. let's go through that list
	//and find events from the ezcd event table that have good zips and
	//if they also match the eventName specified by the user in the form,
	//then, shove all the information about that event into the array
	//we will eventually convert this array to json, and give a json response 
	//containing all the good data so we can give it to dataTables() and 
	//provide a pretty way to interact with the data.
	$allGood = '';
	foreach($goodZips as $goodZip){
		$allGood .= $goodZip->zipD.",";
	}
	
	$allGood = rtrim($allGood, ",");
	$nearbyEventsQuery = "	SELECT * 
							FROM $ezcdTableName 
							WHERE zip in($allGood)
							AND `eventName` = '$eventName'";
							
	$nearbyEvents = $wpdb->get_results($nearbyEventsQuery);
	
	
	echo json_encode( $nearbyEvents );
	
    wp_die(); // this is required to terminate immediately and return a proper response

}

function loadAjaxScripts() {
	$scriptPath = '/wp-content/plugins/ezcd/main.js';
	//register it for safety
	wp_register_script('ajaxScript',$scriptPath);
	wp_enqueue_script('jquery');
	// load our jquery file that sends the post request
	wp_enqueue_script( "ajaxScript" );
	wp_localize_script( 'ajaxScript', 'ajaxObject', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));
	add_action('wp_ajax_doZipRadiusDistSearch','doZipRadiusSearch');

}

//this is going to display the eventname dropdown, a 'miles' input, and a 'zip' input
function searchBar(){
	
	$eventNames = getDropDownEventNames(getEvents());
	
	$html = "
					<form id='ezcdSearchForm' action=''>
						<div class='form-group'>
							<label for='eventNames'>Available Courses:</label>
							<select class='form-control' id='eventNames'>
							$eventNames
							</select>
						</div>
						<div class='form-group'>
							<input type='number' class='form-control' placeholder='Miles' id='miles'/>
						</div>
						<div class='form-group'>
							<input type='text' class='form-control' pattern='^\d{5}$' placeholder='Zipcode' id='zip'/>
						</div>
						<button type='button' id='searchButton' class='btn btn-primary'>Search</button>
					</form>
			<table id='results'></table>";
	return $html;
}

function loadWooData(){
	global $wpdb;
	$ezTable =  $wpdb->prefix . "ezcd"; 
	
	//load 'er on up
	$allProducts = getWooProductDeets();
	
	foreach($allProducts as $p){
		foreach($p as $product){
			echo "<div style='margin-left:200px;'>";
			echo "<h3>".$product['eventName']."</h3></div>";
			$wpdb->insert($ezTable, $product);
		}
	}
}

function check(){
		
	if(isset($_GET['eName'])){
		$zipO = $_GET['zCode'];
		$miles = $_GET['milesFrom'];
		$eventName = $_GET['eName'];
		doZipRadiusSearch($zipO,$miles,$eventName);
	} 


	//checking to see if we got a request to create a new event. if so,
	//create it.
	if(isset($_POST['eventName'])){
		global $wpdb;
		$table_name =  $wpdb->prefix . "ezcd"; 
		$wpdb->insert(
			$table_name,
			array('eventName' => $_POST['eventName'],
				  'description' => $_POST['description'],
				  'date' => $_POST['date'],
				  'address' => $_POST['address'],
				  'zip' => $_POST['zipcode'],
				  'url' => $_POST['url'],
				  'price' => $_POST['price'],
				  'seats' => $_POST['seats']
				  )
		);
	}

	//checking to see if we got a request to load the actual data into the table
	//we don't do this during plugin installation because it can take FOREVER
	if(isset($_POST['loadData'])){
		populateZipTable();
	}
}
 

//this is the function that gets called during installation of ezcd. 
//it builds two tables, one to manage events, another to keep track of distances
//between zip codes.for now, it is only washington.. to keep the data smaller during development.
function buildTables(){
	ezcdTable();
	waZipTable();
}

//create the table
function waZipTable(){
	global $wpdb;
	$table_name =  $wpdb->prefix . "waZipDist"; 
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  zipO varchar(55) NOT NULL,
	  zipD varchar(55) NOT NULL,
	  distance varchar(55),
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}	 

//add data to the table. for now only one state.
//soon, we may add all zips to a master table. orrrrr.. keep them separate
//eventually, I'm going to offload all this geodistance stuff to my own 
//server and expose an api. just doing it here now to save time.
function populateZipTable(){
	global $wpdb;	
	
	//turn big csv into array
	$csv = file_get_contents(plugin_dir_path(__FILE__).'WA_OR_ID.csv');
	$lines = explode(PHP_EOL, $csv);
	
	
	$table_name =  $wpdb->prefix . "waZipDist"; 
	$count_query = "select count(*) from $table_name";
	$num = $wpdb->get_var($count_query);
	
	//check that they haven't already loaded
	if($num == 0){
		foreach ($lines as $line) {
			if($line != NULL){
				$row = str_getcsv($line);
				//loop through all zipcode rows again to check all distances
				foreach($lines as $nextRow){
					$nextRow = str_getcsv($nextRow);

					if($nextRow != NULL ){
						//use haversine function to get distance between two zips (lat/long)
						//$row[0] is the name of the zip which we're checking everythign against
						//$nextRow 
						$dist = getDistance($row[1],$row[2],$nextRow[1],$nextRow[2]);
						$zipD = $nextRow[0];
						$zipO = $row[0];
						//building data array to insert into the db table
						$data = array("zipO" => $zipO,"zipD" => $zipD,"distance" => $dist);
						$wpdb->insert( $table_name, $data );
					}
				}
			}
		}
	}
}


//this is a php implementation of the haversine function used to get distances
//between two points on a globe.
function getDistance($latitude1, $longitude1, $latitude2, $longitude2) {
	
	$earth_radius = 6371;
 
	$dLat = deg2rad($latitude2 - $latitude1);
	$dLon = deg2rad($longitude2 - $longitude1);
 
	$a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);
	$c = 2 * asin(sqrt($a));
	$d = $earth_radius * $c;
 
	return $d;

}

//we use this to make sure that the product is actually an ezcd event
//returns true or false after we pass in the 'post', or 'product' (products
//are just a type of post)
function checkTag($product){
	$tags = $product->get_tags();
	//is it an ezcd product?
	if (strpos($tags, 'ezcd') !== false) {
		return true;
	} else {
		return false;
	}
}

//check for new products.. make sure that, if they are tagged 'ezcd', we update our ezcd table
add_action( 'draft_to_publish', 'my_product_update' );
function my_product_update( $post ) {
	global $wpdb;
	$ezTable =  $wpdb->prefix . "ezcd"; 

    if ( $post->post_type == "product" ) {
		if(checkTerms($post)){
			$wpdb->insert($ezTable, $post);
		}
    }
}

//a variation on the tag checking process for 'my_product_update'
function checkTerms($post){
	$terms = get_the_terms( $post->ID, 'product_tag' );

	$aromacheck = array();
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
		foreach ( $terms as $term ) {
			$aromacheck[] = $term->slug;
		}
	}

	/* Check if it is existing in the array to output some value */

	if (in_array ( "value", $aromacheck ) ) { 
	   return true;
	} else {
		return false;
	}
}

//get product details.
function getWooProductDeets(){
		$args = array( 'post_type' => 'product', 'posts_per_page' => 1000 );
		
		//hit up WP for the products, which are really just posts.
		$loop = new WP_Query( $args );
		$allTheProductDeets = array();
		$arrayIndex = 0;
		//go through all products
		while ( $loop->have_posts() ) : $loop->the_post(); 
			global $product;
			//check that this product has an 'ezcd' tag... then use it if so 
			if(checkTag($product)){
				$fullAddress = $product->get_attribute( 'address' );
				$zipCode = $product->get_attribute( 'zipcode' );
				$dateOfClass =  $product->get_attribute( 'date' );
				$url = get_permalink();
				$productDescription = $product->post->post_content;
				$thumb = woocommerce_get_product_thumbnail();
				$title = get_the_title();
				$price = $product->get_price_including_tax(1,$product->get_price);
				$quantity = $product->get_stock_quantity();
				
				//I can't remember why i did this.. maybe i thought that 
				//old versions of productDeets were creeping up???
				unset($productDeets);
				
				//collect the deets
				$productDeets[] = array('eventName' => $title,
										  'address' => $fullAddress,
										  'zip' => $zipCode, 
										  'url' => $url,
										  'description' => $productDescription,
										  'date' => $dateOfClass,
										  'price' => $price,
										  'seats' => $quantity);
				//now shuv it on in thar						  
				$allTheProductDeets[] = $productDeets;
			}
		endwhile; 
		return $allTheProductDeets;
	}

function getWooDeetsHTML(){
		$chunkSpit = '<h3>wooCommerce is active</h3>
				<h4>so, we let it handle event management</h4>';
		
		$chunkSpit .= "<div class='row' style='background-color:rgba(255,255,255,0.8);max-height:250px;padding:25px;position:static;overflow:scroll;'><h3>Events</h3>";
		$wooProductDeets = getWooProductDeets();
		
		if(!isset($wooProductDeets)){
			$chunkSpit = "uh oh. we don't have wooCommerce details that we need.";
		} else {
			foreach($wooProductDeets as $key => $value){
				$chunkSpit .= "<h3>$key</h3><ul>";
				foreach($value as $vKey => $vValue){
					foreach($vValue as $finalValue){
						$chunkSpit .= "<li>$finalValue</li>";
					}
				}
				$chunkSpit .= '</ul>';
			}
		}
		$chunkSpit .= "</div><form method='POST' action=''>
						<input type='hidden' id='loadWoo' name='loadWoo'>
						<button class='btn btn-default' type='submit' >Load wooCommerce Product Data</button>
					</form>";
					
		return $chunkSpit;
	}
	

//echoing out the actual html for the admin page.
function makePage(){
	 $events = getEvents();
	 $eventInfo = getEventInfo($events);
	 if(isWooActive()){
		$chunkSpit = getWooDeetsHTML();
		//wp_reset_query(); 
	 }
	 $s = searchBar();
	 echo $s;
	 $eventNames = getDropDownEventNames($events);
	
	 echo  "
				<h1>" . __( 'EZCD', 'ezcd' ) . "</h1>
				
				$chunkSpit
						<h3>Load ZipCode Distances Table</h3>
						<p>
							This will create a table of distances between all zipcodes in
							Washington, Oregon and Idaho. It will take a loooooong time to do all the calculations.
						</p>
						<form method='post' action=''>
							<input type='hidden' name='loadData' value='1' />
							<input id='loadDataButton' type='submit' value='Load Data' />
					        </form>";

				//if woocommerce is active, we'll just get rid of the event creation form
				if(!isWooActive()){
					echo "
					<h3>Create Event</h3>
					   <div style='margin-left:25px;' id='createEvent'>
						   <form method='post' action=''>
							   Event Name:<br><input type='text' name='eventName' id='eventName'></input><br>
							   Description:<br><input type='text' id='description' name='description'></input><br>
							   Date and Time:<br><input type='datetime-local' name='date' id='date'></input><br>
							   Street Address:<br><input type='text' name='address' id='address'></input><br>
							   Zipcode:<br><input type='text' name='zipcode' id='zipcode'></input><br>
							   Event Page URL:<br><input type='text' name='url' id='url'></input><br>
							   Price:<br><input type='text' name='price' id='price'></input><br>
							   Seats:<br><input type='text' name='seats' id='seats'></input><br>
							   <input type='submit' value='Create'></input>
						   </form>
					   </div>";
				   }
					echo "
					
					   <h3>Events</h3>
					   <ul>
						$eventInfo
					   </ul>";
 }
 
 //returns a chunk of html for bootstrap dropdown on the event name selector for zip radius search
 function getDropDownEventNames($events){
	 $allEventNames = '';
	 foreach($events as $event){
		 $allEventNames .= "<option>$event->eventName</option>";
	 }
	 return $allEventNames;
 }
 
 //this actually queries the db and gets all the info about all the events
function getEvents(){
	global $wpdb;
	$table_name =  $wpdb->prefix . "ezcd"; 
	$query = "SELECT * FROM $table_name";
	return $wpdb->get_results($query);
}

//this spits out a chunk of html for event info
function getEventInfo($events){
	$eventList = '';
	foreach ( $events as $event ) 
		{
			$eventList .= "<li> id: <strong>" . $event->id . "</strong>, event name: <strong>" . $event->eventName . "</strong>, event date: <strong>" . $event->date . "</strong>, address: <strong>" . $event->address . "</strong>, seats: <strong>" . $event->seats . "</strong>, zip: <strong>" . $event->zip . "</strong></li>";
		}
	return $eventList;
}

//creating the table for events. this is called during plugin activation.
function ezcdTable() {
    global $wpdb;

    $table_name =  $wpdb->prefix . "ezcd"; 

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  eventName text NOT NULL,
	  description text NOT NULL,
	  date datetime NOT NULL,
	  address varchar(55) NOT NULL,
	  zip varchar(55) NOT NULL,
	  url varchar(55),
	  price varchar(55) NOT NULL,
	  seats mediumint(9) NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	//insert a test event just to show that things are working.
	$wpdb->insert( 
		$table_name, 
		array( 
			'eventName' => 'Test Event', 
			'description' => 'This is where event description is.', 
			'date' => '2016-12-23',
			'address' => '1121 Rigoroli ST', 
			'zip' => '98502',
			'url' => 'http://test.com/testEvent',
			'price' => '99',
			'seats' => '30'
		) 
	);
}
