<?php
include 'includes.php';
//First draw 2002-04-22
$db_guide = new guide_DB();
$message="";



$result = $db_guide->executeSql('SELECT MAX(dates) FROM rawdata'); // Latest date from raw data that has been inserted
$result_date = $db_guide->executeSql('SELECT count(dates) FROM rawdata where dates=(SELECT MAX(dates) FROM rawdata)'); //Check if we have earlier lottery for same day
$result_date  = $result_date[0][0];
/*
echo strtotime(date("Y-m-d"));
echo "<br>";
echo strtotime($result[0][0]);
echo "<br>";
echo strtotime("16:00:00");
echo "<br>";
//echo strtotime("now");
echo date("H:i:s");
exit();
*/
//if(strtotime($result[0][0])<= strtotime(date("Y-m-d")))
echo "<html><head></head><body>";
//<meta http-equiv='refresh' content='3'>
if($result[0][0]=="") //Empty database, first day that Keno was played
  $result[0][0]="2002-04-21";
$result[0][0] = strtotime($result[0][0]);
$result[0][0] = $result[0][0] + 90000; // +1 day in seconds
$result[0][0] = date("d-m-Y",$result[0][0]);
$results = explode("-",$result[0][0]);

$week = date('W',mktime(0, 0, 0, $results[1], $results[0], $results[2])); //mktime(month,day,year)
$year = date('Y',mktime(0, 0, 0, $results[1], $results[0], $results[2])); //mktime(month,day,year)

if ($results[1] == 1 && $week >= 52)
   $year--; //Some 52nd weeks go past new year

if ($week == 1 && $results[0] > 20)
	$year++; //If weeks run out before days

$url = 'https://www.veikkaus.fi/mobile?area=results&game=keno&op=link_search&type=round&year1='.$year.'&year2='.$year.'&round1='.$week.'&round2='.$week.'&results_of=&comesfrom=results&search=all';

echo $url;
exit();

function disguise_curl($url)
{
  $curl = curl_init();

  $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
  $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
  $header[] = "Cache-Control: max-age=0";
  $header[] = "Connection: keep-alive";
  $header[] = "Keep-Alive: 300";
  $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
  $header[] = "Accept-Language: fi-fi, fi;q=0.5";
  $header[] = "Pragma: "; // browsers keep this blank.

  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; fi; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB6 (.NET CLR 3.5.30729)');
  curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
  //curl_setopt($curl, CURLOPT_REFERER, 'http://www.google.com');
  curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
  curl_setopt($curl, CURLOPT_AUTOREFERER, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_TIMEOUT, 10);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

  $html = curl_exec($curl); // execute the curl command
  curl_close($curl); // close the connection

  return $html; // and finally, return $html
} // end of function

// uses the function and displays the text off the website
$text = disguise_curl($url);

if(strpos($text,'<!-- keno_round_result_area.vm START -->') !== FALSE) { //See if we have results
	$table = explode('<!-- keno_round_result_area.vm START -->',$text); //Split one day results
}

foreach ($table as $val) { //val is one day results
	$tmp = explode('Oikea rivi ',strip_tags($val));
	$linedate = preg_split('/[\s]/',trim($tmp[0])); //Get the date information

	if ($linedate[0] == "Arvonta") { //There is some garbage code at the beginning
		$time_of_day=$linedate[4];
	    $tmpdate = explode(".",$linedate[count($linedate)-3]); //Date is in the third last cell
	    $linedate = $tmpdate[2]."-".$tmpdate[1]."-".$tmpdate[0]; //Rearranging to year-month-day
	    if($time_of_day == "Ilta-arvonta") {
            if(isset($tmp[1]))
                $rows[$linedate][0] = $tmp[1]; //Insert results for later use to cells identified by date
        } else {
            if(isset($tmp[1]))
                $rows[$linedate][1] = $tmp[1]; //Insert results for later use to cells identified by date
        }
	}
}//end of foreach ($table as $val) {

if(isset($rows[$linedate][0])){       //Check is done because earlier there were only one game per day
	$tmp = explode(" &#60;", $rows[$linedate][0]); //Trim garbage at the end, splitting from char <
	$rows[$linedate][0] = $tmp[0]; //Last results
} else {
    $tmp = explode(" &#60;", $rows[$linedate][1]); //Trim garbage at the end, splitting from char <
    $rows[$linedate][1] = $tmp[0]; //Last results
}

//	$results[2] = $results[2]; //The last date in database
$results[1] = $results[1]*1; //Day is presented without preceeding 0
$origdate = $results[2]."-".$results[1]."-".$results[0];

$pattern='/[\s]/';
$replace=",";
$pattern2='/[,]+/';
$replace2=",";

foreach($rows as $key=>$val) {
	for($x=0;$x<2;$x++) {								//x is the number of draws now twice a day
			if(strtotime($origdate) <= strtotime($key)) {	//if result_date is 1 here it means we have result for this day
				if($result_date==1 && strtotime($key) > strtotime('2011-04-11'))    { //Date after when they started having 2 draws per day
					$result_date = 0;
					$x=1;
				}

				$values = preg_replace($pattern, $replace, $val[$x]); //Replaces whitespaces and linefeeds but produces extra , chars
				$values = preg_replace($pattern2, $replace2, $values); //Removes multiple , chars but still first and last char is ,
				$values = substr($values, 1, -1);
	
				$tmp_values = explode(',',$values);

				if(count($tmp_values)>20)	{
					//extract the numbers from random garbage	
					array_splice($tmp_values, 20);
					$values = implode(',',$tmp_values);
				}
				$sql="INSERT INTO rawdata (id,dates,num1,num2,num3,num4,num5,num6,num7,num8,num9,num10,num11,num12,num13,num14,num15,num16,num17,num18,num19,num20)";
				$sql.=" VALUES(NULL,'$key',$values);";						
				
				if(strtotime($key) < strtotime('2011-04-11')) 
					$x=2;
                 
				$result = $db_guide->executeSql($sql);
				if($result === "error") {
					echo "<br>Error inserting ".$key."<br>";
					echo "Error: ".$result." ".$db_guide->getError()."<br>".$sql;
					exit();
				} else {
					echo "<br>Successfully inserted ".$key;
				}
			}  else {
				echo "<br>Date comparison failed ".$key;
			} //end of if(strtotime($origdate...
        }//end of for
    } // end of foreach

	echo "<script type='text/javascript'>
location.replace('http://localhost/kenonaattori/update.php?id=".$key."');
</script>";
echo "</body></html>";
//header("Location: http://localhost/kenonaattori/update.php?id=".$key);
/*
Miten voidaan tarkistaa ja varmistaa että joka päivällä on vain kaksi merkintää
Mahdollistaako ehtolause
if(strtotime($origdate) <= strtotime($key)) {
  päällekkäisyyksiä nyt kun unique id on juokseva numero

  ALTER TABLE rawdata AUTO_INCREMENT=12655

*/

?>
