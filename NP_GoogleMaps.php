<?php
class NP_GoogleMaps extends NucleusPlugin {
    var $mapnumber;
    var $pointnumber;
    var $script;
    var $itemdata;
    var $items;
    var $isinline;
    var $geocoder;

    function getName()              { return 'Google Maps'; }
    function getAuthor()            { return 'Andy'; }
    function getURL()               { return 'http://matsubarafamily.com/blog/'; }
    function getVersion()           { return '1.14'; }
    function getDescription()       { return 'Display Google Maps';}
    function getTableList()         { return array( sql_table('plugin_googlemaps') );}
    function supportsFeature($what) { return in_array($what,array('SqlTablePrefix','SqlApi'));}
    function getEventList() {
        $res = sql_query(sprintf("DESCRIBE %s 'geocoder'", sql_table('plugin_googlemaps')));
        if (!sql_fetch_array($res)) {
            sql_query(
                sprintf(
                    'ALTER TABLE %s ADD COLUMN geocoder varchar(40)'
                    , sql_table('plugin_googlemaps')
                )
            );
        }
        return array('PreItem');
    }

    function init() {
        global $DIR_PLUGINS, $DIR_LIBS;
        $this->mapnumber = 0;
        $this->pointnumber = 0;
        $path = ini_get('include_path');
        if (!strstr($path, $DIR_PLUGINS . 'sharedlibs')) {
            include_once(__DIR__ .'/sharedlibs/sharedlibs.php');
        }
        $path = ini_get('include_path');
        if (!strstr($path, $DIR_PLUGINS . 'pear')) {
            ini_set('include_path', $path . '/' . $DIR_PLUGINS . 'pear');
        }
        $path = ini_get('include_path');
        if (!strstr($path, $DIR_LIBS . 'pear')) {
            ini_set('include_path', $path . '/' . $DIR_LIBS . 'pear');
        }
        $this->copyright = array();

        $this->script = '';
        include_once($this->getDirectory() . 'geocoder/geocoder.php');
        $this->geocoder = new NPGM_GeoCoderMain();

        $language = str_replace( array('/','\\'), '', getLanguageName());
        if(is_file($this->getDirectory().$language.'.php')) {
            include_once($this->getDirectory().$language.'.php');
        }else {
            include_once($this->getDirectory().'english.php');
        }
    }

    function event_PreItem(&$data) {
        global $currentTemplateName;

        $this->itemdata = array();
        $this->items = 0;
        $this->currentItem = &$data["item"];
        $template = TEMPLATE::read($currentTemplateName);

        $bodies = 0;
        $mores  = 0;

        foreach ($template as $part) {
            $bodies += substr_count($part, '<%body%>');
            $mores += substr_count($part, '<%more%>');
        }

        $pattern1 = '#<%gmap\((.*?)\)%>#s';
        $pattern2 = '#<%gmapitem\((.*?)\)%>#s';
        $pattern3 = '#<%gmapitemlink\((.*?),(.*?)\)%>#s';

        if ($bodies && strpos($this->currentItem->body,'<%gmap')!==false) {
            $this->currentItem->body = preg_replace_callback(
                $pattern1
                , array(&$this, 'replaceCallback')
                , $this->currentItem->body
            );
            $this->currentItem->body = preg_replace_callback(
                $pattern2
                , array(&$this, 'replaceCallbackItem')
                , $this->currentItem->body
            );
            $this->currentItem->body = preg_replace_callback(
                $pattern3
                , array(&$this, 'replaceCallbackLink')
                , $this->currentItem->body
            );
        }
        if ($mores && strpos($this->currentItem->more,'<%gmap')!==false) {
            $this->currentItem->more = preg_replace_callback(
                $pattern1
                , array(&$this, 'replaceCallback')
                , $this->currentItem->more
            );
            $this->currentItem->more = preg_replace_callback(
                $pattern2
                , array(&$this, 'replaceCallbackItem')
                , $this->currentItem->more
            );
            $this->currentItem->more = preg_replace_callback(
                $pattern3
                , array(&$this, 'replaceCallbackLink')
                , $this->currentItem->more
            );
        }
    }

