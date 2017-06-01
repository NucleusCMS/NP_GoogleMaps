<?php

class NPGM_GeoCoder {
	function __construct($name) {
		require_once "HTTP/Request.php";
		$this->name = $name;
	}
	
	function getName() {
		if (preg_match('/^NPGM_(.+)$/', $this->name, $match)) {
				return $match[1];
		}
	}
	
	function getSupportedCountries() {
		return array();
	}

	function GetCopyright($country, $address) {
		return "";
	}

    function getGeocode($country, $address) {
	}
}

class NPGM_Google extends NPGM_GeoCoder {
	
	function getSupportedCountries() {
		return array();
	}

    function getGeocode($country, $address) {
		global $manager;
		$googlemaps = $manager->getPlugin("NP_GoogleMaps");
		$apikey = $googlemaps->getOption('apikey');
		$req = new HTTP_Request("http://maps.google.com/maps/geo");
		$req->setMethod(HTTP_REQUEST_METHOD_GET);
		$req->addQueryString("key", $apikey, TRUE);
		$req->addQueryString("output", 'csv', TRUE);
		$req->addQueryString("q", $address, FALSE);

		if (!PEAR::isError($req->sendRequest())) {
		     $response1 = $req->getResponseBody();
		} else {
		     $response1 = "";
		}
		$sa = explode(',', $response1);
		$result->accuracy = $sa[1];
		$result->latitude = $sa[2];
		$result->longitude = $sa[3];
		$result->geocoder = 'Google';
		$result->country = $country;
		$result->originaladdress = $address;
		$result->address = $address;
		return $result;
	}
}

class NPGM_GeoCoderMain {
	var $defaultgeocoder;
	function __construct() {
		global $manager;
		$this->plugin = $manager->getPlugin("NP_GoogleMaps");
		$this->directory = $this->plugin->getDirectory().'geocoder/';
		$this->defaultgeocoder = new NPGM_Google('NPGM_Google');
		$this->geocoders = array();
	}
	
	function loadGeocoders() {
		$dirhandle = opendir($this->directory);
		while ($filename = readdir($dirhandle)) {
			if (preg_match('/^NPGM_(.*)\.php$/',$filename,$matches)) {
				$name = $matches[1];
				$geo = $this->loadgeocoder($name);
				if (is_object($geo)) {
					$countries = $geo->getSupportedCountries();
					foreach ($countries as $country) {
						if (! is_array($this->geocoders[$country]))
							$this->geocoders[$country] = array();
						array_push($this->geocoders[$country] , $geo);
					}
				}
			}
		}
		closedir($dirhandle);
		
	}
	
	function loadgeocoder($name) {
		$classname = 'NPGM_' . $name;
		if (class_exists ($classname)) {
			eval('$instance = new ' . $classname . '($classname);');
			return $instance;
		}
		include ($this->directory . $classname .'.php');
		if (!class_exists($classname)) return;
		eval('$instance = new ' . $classname . '($classname);');
		return $instance;
	}

	function storeDB($geocode) {
		$address = addslashes($geocode->address);
		$originaladdress = addslashes($geocode->originaladdress);
		if (!$originaladdress) $originaladdress = $address;
		$country = addslashes($geocode->country);
		$longitude = floatval($geocode->longitude);
		$latitude = floatval($geocode->latitude);
		$geocoder = addslashes($geocode->geocoder);
		$query = 'INSERT INTO ' . sql_table('plugin_googlemaps'). 
				' (country, address, fulladdress, longitude, latitude, geocoder) VALUES '.
				"('$country', '$originaladdress', '$address', $longitude, $latitude, '$geocoder')";
		sql_query($query);
	}
	
	function getcopyright(& $geocode, $country, $address) {
		$geocoder = $this->loadgeocoder($geocode->geocoder);
		$geocode->copyright = $geocoder->GetCopyright($country, $address);
	}
	
	function getGeoCode($country, $address) {
		str_replace('"', '', $address); // remove double quote to avoid SQL injection
		str_replace('"', '', $country); // remove double quote to avoid SQL injection
		$query = 'SELECT longitude, latitude, geocoder FROM '.  sql_table('plugin_googlemaps').
				 ' WHERE country="' . $country . '" and address="' . $address . '"';
		$result = sql_query($query);
		$geocode = mysql_fetch_object($result);
		if ($geocode) {
			if (!$geocode->geocoder) { $geocode->geocoder = 'Google'; }
			$this->getcopyright($geocode, $country, $address);
			return $geocode;
		}
		
		$defgeocode = $this->defaultgeocoder->getGeocode($country, $address);
		if (($defgeocode->accuracy >= 7) || 
			(($defgeocode->accuracy >= 1) && ($defgeocode->country=='jp'))) {
			$defgeocode->geocoder = $this->defaultgeocoder->getName();
			$this->storeDB($defgeocode);
			return $defgeocode;  // beyond intersection level
		}

		$this->loadGeocoders();
		foreach ($this->geocoders[$country] as $geocoder) {
			$geocode = $geocoder->getGeocode($country, $address);
			if ($geocode) {
				$geocode->geocoder = $geocoder->getName();
				$this->storeDB($geocode);
				return $geocode;
			}
		}
		return $defgeocode;
	}
	
	function getAllGeocoderResult($country, $address) { // for debug mode
		$allresult = array();
		str_replace('"', '', $address); // remove double quote to avoid SQL injection
		str_replace('"', '', $country); // remove double quote to avoid SQL injection
		$query = 'SELECT longitude, latitude, geocoder FROM '.  sql_table('plugin_googlemaps').
				 ' WHERE country="' . $country . '" and address="' . $address . '"';
		$result = sql_query($query);
		$geocode = mysql_fetch_object($result);
		if ($geocode) {
			if (!$geocode->geocoder) { $geocode->geocoder = 'Google'; }
			$this->getcopyright($geocode, $country, $address);
			array_push($allresult, $geocode);
		}
		
		$defgeocode = $this->defaultgeocoder->getGeocode($country, $address);
		$defgeocode->geocoder = $this->defaultgeocoder->getName();
		array_push($allresult, $defgeocode);

		$this->loadGeocoders();
		foreach ($this->geocoders[$country] as $geocoder) {
			$geocode = $geocoder->getGeocode($country, $address);
			$geocode->geocoder = $geocoder->getName();
			array_push($allresult, $geocode);
		}
		return $allresult;
	}
}

?>
