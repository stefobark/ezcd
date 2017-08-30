<?php
/*
 * this is my server's code. i'm gonna use this to create a geo data database.
 * then expose an api to allow for quick zipcode radius search in WA OR and ID
 * eventually, it'll be the whole US. I want to take little steps to be sure
 * what i'm doing makes sense. 
*/

	$servername = "localhost";
	$username = "webbarke_geo";
	$password = "bucket12";
	$dbname = "webbarke_zipDist";
	

	function createZipTables($pdo){
		$files = scandir(getcwd()."/wa_or_id");
		foreach($files as $zip){
			$table = preg_replace('/[^0-9]/','',$zip); //the file name without anything but numbers.. in other words, the table name is the zipcode
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			$csv = array_map(function($v){return str_getcsv($v, "\t");}, file("$zip"));
			foreach($csv as $row){
				print_r $row;
			}
		}
	}
	
	function csv_to_array($filename='', $delimiter=',')
{
    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
        {
            if(!$header)
                $header = $row;
            else
                $data[] = array_combine($header, $row);
        }
        fclose($handle);
    }
    return $data;
}
	
	function buildZipTables(){
	
		//we're going to create a table for each zip
		global $wpdb;
		
		//turn csv into an array
		$csv = csv_to_array('WA_OR_ID.csv','\t');
		//loop through all rows
		foreach($csv as $row){

			$table_name =  $row[0]; 

			$charset_collate = $wpdb->get_charset_collate();
			
			//the table name is the zipcode. so, we'll search for the 
			//table by zip code then enter the destination and distance
			$sql = "CREATE TABLE $table_name (
			  id mediumint(9) NOT NULL AUTO_INCREMENT,
			  zipD varchar(55) NOT NULL,
			  dist varchar(55)
			  PRIMARY KEY  (id)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			//loop through all zipcode rows again to check all distances
			foreach($csv as $nextRow){
				
				//use haversine function to get distance between two zips (lat/long)
				//$row[0] is the name of the zip which we're checking everythign against
				//$nextRow 
				$dist = getDistance($row[1],$row[2],$nextRow[1],$nextRow[2]);
				
				//building data array to insert into the db table
				$data = array("zipD" => $nextRow[0],"dist" => $dist);
				$wpdb->insert( $table_name, $data );
				//echo "distance between $row[0] and $nextRow[0] is $dist \n";
			}
		}
	}
	
	function getDistance($latitude1, $longitude1, $latitude2, $longitude2) {
		
		$earth_radius = 6371;
	 
		$dLat = deg2rad($latitude2 - $latitude1);
		$dLon = deg2rad($longitude2 - $longitude1);
	 
		$a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);
		$c = 2 * asin(sqrt($a));
		$d = $earth_radius * $c;
	 
		return $d;
    
	}
?>
