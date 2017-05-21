<?php
	// if your 'plugin' directory is not in the default location,
	// edit this variable to point to your site directory
	// (where config.php is)
	$strRel = '../../../../';

	include($strRel . 'config.php');
	if (!$member->isLoggedIn())
		doError('You\'re not logged in.');

		$path = ini_get('include_path');
		if (!strstr($path, $DIR_PLUGINS . 'pear')) {
			ini_set('include_path', $path . PATH_SEPARATOR . $DIR_PLUGINS . 'pear');
		}
		$path = ini_get('include_path');
		if (!strstr($path, $DIR_LIBS . 'pear')) {
			ini_set('include_path', $path . PATH_SEPARATOR . $DIR_LIBS . 'pear');
		}

		$googlemaps = $manager->getPlugin("NP_GoogleMaps");

?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja-JP" lang="ja-JP">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Test Geocode</title>
</head>
<body>
<form action="index.php" method="post">
<table>
<tbody>
<tr><td>Address</td><td><input type="text" name="address" size="60" /></td></tr>
<tr><td>Country</td><td><input type="text" name="country" size="10" value="jp" /></td></tr>
<tr><td><input type="submit" /></td></tr>
</tbody>
</table>
</form>
<table border="1">
<thead>
<tr>
<th>Geocoder</th>
<th>Accuracy</th>
<th>Latitude</th>
<th>Longitude</th>
<th>Address</th>
<th>Original Address</th>
<th>Country</th>
</tr>
</thead>
<tbody>
<?php
	$address = requestVar('address');
	$country = requestVar('country');
	if (!$address) return;
	
	include_once('./geocoder.php');
	$geocoder = new NPGM_GeoCoderMain();
	
	$geocode = $geocoder->getAllGeocoderResult($country, $address);

	foreach($geocode as $result) {
		echo '<tr>';
		echo '<td>';
		echo $result->geocoder;
		echo '</td>';
		echo '<td>';
		echo $result->accuracy;
		echo '</td>';
		echo '<td>';
		echo $result->latitude;
		echo '</td>';
		echo '<td>';
		echo $result->longitude;
		echo '</td>';
		echo '<td>';
		echo $result->address;
		echo '</td>';
		echo '<td>';
		echo $result->originaladdress;
		echo '</td>';
		echo '<td>';
		echo $result->country;
		echo '</td>';
		echo '<td>';
		echo $result->copyright;
		echo '</td>';
		
		echo '</tr>';
	}
?>
</tbody>
</table>
</body>
</html>
