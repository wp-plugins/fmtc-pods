<?php
/*
Plugin Name: FMTC Pods
Plugin URI: http://www.fmtc.co/tools/pods
Description: Display FMTC Pods in your WordPress site
Version: 1.41
Author: FMTC
Author URI: http://fmtc.co
License: GPL2
*/

/*  Copyright 2014  For Me To Coupon  eric@fmtc.co

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

register_activation_hook(__FILE__, 'fmtcpod_activate');
// register_deactivation_hook(__FILE__, 'fmtc_deactivate');
// register_uninstall_hook(__FILE__, 'fmtc_uninstall');

// activating the default values
function fmtcpod_activate() {
	add_option('fmtcpod_cdn',		1);
}

add_action('admin_menu', 'fmtcpod_menu');
function fmtcpod_menu() {
	add_submenu_page('options-general.php', 'FMTC Pods', 'FMTC Pods', 0, __FILE__, 'fmtcpod_options');
}

/******************************************************************************
|
|	The Shortcode code
|
******************************************************************************/
// [fmtcpod pod="value" sid="yoursid"]
function fmtcpod_func($rsAttributes) {
	$bAdmin = current_user_can('activate_plugins');
	if (get_option('fmtcpod_cdn') && !$bAdmin) {
		$cHost = 'podcdn.formetocoupon.com';
	}
	else {
		$cHost = 'pods.formetocoupon.com';
	}

	$cJSON = @file_get_contents('http://' . $cHost . '/' . urlencode($rsAttributes['pod']) . '.json');

	$cReturn = '';
	$aOriginParts = explode('/', get_site_url());

	// $cJSON = false;
	if ($cJSON === false) {
		// Can't get the data
		$cReturn .= '<div id="div' . $rsAttributes['pod'] . '"></div>';
		$cReturn .= '<script src="//' . $cHost . '/' . urlencode($rsAttributes['pod']) . '.js?HTTP_HOST=';
		$cReturn .= urlencode(implode('/', array_slice($aOriginParts, 0, 3)));
		if (!empty($rsAttributes['sid'])) {
			$cReturn .= '&sid=' . urlencode($rsAttributes['sid']);
		}
		$cReturn .= '"></script>';
	}
	else {
		$rsJSON = json_decode($cJSON, true);
		$rsPod = $rsJSON['rsPod'];
		$aDeals = $rsJSON['aDeals'];

		$cPodName = htmlentities($rsAttributes['pod']);

		$cReturn .= <<<INLINE_SCRIPT
<script>
	if (!window.jQuery) {
		document.write('<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"><\/script>');
		$.noConflict();
	}
</script>

<script>
	window.addEventListener('message', function(e) {
		if (
			(e.origin == "http://pods.formetocoupon.com") ||
			(e.origin == "http://podcdn.formetocoupon.com") ||
			(e.origin == "http://pods.fmtc.co") ||
			(e.origin == "http://podcdn.fmtc.co")
			) {
			try{
				var rsPodData = JSON.parse(e.data);
			}catch(e){
				// alert(e);
			}

			if(rsPodData.pod_id == '$cPodName') {
				document.getElementById("fmtcpod_$cPodName").height = rsPodData.if_height;
			}
		}
		else {
			console.log(e.origin, e.data);
			return;
		}
	});
</script>
INLINE_SCRIPT;

		$cPodSrc = '//' . $cHost . '/' . urlencode($rsAttributes['pod']) . '.html?HTTP_HOST=';
		$cPodSrc .= urlencode(implode('/', array_slice($aOriginParts, 0, 3)));
		if (!empty($rsAttributes['sid'])) {
			$cPodSrc .= '&sid=' . urlencode($rsAttributes['sid']);
		}

		$cReturn .= '<iframe id="fmtcpod_' . htmlentities($rsAttributes['pod']) . '" src="about:blank" scrolling="no" border="0" frameborder="0" seamless="seamless" style="background-color: transparent; border: 0px none transparent; padding: 0px; overflow: hidden; max-width: 100%;" name="fmtcpod_' . htmlentities($rsAttributes['pod']) . '" width="' . $rsPod['nWidth'] . '" class="fmtciframe">';
		for ($i = 0; $i < min(count($aDeals), $rsPod['nMaxOffers']); $i++) {
			$rsDeal = $aDeals[$i];
			$cReturn .= "<p><a href=\"http://fmtc.co/" . $rsDeal['nCouponID'] . "/" . $rsPod['applicationCode'];
			if (!empty($rsAttributes['sid'])) {
				$cReturn .= '?sid=' . urlencode($rsAttributes['sid']);
			}
			$cReturn .= "\" target=\"_blank\" rel=\"nofollow\">" . htmlentities($rsDeal['cLabel']) . "</a>";
			if ($rsDeal['dtEndDate'] != '2050-12-31T23:59:59+00:00') {
				$cReturn .= "<br />\n\tExpires: " . date("F j, Y", strtotime($rsDeal['dtEndDate']));
			}
			$cReturn .= "</p>\n";
		}
		$cReturn .= '</iframe>';

		$cReturn .= <<<INLINE_SCRIPT
<script>
jQuery(window).ready(function(){
	document.getElementById("fmtcpod_$cPodName").src = '$cPodSrc';
});
</script>
INLINE_SCRIPT;
	}
	return $cReturn;
}
add_shortcode('fmtcpod', 'fmtcpod_func');


