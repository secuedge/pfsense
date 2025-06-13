<?php
/*
 * authgui.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

include_once("auth.inc");
include_once("config.inc");
include_once("config.lib.inc");
include_once("priv.inc");
if (!function_exists('platform_booting')) {
	require_once('globals.inc');
}
require_once('pfsense-utils.inc');

/* Authenticate user - exit if failed */
if (!session_auth()) {
	display_login_form();
	exit;
}

phpsession_begin();

/*
 * Once here, the user has authenticated with the web server.
 * We give them access only to the appropriate pages based on
 * the user or group privileges.
 */
$allowedpages = getAllowedPages($_SESSION['Username'], $_SESSION['user_radius_attributes']);

/*
 * Get user-based preference settings so they can be easily referenced.
 */
$user_settings = get_user_settings($_SESSION['Username']);

/*
 * redirect to first allowed page if requesting a wrong url
 */

/* Fix this up otherwise the privilege check will fail. See Redmine #5909. */
if ($_SERVER['REQUEST_URI'] == "/") {
	$_SERVER['REQUEST_URI'] = "/index.php";
}

if (!isAllowedPage($_SERVER['REQUEST_URI'])) {
	if (count($allowedpages) > 0) {
		$page = str_replace('*', '', $allowedpages[0]);
		$_SESSION['Post_Login'] = true;
		require_once("functions.inc");
		pfSenseHeader("/{$page}");

		$username = get_config_user();
		log_error("{$username} attempted to access {$_SERVER['SCRIPT_NAME']} but does not have access to that page. Redirecting to {$page}.");

		exit;
	} else {
		// add this so they don't get stuck on the logout page when they have no permissions.
		$_SESSION["Logged_In"] = false;
		display_error_form("201", gettext("No page assigned to this user! Click here to logout."));

		exit;
	}
} else {
	$_SESSION['Post_Login'] = true;
}

/*
 * redirect browsers post-login to avoid pages
 * taking action in response to a POST request
 */
if (!$_SESSION['Post_Login']) {
	$_SESSION['Post_Login'] = true;
	require_once("functions.inc");
	pfSenseHeader($_SERVER['REQUEST_URI']);
	exit;
}

/*
 * Close session data to allow other scripts from same host to come in.
 * A session can be reactivated from calling phpsession_begin again
 */
phpsession_end(true);

/*
 * determine if the user is allowed access to the requested page
 */
function display_error_form($http_code, $desc) {
	if (isAjax()) {
		printf(gettext('Error: %1$s Description: %2$s'), $http_code, $desc);
		return;
	}

	$logincssfile = "#770101";
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
	    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
	    <link rel="stylesheet" href="/css/login.css?v=<?=filemtime('/usr/local/www/css/login.css')?>" type="text/css">
	    <link rel="stylesheet" href="/css/custom-theme.css?v=<?=filemtime('/usr/local/www/css/custom-theme.css')?>" type="text/css">
		<title><?=gettext("Error"); ?></title>
	</head>

	<body id="error" >
		<div id="total">
			<header>
				<div id="headerrow">
					<div class="row">
						<div class="col-sm-4">
							<div id="logodiv" style="text-align:center" class="nowarning">
								<object data="/logo.svg" type="image/svg+xml" height="60" width="240" class="logo">
									Security Gateway
								</object>
							</div>
						</div>
						<div class="col-sm-8 nowarning msgbox text-center">
							<span id="hostspan">
							</span>
						</div>
					</div>
				</div>
			</header>

			<div style="background: <?=$logincssfile?>;" class="pagebody">
				<div class="col-sm-2"></div>

				<div class="col-sm-8 offset-md-4 logoCol">
					<div class="loginCont center-block error-panel">
						<a href="index.php?logout"><?=$desc;?></a>
					</div>
				</div>

			<div class="col-sm-2"></div>
			</div>

			<footer id="3">
			<div id="footertext">
					<p class="text-muted">
						<?=print_credit()?>
					</p>
				</div>
			</footer>
		</div>
	</body>
</html>

<?php

} // end function


