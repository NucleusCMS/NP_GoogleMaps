<?php

class NPGM_Geocoderus extends NPGM_GeoCoder {
	function getSupportedCountries() {
		return array('us');
	}

    function GetGeocode($country, $address) {
		$req =& new HTTP_Request("http://www.ontok.com/geocoder");
		$req->setMethod(HTTP_REQUEST_METHOD_GET);
		$req->setURL("http://rpc.geocoder.us/service/rest");
		$req->addQueryString("address", $address, FALSE);
		if (!PEAR::isError($req->sendRequest())) {
		     $response1 = $req->getResponseBody();
		} else {
		     $response1 = "";
		}
		$parser = xml_parser_create(); 
		xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,1); 
		xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0); 
		xml_parse_into_struct($parser,$response1,&$d_ar,&$i_ar);
		xml_parser_free($parser); 
		foreach ($d_ar as $token) {
			if ($token['tag'] == 'dc:description') $result->address = $token['value'];
			if ($token['tag'] == 'geo:long') $result->longitude = $token['value'];
			if ($token['tag'] == 'geo:lat') $result->latitude = $token['value'];
		}
		$result->originaladdress = $address;
		$result->country = 'us';
		if ($result->longitude || $result->latitude)
			return $result;
		else
			return NULL;
	}

}

?>