    function getMapNumber() {
        return $this->mapnumber++;
    }

    function getPointNumber() {
        return $this->pointnumber++;
    }

    /* static function to find lng/lat from street address */
    function Geocode($country, $address) {
        $this->geocoder = new NPGM_GeoCoderMain();
        $geocode = $this->geocoder->getGeoCode($country, $address);
        if ((!$geocode->longitude) && (!$geocode->latitude)) {
            return NULL;
        }
        array_push($this->copyright, $geocode->copyright);
        $this->copyright = array_unique($this->copyright);

        return array ($geocode->longitude, $geocode->latitude);
    }

    function GetGPSCoord($fname) {
        $exif = @exif_read_data ($fname,0,true);
        if ($exif) {
            sscanf($exif['GPS']['GPSLatitude'][0], '%d/1', $lat);
            preg_match('|(\d+)/(\d+)|', $exif['GPS']['GPSLatitude'][1], $matches);
            if ($matches[2]) {
                $lat += $matches[1] / $matches[2] / 60;
            }
            preg_match('|(\d+)/(\d+)|', $exif['GPS']['GPSLatitude'][2], $matches);
            if ($matches[2]) {
                $lat += $matches[1] / $matches[2] / 3600;
            }
            if ($exif['GPS']['GPSLatitudeRef'] === 'S') {
                $lat = -$lat;
            }

            sscanf($exif['GPS']['GPSLongitude'][0], '%d/1', $long);
            preg_match('|(\d+)/(\d+)|', $exif['GPS']['GPSLongitude'][1], $matches);
            if ($matches[2]) {
                $long += $matches[1] / $matches[2] / 60;
            }
            preg_match('|(\d+)/(\d+)|', $exif['GPS']['GPSLongitude'][2], $matches);
            if ($matches[2]) {
                $long += $matches[1] / $matches[2] / 3600;
            }
            if ($exif['GPS']['GPSLongitudeRef'] === 'W') {
                $long = -$long;
            }
            if ($exif['GPS']['GPSMapDatum'] === 'TOKYO') {
                $lat2 = $lat - 0.00010695 * $lat + 0.000017464 * $long + 0.0046017;
                $long2 = $long - 0.000046038 * $lat - 0.000083043 * $long + 0.010040;
                if ($lat2 && $long2) return array($long2, $lat2);
            }
            if ($lat && $long) return array($long, $lat);
        }
        return NULL;
    }