function display_login_form() {
	require_once("globals.inc");
	global $g;

	unset($input_errors);

	if (isAjax()) {
		if (isset($_POST['login'])) {
			if ($_SESSION['Logged_In'] <> "True") {
				isset($_SESSION['Login_Error']) ? $login_error = $_SESSION['Login_Error'] : $login_error = gettext("unknown reason");
				printf("showajaxmessage('" . gettext("Invalid login (%s).") . "')", $login_error);
			}
			if (file_exists("{$g['tmp_path']}/webconfigurator.lock")) {
				// TODO: add the IP from the user who did lock the device
				$whom = file_get_contents("{$g['tmp_path']}/webconfigurator.lock");
				printf("showajaxmessage('" . gettext("This device is currently being maintained by: %s.") . "');", $whom);
			}
		}
		//If session ended
		echo "SESSION_TIMEOUT";
		exit;
	}

	/* Check against locally configured IP addresses, which will catch when someone
	   port forwards WebGUI access from WAN to an internal IP on the router. */
	global $FilterIflist;

	$local_ip = false;

	if (strpos($_SERVER['HTTP_HOST'], ":") === FALSE) {
		$http_host_port = explode(":", $_SERVER['HTTP_HOST']);
		$http_host = $http_host_port[0];
	} else {
		$http_host = $_SERVER['HTTP_HOST'];
	}

	if (empty($FilterIflist)) {
		require_once('filter.inc');
		require_once('shaper.inc');
		filter_generate_optcfg_array();
	}

	foreach ($FilterIflist as $iflist) {
		if ($iflist['ip'] == $http_host) {
			$local_ip = true;
		} else if ($iflist['ipv6'] == $http_host) {
			$local_ip = true;
		} else if (is_array($iflist['vips'])) {
			foreach ($iflist['vips'] as $vip) {
				if ($vip['ip'] == $http_host) {
					$local_ip = true;
					break;
				}
			}

			unset($vip);
		}

		if ($local_ip == true) {
			break;
		}
	}

	unset($FilterIflist);
	unset($iflist);

	if ($local_ip == false) {
		foreach (config_get_path('openvpn/openvpn-server', []) as $ovpns) {
			if (is_ipaddrv4($http_host) && !empty($ovpns['tunnel_network']) && ip_in_subnet($http_host, $ovpns['tunnel_network'])) {
				$local_ip = true;
			} else if (is_ipaddrv6($http_host) && !empty($ovpns['tunnel_networkv6']) && ip_in_subnet($http_host, $ovpns['tunnel_networkv6'])) {
				$local_ip = true;
			}

			if ($local_ip == true) {
				break;
			}
		}
	}

	// For the login form, get the settings of no particular user.
	// That ensures we will use the system default theme for the login form.
	$user_settings = get_user_settings("");
	$logincssfile = "#1e3f75";

	if (isset($user_settings['webgui']['logincss']) && strlen($user_settings['webgui']['logincss']) == 6) {
		$logincssfile = "#" . $user_settings['webgui']['logincss'];
	}

	if (config_path_enabled('system/webgui','loginshowhost')) {
		$loginbannerstr = sprintf(gettext('%1$s.%2$s'),
								  htmlspecialchars(config_get_path('system/hostname', "")),
								  htmlspecialchars(config_get_path('system/domain', "")));
		$login_title = gettext(htmlspecialchars(config_get_path('system/hostname', "") . " - Login"));
	} Else {
		$loginbannerstr = sprintf(gettext('Login to %1$s'), g_get('product_label'));
		$login_title = sprintf(gettext("%s - Login"), g_get('product_label'));
	}

	$loginautocomplete = config_path_enabled('system/webgui','loginautocomplete') ? '' : 'autocomplete="off"';

	if (is_ipaddr($http_host) && !$local_ip &&
		!config_path_enabled('system/webgui','nohttpreferercheck')) {
		$warnclass = "pagebodywarn";	// Make room for a warning display row
	} else {
		$warnclass = "pagebody";
	}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
	    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
	    <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" type="text/css">
	    <link rel="stylesheet" href="/css/login-modern.css?v=<?=filemtime('/usr/local/www/css/login-modern.css')?>" type="text/css">
	    <link rel="stylesheet" href="/css/custom-theme.css?v=<?=filemtime('/usr/local/www/css/custom-theme.css')?>" type="text/css">
		<title><?=$login_title; ?></title>
		<script type="text/javascript">
			//<![CDATA{
			var events = events || [];
			//]]>
		</script>
	</head>

	<body id="login">
		<div class="shape shape-1"></div>
		<div class="shape shape-2"></div>
		<div class="shape shape-3"></div>
		<div id="total">
			<div class="<?=$warnclass?>">
				<div class="container">
					<div class="loginCont">
						<div class="login-logo">
							<img src="/SecuEdge_Logo.png" alt="SecuEdge Logo" class="logo">
						</div>
						<form method="post" <?=$loginautocomplete?> class="login">
							<p class="form-title">Login to SecuEdge</p>
							<?php if (!empty($_POST['usernamefld'])): ?>
							<div class="msgbox text-center text-danger">
								<h4><i class='fas fa-exclamation-circle'></i> <?=$_SESSION['Login_Error']?></h4>
							</div>
							<?php endif; ?>
							<div class="input-wrapper">
								<i class="fas fa-user input-icon"></i>
								<input name="usernamefld" id="usernamefld" type="text" class="input-with-icon" placeholder="Username" autocorrect="off" autocapitalize="none"/>
							</div>
							<div class="input-wrapper">
								<i class="fas fa-lock input-icon"></i>
								<input name="passwordfld" id="passwordfld" type="password" class="input-with-icon" placeholder="Password" />
							</div>
							<?php if ($warnclass == "pagebodywarn"): ?>
							<div class="alert alert-warning">
								<i class="fas fa-exclamation-triangle"></i>
								<?=gettext("The IP address being used to access this router is not configured locally, which may be forwarded by NAT or other means.
								If this forwarding is unexpected, it should be verified that a man-in-the-middle attack is not taking place.")?>
							</div>
							<?php endif; ?>
							<input type="submit" name="login" value="Sign In" />
						</form>
					</div>
				</div>
			</div>
		</div>

		<script src="/vendor/jquery/jquery-3.5.1.min.js"></script>
		<script src="/vendor/bootstrap/js/bootstrap.min.js"></script>
		<script src="/js/pfSense.js"></script>

		<script type="text/javascript">
		//!<[CDATA[
		events.push(function() {
			document.cookie=
				"cookie_test=1" +
				"<?php echo config_get_path('system/webgui/protocol') == 'https' ? '; secure' : '';?>";

			if (document.cookie.indexOf("cookie_test") == -1) {
				alert("<?=gettext('The browser must support cookies to login.')?>");
			}

			// Delete it
			document.cookie = "cookie_test=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";
		});
		//]]>
		</script>
	</body>
</html>

<?php
} // end function