/******************************************************************************
|
|	The Editor / Popup code
|
******************************************************************************/
//add a button to the content editor, next to the media button
//this button will show a popup that contains inline content
add_action('media_buttons_context', 'add_fmtc_pod_button');

//add some content to the bottom of the page
//This will be shown in the inline modal
add_action('admin_footer-post.php', 'add_fmtc_pod_popup_content');
add_action('admin_footer-post-new.php', 'add_fmtc_pod_popup_content');

//action to add a custom button to the content editor
function add_fmtc_pod_button($context) {
	//path to my icon
	$img = plugins_url( 'penguin.png' , __FILE__ );

	//the id of the container I want to show in the popup
	$container_id = 'fmtc_pod_container';

	//our popup's title
	$title = 'FMTC Pods';

	//append the icon
	$context .= "<a class='button thickbox' title='{$title}' href='#TB_inline?width=400&inlineId={$container_id}'><i class=\"dashicons dashicons-admin-plugins\"></i> Add FMTC Pod</a>";

	return $context;
}

function add_fmtc_pod_popup_content() {
?>
<script>
window.addEventListener('message', function(e) {
	var message = e.data;
	// alert(message);

	var aParams = message.split("&");

	var aTmp = aParams[0].split('=');
	var cPodID = aTmp[1];

	aTmp = aParams[1].split('=');
	var cSubID = decodeURIComponent(decodeURIComponent(aTmp[1]));

	window.send_to_editor('[fmtcpod pod="' + cPodID + '" sid="' + cSubID + '"]');
});
</script>
<div id="fmtc_pod_container" style="display:none;">
	<center><iframe id="podiframe" src="http://account.formetocoupon.com/cp/wordpress/pods?HTTP_HOST=<?php

	$aOriginParts = explode('/', $_SERVER["SCRIPT_URI"]);

	echo(urlencode(implode('/', array_slice($aOriginParts, 0, 3))));

	?>" width="98%" height="220" seamless="seamless" scrolling="no" frameborder="0" style="background-color: transparent; border: 0px none transparent; padding: 0px; overflow: hidden; max-width: 100%;"></iframe></center>
</div>
<?php
}

function fmtcpod_options() {
	global $wpdb;
	?>
	<div class="wrap">
	<h2>FTMC Pod Options</h2>
	<?php

	if (isset($_POST)) {
		if (isset($_POST['submit']) && ($_POST['submit'] == 'Save Changes')) {
			// update_option('fmtc_post_author', 	$_POST['fmtc_post_author']);
			update_option('fmtcpod_cdn', 	(isset($_POST['fmtcpod_cdn']) && $_POST['fmtcpod_cdn']));0
			?>
			<div id="message" class="updated fade"><p><strong><?php _e('The options have been updated.'); ?></strong></p></div>
			<?php
		}
	}
	?>

	<form method="post" action="">
	   	<table class="form-table">
		<!-- FMTC API Key -->
		<tr valign="top">
			<th scope="row">Use CDN</th>
			<td><input type="checkbox" name="fmtcpod_cdn" value="1"<?= ((get_option('fmtcpod_cdn'))?' checked="checked"':'') ?>><br />
				<small>If you use our Content Distribution Network (CDN), your Pods are delivered <strong>faster</strong>, and you're only <strong>charged 50% of the regular impression rate</strong>.<br />
					If you're viewing your site as an <strong>admin</strong>, you'll always get <strong>live pods</strong>.<br />
				Pods delivered by the CDN are cached, and may not contain the latest deals.</small></td>
		</tr>

		<tr>
			<td><input type="hidden" name="action" value="update_fmtc_clipper" /></td>
			<td><?php submit_button(translate('Save Changes')); ?></td>
		</tr>
		</table>
	</form>
	</div>
	<?php
}
?>