    function GetFlickrGPSCoord($url) {
        preg_match('#/([^_/]+)_[^/]+$#', $url, $matches);
        $id = $matches[1];
        $apikey = $this->getOption('flickrapi');
        $callpath = 'http://www.flickr.com/services/rest/?method=flickr.photos.getInfo&api_key=' . $apikey . '&photo_id=' . $id;
        $data = @implode('',file($callpath));
        $parser = xml_parser_create();
        xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,1);
        xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
        xml_parse_into_struct($parser,$data,$d_ar,$i_ar);
        xml_parser_free($parser);
        foreach ($d_ar as $token) {
            $pos = strpos($token['value'], 'geolon');
            if (($pos === 0) && ($token['tag'] == 'tag')) {
                $rawlong = $token['attributes']['raw'];
                $long = str_replace('geo:lon=', '', $rawlong);
            }
            $pos = strpos($token['value'], 'geolat');
            if (($pos === 0) && ($token['tag'] == 'tag')) {
                $rawlat = $token['attributes']['raw'];
                $lat = str_replace('geo:lat=', '', $rawlat);
            }
        }
        if ($long || $lat)
            return array($long, $lat);
        $callpath = "http://www.flickr.com/services/rest/?method=flickr.photos.getExif&api_key={$apikey}&photo_id={$id}";
        $data = @implode("",file($callpath));
        $parser = xml_parser_create();
        xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,1);
        xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
        xml_parse_into_struct($parser,$data,$d_ar,$i_ar);
        xml_parser_free($parser);
        foreach ($d_ar as $i => $iValue) {
            if (($iValue['tag'] === 'exif') &&
                ($iValue['attributes']['label'] === 'Latitude')) {
                $data = explode(',', $d_ar[++$i]['value']);
                sscanf($data[0], '%d/1', $lat);
                preg_match('|(\d+)/(\d+)|', $data[1], $matches);
                $lat += $matches[1]/$matches[2]/60;
                preg_match('|(\d+)/(\d+)|', $data[2], $matches);
                $lat += $matches[1]/$matches[2]/3600;
            }

            if (($iValue['tag'] === 'exif') &&
                ($iValue['attributes']['label'] === "North or South Latitude")) {
                $northsouth = $d_ar[++$i]['value'];
            }
            if (($iValue['tag'] === 'exif') &&
                ($iValue['attributes']['label'] === 'Longitude')) {
                $data = explode(',', $d_ar[++$i]['value']);
                sscanf($data[0], '%d/1', $long);
                preg_match('|(\d+)/(\d+)|', $data[1], $matches);
                $long += $matches[1]/$matches[2]/60;
                preg_match('|(\d+)/(\d+)|', $data[2], $matches);
                $long += $matches[1]/$matches[2]/3600;
            }
            if (($iValue['tag'] === 'exif') &&
                ($iValue['attributes']['label'] === "East or West Longitude")) {
                $eastwest = $d_ar[++$i]['value'];
            }
        }
        if ($northsouth === 'S') {
            $lat = -$lat;
        }
        if ($eastwest === 'W') {
            $long = -$long;
        }
        if ($long || $lat) {
            return array($long, $lat);
        }
        return FALSE;
    }

    function doAction($type) {
        switch ($type) {
            case '':
                $map = $_GET;
                $bid = intval($map['blogid']);
                if ($bid) $key = $this->getBlogOption($bid, 'blogapikey');
                if (!$key) $key = $this->getOption('apikey');
                $max = intval($map['p']);
                for ($k = 0; $k < $max; ++$k) {
                    $map['info' . $k] = hsc($map['info' . $k]);
                }
                $map['info'] = hsc($map['info']);
                ?>
                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml">
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                    <title>Google Maps</title>
                    <script src="http://maps.google.com/maps?file=api&v=1&key=<?php echo $key ?>" type="text/javascript"></script>
                    <script type="text/javascript">
                        //<![CDATA[
                        function mapgotopoint(p) {
                            this.focus();
                            map.recenterOrPanToLatLng(marker[p].point);
                            marker[p].openInfoWindowHtml(info[p]);
                        }
                        window.gotopoint = mapgotopoint;
                        var marker = new Array(1);
                        var marker2 = new Array(1);
                        var info = new Array(1);
                        //]]>
                    </script>
                </head>
                <body>
                <div id="map" style="width: <?php echo intval($map['width']) ?>px; height: <?php echo intval($map['height']) ?>px"></div>
                <script type="text/javascript">
                    //<![CDATA[
                    <?php echo $this->DrawMap($map); ?>
                    //]]>
                </script>
                </body>
                </html>
                <?php
                break;
        }
    }

    function doSkinVar($type, $param) {
        global $CONF, $blogid;
        if ($blogid) $key = $this->getBlogOption($blogid, 'blogapikey');
        if (!$key)   $key = $this->getOption('apikey');
        switch($param) {
            case 'HEAD' :
                echo <<<HEADER
<script src="http://maps.google.com/maps?file=api&v=2&key=$key" type="text/javascript" charset="utf-8"></script>
<script type="text/javascript">
	//<![CDATA[
	function gotopoint(p) {
		this.recenterOrPanToLatLng(marker[p].point);
		marker[p].openInfoWindowHtml(info[p]);
	}
	GMap.prototype.gotopoint = gotopoint;
	var marker = new Array(1);
	var marker2 = new Array(1);
	var info = new Array(1);
	//]]>
	</script>

HEADER;
                break;
            case 'SCRIPT' :
                if ($this->copyright) {
                    $copyright = _NP_GGLMPS_CPRGHT1 . implode(',', $this->copyright)
                        ._NP_GGLMPS_CPRGHT2;
                    $this->script .= "npgmcprght = document.getElementById('NPGM_CPRGT');\n";
                    $this->script .= "if (npgmcprght) npgmcprght.innerHTML = '$copyright';\n";
                }
                echo <<<SCRIPTHEAD
	<script type="text/javascript">
	//<![CDATA[

SCRIPTHEAD;
                echo $this->script;
                echo <<<SCRIPTEND
	//]]>
	</script>
SCRIPTEND;
                break;
            case 'COPYRIGHT' :
                echo '<span id="NPGM_CPRGT"></span>';
        }
    }

    function DrawMap($mapdata, $i = '') {
        $ph['i'] = $i;
        $script = array();
        $script[] = 'var map<%i%> = new GMap2(document.getElementById("map<%i%>"));';
        $script[] = 'map<%i%>.drawpolyline = false;';
        switch ($mapdata['mc']) {
            case 'xs'   : $mapControl = 'GSmallZoomControl';break;
            case 's'    : $mapControl = 'GSmallMapControl' ;break;
            case 'b'    : $mapControl = 'GLargeMapControl' ;break;
        }
        $ph['mapControl'] = $mapControl;
        $script[] = 'map<%i%>.addControl(new <%mapControl%>());';

        if ($mapdata['tc']==='s') $script[] = "	map<%i%>.addControl(new GMapTypeControl());";
        if ($mapdata['sc']==='s') $script[] = "	map<%i%>.addControl(new GScaleControl());";

        if ($mapdata['zl'] === 'auto') {
            if ($mapdata['p'] == 0) {
                $autozoom = FALSE;
                $mapdata['zl'] = 17;
            }
            else {
                $autozoom = TRUE;
                $mapdata['zl'] = 11;
            }
        } else {
            $autozoom = FALSE;
            $mapdata['zl'] = intval($mapdata['zl']);
        }

        $xarray = array ();
        $bottom = floatval($mapdata['y0']); $top = $miny;

        $num = $minpoint = $this->pointnumber;
        $max = intval($mapdata['p']);
        $ph['zoom'] = $mapdata['zl'];
        for ($k = 0; $k < $max; ++$k) {
            $mark = array();
            $mark['lat'] = floatval($mapdata["y{$k}"]);
            $mark['lng'] = floatval($mapdata["x{$k}"]);

            $script[] = sprintf("	var wpoint = new GLatLng(%s,%s);", $mark['lat'], $mark['lng']);

            if ($k == 0) $script[] = "	map<%i%>.setCenter(wpoint,<%zoom%>);";

            $num = $this->getPointNumber();
            if ($mapdata['mark' . $k] === 'yes') {
                $script[] = "	marker[$num] = new GMarker(wpoint);"
                    .  "	map<%i%>.addOverlay(marker[$num]);";
            }

            if ($mapdata["info{$k}"]) {
                if ($mapdata['mark' . $k] === 'yes') {
                    $script[] = "   info[$num] = '". str_replace("'", "\\'", $mapdata["info{$k}"]) . "';";
                    $script[] = "	GEvent.addListener(marker[$num], \"click\", function() {"
                        .  "		marker[$num].openInfoWindowHtml(info[$num]);"
                        .  "	});";
                } else
                    $script[] = "	map<%i%>.openInfoWindow(map<%i%>.getCenterLatLng(), "
                        .  "		document.createTextNode('" . $mapdata["info{$k}"] . "'));";
            }

            if ($bottom > $mark['lat']) $bottom = $mark['lat'];
            if ($top < $mark['lat'])    $top = $mark['lat'];

            array_push($xarray, $mark['lng']);
        }

        if (!$max) $script[] = "	map<%i%>.setCenter(new GLatLng(0, 0),<%zoom%>);";

        $script[] = "	map<%i%>.minpoint = $minpoint;";
        $script[] = "	map<%i%>.maxpoint = $num;";
        if ($autozoom) {
            sort($xarray);
            $gap = 360 + $xarray[0] - $xarray[count($xarray)-1];
            $maxgap = count($xarray)-1;
            for ($p = 0; $p < count($xarray)-1; ++$p) {
                if ($gap < ($xarray[$p+1] - $xarray[$p])) {
                    $gap = $xarray[$p+1] - $xarray[$p];
                    $maxgap = $p;
                }
            }
            $ph['centerx'] = $xarray[$maxgap] - (360 - $gap) / 2;
            $ph['gapx'] = (360 - $gap) * 1.1;
            $ph['gapy'] = ($top - $bottom) * 1.2;
            $ph['centery'] = $bottom + ($top - $bottom) * 0.55;
            $script[] = "	var center = new GPoint(<%centerx%>, <%centery%>);";
            $script[] = "	map<%i%>.centerAtLatLng(center);";
            $script[] = "	var zl = map<%i%>.getZoomLevel();";
            $script[] = "	while (true) {";
            $script[] = "		var bounds = map<%i%>.getSpanLatLng();";
            $script[] = "		if ((bounds.width > <%gapx%>) && (bounds.height > <%gapy%>))";
            $script[] = "			break;";
            $script[] = "		++zl;";
            $script[] = "		map<%i%>.zoomTo(zl);";
            $script[] = "	}";
        }
        if ($mapdata['info'] !== '' && $mapdata['info'] !== null) {
            $ph['info'] = $mapdata['info'];
            $script[] = "	map<%i%>.recenterOrPanToLatLng(marker[<%info%>].point);";
            $script[] = "	marker[<%info%>].openInfoWindowHtml(info[<%info%>]);";
        }

        return $this->parseText(join("\n",$script),$ph);
    }

    function createInlineLink($map, $i) {
        return '<div id="map' .$i. '" style="width: '
            .$map['width'] .'px; height: '.$map['height'] .'px"></div>'. "\n";
    }

    function createPopupLink($map, $linktext, $linkurl= '') {
        global $CONF;
        $i = $this->getMapNumber();
        $w2 = $map['width'] + 20;
        $h2 = $map['height'] + 20;
        $s = array();
        foreach ($map as $key => $value) {
            if (strpos($key, 'info') === 0) $value = urlencode($value);
            if ($value) $s[] = "{$key}={$value}";
        }
        $s = join('&',$s);
        if ($linkurl) {  // for MapBlog popup
            $h2 += 30;
            $this->script .= <<<NEWFUNC1
		function openmap$i() {
			var map=window.open('$linkurl', 'googlemap', 'scrollbars=no,width=$w2,height=$h2,left=10,top=10,status=yes,resizable=yes,location=no');
			return map;
		}

NEWFUNC1;
        } else {
            $this->script .= <<<NEWFUNC2
		function openmap$i(p) {
			var map=window.open('{$CONF['ActionURL']}?action=plugin&name=GoogleMaps&{$s}&info='+p, 'googlemap', 'scrollbars=no,width=$w2,height=$h2,left=10,top=10,status=yes,resizable=yes,location=no');
			return map;
		}

NEWFUNC2;
        }
        $link = "<a href=\"javascript:void(0)\" onclick=\"map$i=openmap$i('');map$i.focus();return false;\">$linktext</a>";
        return $link;
    }

    function addInlineMap($map) {
        $i = $this->getMapNumber();
        $this->script .= $this->DrawMap($map, $i);
        return $this->createInlineLink($map, $i);
    }

    function createLink($map, $linktext, $items) {
        for ($i = 0, $j = $this->items; $i < $items; $i++, $j++) {
            $this->itemdata["x{$j}"] = $map["x{$i}"];
            $this->itemdata["y{$j}"] = $map["y{$i}"];
            $this->itemdata["mark{$j}"] = 'yes';
            $this->itemdata["info{$j}"] = $map["info{$i}"];
        }
        $s = "<%gmapitemlink({$this->items},$linktext)%>";
        $this->items += $items;
        return $s;
    }

    function analyzepoint($point) {
        preg_match('#^([^\[]*)\[(.*?)\]\|(.*?)\|(.*)$#', $point, $pointinfo);
        switch ($pointinfo[1]) {
            case '' :
                list($coord['x'], $coord['y']) = explode('|', $pointinfo[2]);
                break;
            case 'image' :
                global $DIR_MEDIA;
                if (strpos($pointinfo[2], 'http://') === 0) {
                    $xy = $this->GetFlickrGPSCoord($pointinfo[2]);
                    $coord['address'] = $pointinfo[2];
                } elseif (strpos($pointinfo[2], '/') === false) {
                    $fname = $DIR_MEDIA . $this->currentItem->authorid . '/' . $pointinfo[2];
                    $coord['address'] = $this->currentItem->authorid . '/' . $pointinfo[2];
                    $xy = $this->GetGPSCoord($fname);
                } else {
                    $fname = $DIR_MEDIA . $pointinfo[2];
                    $coord['address'] = $pointinfo[2];
                    $xy = $this->GetGPSCoord($fname);
                }
                if (!$xy) {
                    return $pointinfo[0];
                }
                list($coord['x'], $coord['y'])= $xy;
                break;
            default :
                // case geocode
                $xy = $this->Geocode($pointinfo[1], $pointinfo[2]);
                if (!$xy) return 'Address not found';
                list($coord['x'], $coord['y'])= $xy;
                $coord['country'] = $pointinfo[1];
                $coord['address'] = $pointinfo[2];
        }
        if (!($coord['x']) && !($coord['y'])) return NULL;
        $coord['info'] = $pointinfo[4];
        $coord['mark'] = $pointinfo[3];
        return $coord;
    }


    function replaceCallback($matches) {
        global $blogid;
        $matches[1] = preg_replace('{<br[ /]*>}i', '', $matches[1]);
        if (!preg_match('#^(inline|popup\((.*?)\)|link\((.*?)\))(?:\s*,\s*p\((?:[^)]*?)\))+(?:\s*,\s*m\((.*?)?\))?$#s', $matches[1], $params)) {
            return $matches[0];
        }

        $map['p'] = preg_match_all('#(?:\n|\s|,)p\(([^)]*?)\)#', $matches[1], $points);
        for ($i = 0; $i < $map['p']; ++$i) {
            $coord = $this->analyzepoint($points[1][$i]);

            if (!is_array($coord)) return $coord;
            $map["x{$i}"]    = $coord['x'];
            $map["y{$i}"]    = $coord['y'];
            $map["mark{$i}"] = $coord['mark'] ? $coord['mark'] : 'no';
            $map["info{$i}"] = $coord['info'];
        }
        $map['blogid'] = $blogid;
        if (preg_match('#^(.*?)\|(.*?)\|(.*?)\|(.*?)/(.*?)/(.*?)\|(.*)$#', $params[4], $mapoption)) {
            $map['width'] = ($mapoption[1]) ? $mapoption[1] : 400;
            $map['height'] = ($mapoption[2]) ? $mapoption[2] : 300;
            $map['tp'] = ($mapoption[3]) ? $mapoption[3] : 'map';
            $map['mc'] = ($mapoption[4]) ? $mapoption[4] : 's';
            $map['tc'] = ($mapoption[5]) ? $mapoption[5] : 's';
            $map['sc'] = ($mapoption[6]) ? $mapoption[6] : 's';
            $map['zl'] = ($mapoption[7] || is_int($mapoption[7])) ? $mapoption[7] : 3;
        } else {
            $map['width'] = $this->getOption('mapwidth');
            $map['height'] = $this->getOption('mapheight');
            $map['tp'] = $this->getOption('maptype');
            $map['mc'] = $this->getOption('mapcontrol');
            $map['tc'] = $this->getOption('typecontrol');
            $map['sc'] = $this->getOption('scalecontrol');
            $map['zl'] = $this->getOption('zoomlevel');
        }

        if ($params[1]==='inline' )               return $this->addInlineMap($map);
        elseif(strpos($params[1], 'popup') === 0) return $this->createPopupLink($map, $params[2]);
        else                                      return $this->createLink($map, $params[3], $i);
    }

    function replaceCallbackItem($matches) {
        global $CONF, $blogid;
        $matches[1] = preg_replace('{<br[ /]*>}i', '', $matches[1]);
        if (!preg_match('#^(inline|popup\((.*?)\))(?:\s*,\s*m\((.*?)?\))?$#s', $matches[1], $params))
            return $matches[0];
        $map = $this->itemdata;
        $map['p'] = $this->items;
        $map['blogid'] = $blogid;
        if (preg_match('#^(.*?)\|(.*?)\|(.*?)\|(.*?)/(.*?)/(.*?)\|(.*)$#', $params[3], $mapoption)) {
            $map['width'] = ($mapoption[1]) ? $mapoption[1] : 400;
            $map['height'] = ($mapoption[2]) ? $mapoption[2] : 300;
            $map['tp'] = ($mapoption[3]) ? $mapoption[3] : 'map';
            $map['mc'] = ($mapoption[4]) ? $mapoption[4] : 's';
            $map['tc'] = ($mapoption[5]) ? $mapoption[5] : 's';
            $map['sc'] = ($mapoption[6]) ? $mapoption[6] : 's';
            $map['zl'] = ($mapoption[7] || is_int($mapoption[7])) ? $mapoption[7] : 3;
        } else {
            $map['width'] = $this->getOption('mapwidth');
            $map['height'] = $this->getOption('mapheight');
            $map['tp'] = $this->getOption('maptype');
            $map['mc'] = $this->getOption('mapcontrol');
            $map['tc'] = $this->getOption('typecontrol');
            $map['sc'] = $this->getOption('scalecontrol');
            $map['zl'] = $this->getOption('zoomlevel');
        }
        if ($params[1] === 'inline' ) {
            $this->isinline = TRUE;
            return $this->addInlineMap($map);
        }
        $this->isinline = FALSE;
        return $this->createPopupLink($map, $params[2]);
    }

    function replaceCallbackLink($matches) {
        $mapnum = $this->mapnumber-1;
        if ($this->isinline)
            $s = '<a href="javascript:void(0)" onclick="map' . $mapnum . ".gotopoint(" . $matches[1] . ');return false;" class="maplink">' . $matches[2] . "</a>";
        else
            $s = "<a href=\"javascript:void(0)\" onclick=\"if(window[map$mapnum]==null||map$mapnum.closed)var map$mapnum=openmap$mapnum($matches[1]);return false;\" class=\"maplink\">{$matches[2]}</a>";
        return $s;
    }

    function parseTemplate($data, $item='') {
        global $blog, $currentTemplateName, $manager;
        if ($currentTemplateName) {
            $template =& $manager->getTemplate($currentTemplateName);
        }
        $actions = new ITEMACTIONS($blog);
        $parser  = new PARSER($actions->getDefinedActions(),$actions,'(<:|:>)',';');
        $actions->setTemplate($template);
        $actions->setParser($parser);
        if ($item) $actions->setCurrentItem($item);
        ob_start();
        $parser->parse($data);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    function doTemplateVar() {
        $params = func_get_args();
        if (($num = func_num_args()) < 4) return;
        $item = $params[0];
        $this->itemid = $item->itemid;
        $type = $params[1];
        if (!preg_match('#^(inline|popup\((.*?)\))$#s', $type, $maptype)) {
            return;
        }
        for ($i = 2; $i < $num-1; $i++) {
            $point = $this->parseTemplate($params[$i], $item);
            // $point = preg_replace_callback('#<:(.+?):>#', array(&$this, 'replaceItemFieldEx'), $params[$i]);
            $point = preg_replace('#^p\((.*)\)$#', '$1', $point);
            $coord = $this->analyzepoint($point);

            $j = $i - 2;
            if (!is_array($coord)) return;
            if (($coord['x'] == 0) && ($coord['y'] == 0)) return;
            $map["x{$j}"]    = $coord['x'];
            $map["y{$j}"]    = $coord['y'];
            $map["mark{$j}"] = $coord['mark'] ? $coord['mark'] : 'no';
            $map["info{$j}"] = $coord['info'];
        }
        $map['p'] = $num - 3;
        $map['blogid'] = $item->blogid;
        preg_match('#^m\((.*?)\|(.*?)\|(.*?)\|(.*?)/(.*?)/(.*?)\|(.*)\)$#', $params[$num-1], $mapoption);
        $map['width'] = ($mapoption[1]) ? $mapoption[1] : 400;
        $map['height'] = ($mapoption[2]) ? $mapoption[2] : 300;
        $map['tp'] = ($mapoption[3]) ? $mapoption[3] : 'map';
        $map['mc'] = ($mapoption[4]) ? $mapoption[4] : 's';
        $map['tc'] = ($mapoption[5]) ? $mapoption[5] : 's';
        $map['sc'] = ($mapoption[6]) ? $mapoption[6] : 's';
        $map['zl'] = ($mapoption[7] || is_int($mapoption[7])) ? $mapoption[7] : 3;

        if ($maptype[1] === 'inline' ) {
            echo $this->addInlineMap($map);
        } elseif (strpos($maptype[1], 'popup') === 0) {
            echo $this->createPopupLink($map, $maptype[2]);
        }
    }

    function parseText($tpl,$ph=array()) {
        foreach($ph as $k=>$v) {
            $k = "<%{$k}%>";
            $tpl = str_replace($k,$v,$tpl);
        }
        return $tpl;
    }

    function install() {
        $this->createOption('apikey' , _GGLMPS_APIKEY, 'text', '');
        $this->createOption('flickrapi' , _GGLMPS_FLICKRKEY, 'text', '');
        $this->createOption('deletetable', _GGLMPS_DELETETABLE, 'yesno', 'yes');
        $this->createOption('mapwidth', _GGLMPS_WIDTH, 'text', '400');
        $this->createOption('mapheight', _GGLMPS_HEIGHT, 'text', '300');
        $this->createOption('maptype', _GGLMPS_MAPTYPE, 'select', 'map',
            _GGLMPS_MAP . '|map|' .
            _GGLMPS_SATE . '|sate|' .
            _GGLMPS_DUAL . '|dual');
        $this->createOption('mapcontrol', _GGLMPS_MAPCONTROLER, 'select', 's',
            _GGLMPS_NOCONTROLER . '|none|' .
            _GGLMPS_XSCONTROLER . '|xs|' .
            _GGLMPS_SMALLCONTROLER . '|s|' .
            _GGLMPS_LARGECONTROLER . '|b');

        $this->createOption('typecontrol', _GGLMPS_TYPECONTROLER, 'select', 's',
            _GGLMPS_NOCONTROLER . '|none|' .
            _GGLMPS_SMALLCONTROLER . '|s');

        $this->createOption('scalecontrol', _GGLMPS_SCALECONTROLER, 'select', 's',
            _GGLMPS_NOCONTROLER . '|none|' .
            _GGLMPS_SMALLCONTROLER . '|s');
        $this->createOption('zoomlevel' , _GGLMPS_ZOOMLEVEL, 'text', '3');
        $this->createBlogOption('blogapikey', _GGLMPS_APIKEY_FORBLOG, 'text', '');
        sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plugin_googlemaps'). '('
            . 'geoid int(11) not null auto_increment,'
            . 'country varchar(10), '
            . 'address varchar(255), '
            . 'fulladdress varchar(255), '
            . 'longitude double not null, '
            . 'latitude double not null, '
            . 'PRIMARY KEY (geoid))');
    }

    function unInstall() {
        if ($this->getOption('deletetable') === 'yes') {
            sql_query('DROP TABLE ' . sql_table('plugin_googlemaps'));
        }
    }
}
