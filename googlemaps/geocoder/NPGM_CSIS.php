<?php

class NPGM_CSIS extends NPGM_GeoCoder {
	function getSupportedCountries() {
		return array('jp');
	}
	
	function tokyo2wgs84($lat, $long) {
		// algorism from http://homepage3.nifty.com/Nowral/02_DATUM/02_DATUM.html
		$rd  = pi() / 180;

		$a_tokyo = 6377397.155;
		$f_tokyo = 1 / 299.152813;

		$a_wgs84 = 6378137;
		$f_wgs84 = 1 / 298.257223;

		$dx = -148;
		$dy = +507;
		$dz = +681;

		$lat *= $rd;
		$long *= $rd;
		
		$e2 = 2*$f_tokyo - $f_tokyo * $f_tokyo;
		$bda = 1- $f;

//		($da, $df) = ($a_-$a, $f_-$f);
		$da = $a_wgs84 - $a_tokyo;
		$df = $f_wgs84 - $f_tokyo;
		
//		($sb, $cb, $sl, $cl) = (sin($b), cos($b), sin($l), cos($l));
		$sinlat = sin($lat);
		$coslat = cos($lat);
		$sinlong = sin($long);
		$coslong = cos($long);

		$rn = 1 / sqrt(1 - $e2 * $sinlat * $sinlat); 
		$rm = $a_tokyo * (1 - $e2) * $rn * $rn * $rn;
		$rn *= $a_tokyo;

		$db = -$dx * $sinlat * $coslong - $dy * $sinlat * $sinlong + $dz * $coslat
				+ $da * $rn * $e2 * $sinlat * $coslat / $a_tokyo
				+ $df * ($rm / $bda + $rn * $bda) * $sinlat * $coslat;
		$db /= $rm;
		$dl = -$dx * $sinlong + $dy * $coslong;
		$dl /= $rn * $coslat;

//		(($b+$db)/$rd, ($l+$dl)/$rd, $h+$dh);
		$newlat = ($lat + $db) / $rd;
		$newlong = ($long + $dl) / $rd;
		
		return array($newlat, $newlong);
	}
	
	function GetCopyright($country, $address) {
        $address = mb_convert_encoding($address, 'UTF-8', _CHARSET);
        if (preg_match('/^station:(.*)$/i', $address, $match)) {
			$copyright = _NP_GGLMPS_CPSTATION;
		} elseif (preg_match('/^place:(.*)$/i', $address, $match)) {
			$copyright = _NP_GGLMPS_CPFACILITY;
		} elseif (preg_match('/^facility:(.*)$/i', $address, $match)) {
			$copyright = _NP_GGLMPS_CPFACILITY;
		} else {
			$copyright = _NP_GGLMPS_CPADDRESS;
		}
		return $copyright;
	}


    function GetGeocode($country, $address) {

        $address = mb_convert_encoding($address, 'UTF-8', _CHARSET);
        if (preg_match('/^station:(.*)$/i', $address, $match)) {
			$address = $match[1];
			$series = 'STATION';
			$geosys = 'world';
			$copyright = _NP_GGLMPS_CPSTATION;
		} elseif (preg_match('/^place:(.*)$/i', $address, $match)) {
			$address = $match[1];
			$series = 'PLACE';
			$geosys = 'tokyo';
			$copyright = _NP_GGLMPS_CPFACILITY;
		} elseif (preg_match('/^facility:(.*)$/i', $address, $match)) {
			$address = $match[1];
			$series = 'FACILITY';
			$geosys = 'tokyo';
			$copyright = _NP_GGLMPS_CPFACILITY;
		} else {
			$series = 'ADDRESS';
			$geosys = 'world';
			$copyright = _NP_GGLMPS_CPADDRESS;
		}
		$req =& new HTTP_Request("http://geocode.csis.u-tokyo.ac.jp/cgi-bin/simple_geocode.cgi");
		$req->setMethod(HTTP_REQUEST_METHOD_GET);
		$req->addQueryString("addr", $address, FALSE);
		$req->addQueryString("charset", 'UTF8', TRUE);
		$req->addQueryString("series", $series, TRUE);
		$req->addQueryString("geosys", $geosys, TRUE);
		
		if (!PEAR::isError($req->sendRequest())) {
		     $response1 = $req->getResponseBody();
		} else {
		     $response1 = "";
		}

		$parser = xml_parser_create('UTF-8'); 
		xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,1); 
		xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0); 
		xml_parse_into_struct($parser,$response1,&$d_ar,&$i_ar);
		xml_parser_free($parser);
		
		$a_index = $i_ar['address'][0];
		if (!$a_index) { return array (NULL, NULL, NULL); }
		$long_index = $i_ar['longitude'][0];
		$lat_index = $i_ar['latitude'][0];
		$newaddress = str_replace('/','',$d_ar[$a_index]['value']);
		$long = $d_ar[$long_index]['value'];
		$lat = $d_ar[$lat_index]['value'];
		if ($geosys == 'tokyo') {
			list ($lat, $long) = NPGM_CSIS::tokyo2wgs84($lat, $long);
		}
		
		$result->address = mb_convert_encoding($newaddress, _CHARSET, 'UTF-8');
		$result->originaladdress = mb_convert_encoding($address, _CHARSET, 'UTF-8');
		$result->country = 'jp';
		$result->longitude = $long;
		$result->latitude = $lat;
		$result->copyright = $copyright;
        return $result;

    }
}

?>