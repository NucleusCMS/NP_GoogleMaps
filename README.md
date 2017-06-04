# NP_GoogleMaps

format1: simple map
mapformat	::= '<%gmap(' mode ',' pointsinfo ',' mapoption ')%>' |
		    '<%gmap(' mode ',' pointsinfo ')%>'
mode		::= 'inline' | 'popup(' linktext ')' | 'link(' linktext ')'
linktext	::= STRING
pointsinfo	::= pointinfo | pointinfo ',' pointsinfo
pointinfo	::= 'p(' address '|' marker '|' infotext ')'
address		::= coordinate | street | image
coordinate	::= '[' longitude '|' latitude ']'
longitude	::= -180 .. 180
latitude	::= -90 .. 90
street		::= countrycode '[' strtaddress ']'
countrycode	::= 'jp' | 'us' | ...
strtaddress	::= STRING
image		::= 'image[' imagefile ']'
imagefile	::= STRING	// supports image files in the media directory and Flickr.
marker		::= BLANK | 'yes' | 'no'		// BLANK='no'
infotext	::= STRING
mapoption	::= 'm(' width '|' height '|' type '|' control '|' zoomlevel ')'
width		::= BLANK | POSITIVE INTEGER	// BLANK=400
height		::= BLANK | POSITIVE INTEGER	// BLANK=300
type		::= BLANK | 'map' | 'sate' | 'dual'	// BLANK='map'
control		::= BLANK | mapcontrol '/' typecontrol'/' scalecontrol
mapcontrol	::= BLANK | 'none' | 'xs' | 's' | 'b'   //xs=scale only, s=small, b=large BLANK='s'
typecontrol	::= BLANK | 'none' | 's' 	// BLANK='s'
scalecontrol::= BLANK | 'none' | 's'	// BLANK='s'
zoomlevel	::= BLANK | INTEGER			// BLANK=5

format2: works with <%gmap(link ... %> type tag and make a map for the item. 
mapformat	::= '<%gmapitem(' mode ',' mapoption ')%>'
mode		::= 'inline' | 'popup(' linktext ')'
linktext	::= STRING
mapoption	::= 'm(' width '|' height '|' type '|' control '|' zoomlevel ')'
width		::= BLANK | POSITIVE INTEGER	// BLANK=400
height		::= BLANK | POSITIVE INTEGER	// BLANK=300
type		::= BLANK | 'map' | 'sate' | 'dual'	// BLANK='map'
control		::= BLANK | mapcontrol '/' typecontrol'/' scalecontrol
mapcontrol	::= BLANK | 'none' | 'xs' | 's' | 'b'   //xs=scale only, s=small, b=large BLANK='s'
typecontrol	::= BLANK | 'none' | 's' 	// BLANK='s'
scalecontrol::= BLANK | 'none' | 's'	// BLANK='s'
zoomlevel	::= BLANK | INTEGER			// BLANK=5

format3: supports polyline and multiple icons
coming soon

