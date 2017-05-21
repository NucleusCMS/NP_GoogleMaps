<?php

class NPGM_Ontok extends NPGM_GeoCoder {
	function getSupportedCountries() {
		return array('us');
	}

    function GetGeocode($country, $address) {
		$key = "";       // subscribers are emailed key after subscribing
		$req =& new HTTP_Request("http://www.ontok.com/geocoder");
		$req->setMethod(HTTP_REQUEST_METHOD_GET);
		$req->addQueryString("key", $key, TRUE);
		$req->addQueryString("fmt", 'csv', TRUE);
		$req->addQueryString("q", $address, FALSE);

		if (!PEAR::isError($req->sendRequest())) {
		     $response1 = $req->getResponseBody();
		} else {
		     $response1 = "";
		}
		$sa = explode(',', $response1);
		$result->longitude = floatval($sa[0]);
		$result->latitude = floatval($sa[1]);
		$result->originaladdress = $address;
		$result->country = 'us';
		$result->address = trim($sa[2]);
		if ($result->longitude || $result->latitude)
			return $result;
		else
			return NULL;
	}
	
}

?>