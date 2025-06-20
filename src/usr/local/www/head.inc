<?php
/*
 * head.inc
 *
 * part of pfSense (https://www.pfsense.org)
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

require_once("globals.inc");
require_once("functions.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");
require_once('notices.inc');

header('Content-Type: text/html; charset=utf-8');

$pagetitle = gentitle($pgtitle);
$system_url = $config['system']['hostname'] . "." . $config['system']['domain'];

if ($user_settings['webgui']['pagenamefirst']) {
	$tabtitle = $pagetitle . " - " . htmlspecialchars($system_url);
} else {
	$tabtitle = htmlspecialchars($system_url) . " - " . $pagetitle;
}

$cssfile = "/css/pfSense.css";

if (isset($user_settings['webgui']['webguicss'])) {
	if (file_exists("/usr/local/www/css/" . $user_settings['webgui']['webguicss'])) {
		$cssfile = "/css/" . $user_settings['webgui']['webguicss'];
	}
}

// set default columns to two if unset
if (!isset($config['system']['webgui']['dashboardcolumns'])) {
	$config['system']['webgui']['dashboardcolumns'] = 2;
}

// Initialize authentication variables with defaults
$authmode = isset($_SESSION['authmode']) ? $_SESSION['authmode'] : '';
$user_settings = isset($config['system']['webgui']) ? $config['system']['webgui'] : array();
$user_lang = isset($user_settings['language']) ? $user_settings['language'] : 'en_US';

if (!function_exists('display_error_modal')) {
    function display_error_modal($title, $message) {
        echo '<div class="modal fade" id="errorModal" tabindex="-1" role="dialog">';
        echo '<div class="modal-dialog" role="document">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h4 class="modal-title">' . htmlspecialchars($title) . '</h4>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '</div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

// Check for required session variables
if (!isset($_SESSION['Username'])) {
    $_SESSION['Username'] = '';
}

// Initialize page variables
$pgtitle = isset($pgtitle) ? $pgtitle : array();
$pglinks = isset($pglinks) ? $pglinks : array();
$shortcut_section = isset($shortcut_section) ? $shortcut_section : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Logo and favicon references -->
	<link rel="icon" href="/logo.svg" type="image/svg+xml">
	<link rel="apple-touch-icon-precomposed" sizes="144x144" href="/logo.svg">
	<link rel="apple-touch-icon-precomposed" sizes="114x114" href="/logo.svg">
	<link rel="apple-touch-icon-precomposed" sizes="72x72" href="/logo.svg">
	<link rel="apple-touch-icon-precomposed" href="/logo.svg">
	
	<link rel="apple-touch-icon-precomposed" href="/apple-touch/apple-touch-icon-iphone-60x60-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="60x60" href="/apple-touch/apple-touch-icon-ipad-76x76-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="114x114" href="/apple-touch/apple-touch-icon-iphone-retina-120x120-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="144x144" href="/apple-touch/apple-touch-icon-ipad-retina-152x152-precomposed.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/manifest.json">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/all.min.css')?>">
	<link rel="stylesheet" href="/vendor/font-awesome/css/v4-shims.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/v4-shims.css')?>">
	<link rel="stylesheet" href="/vendor/sortable/sortable-theme-bootstrap.css?v=<?=filemtime('/usr/local/www/vendor/sortable/sortable-theme-bootstrap.css')?>">
	<link rel="stylesheet" href="/vendor/jquery-treegrid/css/jquery.treegrid.css?v=<?=filemtime('/usr/local/www/vendor/jquery-treegrid/css/jquery.treegrid.css')?>">
	<link rel="stylesheet" href="<?=$cssfile?>?v=<?=filemtime('/usr/local/www/' . $cssfile)?>" />
	<link rel="stylesheet" href="/css/custom-theme.css?v=<?=filemtime('/usr/local/www/css/custom-theme.css')?>" />

	<title><?=$tabtitle?></title>
	<script type="text/javascript">
	//<![CDATA[
	var events = events || [];
	//]]>
	</script>
	<style>
		.navbar-brand img {
			height: 40px;
			width: auto;
		}
	</style>
</head>

<?php

/* Determine automated help URL. Should output the page name and parameters
   separately */
$uri_split = "";
preg_match("/\/(.*)\?(.*)/", $_SERVER["REQUEST_URI"], $uri_split);

/* If there was no match, there were no parameters, just grab the filename
   Otherwise, use the matched filename from above. */
if (empty($uri_split[0])) {
	$pagename = ltrim($_SERVER["REQUEST_URI"], '/');
} else {
	$pagename = $uri_split[1];
}

/* If the page name is still empty, the user must have requested / (index.php) */
if (empty($pagename)) {
	$pagename = "index.php";
}

/* If the filename is pkg_edit.php or wizard.php, reparse looking
	for the .xml filename */
if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 4) == "xml=") {
			$xmlfile = explode('=', $param);
			$pagename = $xmlfile[1];
		}
	}
} else if ($pagename == "status_logs.php") {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 8) == "logfile=") {
			$logtype = explode('=', $param);
			$pagename .= '-' . $logtype[1];
		}
	}
}

// Build the full help URL.
$helpurl .= "{$g['help_base_url']}?page={$pagename}";

/*
 * Read files from $g['ext_menu_path']/*.xml and fill an array with menu info
 */
function read_ext_menu_path_data() {
	global $g;

	$result = array();

	if (!is_dir(g_get('ext_menu_path'))) {
		return $result;
	}

	foreach (glob("{$g['ext_menu_path']}/*.xml") as $menu_xml) {
		$xml_data = parse_xml_config_pkg($menu_xml, "packagegui");
		if (empty($xml_data['menu'])) {
			continue;
		}
		foreach ($xml_data['menu'] as $menu) {
			$result[] = $menu;
		}
	}

	return $result;
}

// Create a menu entry of any installed packages in the specified category
// (Now reads the menu information from $config['installedpackages']['menu'] only)
function return_ext_menu($section) {
	global $config, $ext_menu_path_data;

	$htmltext = "";
	$extarray = array();
	$ext_menu_entries = array();

	if ((!empty($config['installedpackages']['package'])) && (!empty($config['installedpackages']['menu']))) {
		foreach ($config['installedpackages']['menu'] as $menu) {
			if (isset($menu['name']) && ($menu['name'] != "AutoConfigBackup")) { // AutoConfigBackup was moved to a built-in function
	//			print('Name: ' . $menu['name'] . ', Pkg category: ' . $menu['category'] . ', Section: ' . $section . '<br />');
				if (isset($menu['section']) && ($menu['section'] == $section)) {
					$ext_menu_entries[] = $menu;
				}
			}
		}
	}

	foreach ($ext_menu_path_data as $menu) {
		if ($menu['section'] == $section) {
			$ext_menu_entries[] = $menu;
		}
	}

	foreach ($ext_menu_entries as $menu) {
		if ($menu['url'] != "") {
			$test_url = $menu['url'];
			$addresswithport = getenv("HTTP_HOST");
			$colonpos = strpos($addresswithport, ":");

			if ($colonpos !== false) {
				//my url is actually just the IP address of the pfsense box
				$myurl = substr($addresswithport, 0, $colonpos);
			} else {
				$myurl = $addresswithport;
			}
			$description = str_replace('$myurl', $myurl, $menu['url']);
		} else {
			$description = '/pkg.php?xml=' . $menu['configfile'];
			$test_url=$description;
		}

		if (isAllowedPage($test_url)) {
			$extarray[] = array($menu['name'], $description);
		}
	}

	return $extarray;
}

function output_menu($arrayitem, $target = null, $section = "") {
	$output = "";

	foreach ($arrayitem as $item) {
		if (isAllowedPage($item[1]) || $item[1] == "/index.php?logout" ||
			(($section == "Help") && isAllowedPage("help.php")) ||
			(substr($item[1], 0, 8) == "https://")) {
			
			$attr = sprintf("href=\"%s\"", htmlentities($item[1]));

			if ($target) {
				$attr .= sprintf(" target=\"%s\"", htmlentities($target));
			}

			$class = "navlnk";

			if ($item['class']) {
				$class .= " {$item['class']}";
			}

			$attr .= sprintf(" class=\"%s\"", htmlentities($class));

			if ($item['style']) {
				$attr .= sprintf(" style=\"%s\"", htmlentities($item['style']));
			}

			if ($item[0] == '-DIVIDER-') {
				$output .= ' <li class="divider"></li>';
			} else {
				$icon = isset($item[2]) ? "<i class=\"fa {$item[2]}\"></i> " : "";
				$output .= "<li>". sprintf("<a %s %s>%s%s</a>", $attr, ($item[1] == "/index.php?logout") ? "usepost":"", $icon, $item[0]) . "</li>\n";
			}
		}
	}

	return $output;
}

$ext_menu_path_data = read_ext_menu_path_data();

// System
$system_menu = array();
$system_menu[] = array(gettext("General Setup"), "/system.php", "fa-cog");
$system_menu[] = array(gettext("Advanced"), "/system_advanced_admin.php", "fa-sliders-h");
$system_menu[] = array(gettext("High Availability"), "/system_hasync.php", "fa-server");
$system_menu[] = array(gettext("Setup Wizard"), "/wizard.php?xml=setup_wizard.xml", "fa-magic");
$system_menu[] = array(gettext("Routing"), "/system_gateways.php", "fa-route");

if (isAllowedPage("system_camanager.php")) {
	$system_menu[] = array(gettext("Certificates"), "/system_camanager.php", "fa-certificate");
} elseif (isAllowedPage("system_certmanager.php")) {
	$system_menu[] = array(gettext("Certificates"), "/system_certmanager.php", "fa-certificate");
} elseif (isAllowedPage("system_crlmanager.php")) {
	$system_menu[] = array(gettext("Certificates"), "/system_crlmanager.php", "fa-certificate");
}

if (!isAllowedPage("system_usermanager.php")) {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager_passwordmg.php", "fa-users");
} else {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager.php", "fa-users");
}

if ($user_settings['customsettings'] && isAllowedPage("system_user_settings.php")) {
	$system_menu[] = array(gettext("User Settings"), "/system_user_settings.php", "fa-user-cog");
}

$system_menu = msort(array_merge($system_menu, return_ext_menu("System")), 0);

/* ensure Logout is the last item in the menu */
$system_menu[] = array(gettext("Logout") . " (" . $_SESSION['Username'] . ")", "/index.php?logout", "fa-sign-out-alt");

// Interfaces
$interfaces_menu = array();
$interfaces_top = array();
$interfaces_bottom = array();

if (!isset($config['system']['webgui']['noassigninterfaces'])) {
	$interfaces_top[] = array(gettext("Assignments"), "/interfaces_assign.php", "fa-network-wired");
	$div = true;
}

$platform = system_identify_specific_platform();

if ($platform['name'] == "uFW") {
	$interfaces_top[] = array(gettext("Switches"), "/switch_system.php", "fa-switch");
}

$opts = get_configured_interface_with_descr(true);

foreach ($opts as $oif => $odescr) {
	if (!isset($config['interfaces'][$oif]['ovpn'])) {
		$interfaces_bottom[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}", "fa-ethernet");
	}
}

$interfaces_bottom = array_merge($interfaces_bottom, return_ext_menu("Interfaces"));

if ($user_settings['webgui']['interfacessort']) {
	$interfaces_bottom = msort($interfaces_bottom, 0);
}

// Combine the top section, the divider and the bottom section of this menu
$interfaces_menu = array_merge($interfaces_top, [array(0 => "-DIVIDER-")], $interfaces_bottom);

// Firewall
$firewall_menu = array();
$firewall_menu[] = array(gettext("Rules"), "/firewall_rules.php", "fa-shield-alt");
$firewall_menu[] = array(gettext("NAT"), "/firewall_nat.php", "fa-random");
$firewall_menu[] = array(gettext("Aliases"), "/firewall_aliases.php", "fa-tags");
$firewall_menu[] = array(gettext("Schedules"), "/firewall_schedule.php", "fa-clock");
$firewall_menu[] = array(gettext("Traffic Shaper"), "/firewall_shaper.php", "fa-tachometer");
$firewall_menu[] = array(gettext("Virtual IPs"), "/firewall_virtual_ip.php", "fa-network-wired");
$firewall_menu = msort(array_merge($firewall_menu, return_ext_menu("Firewall")), 0);

// Services
$services_menu = array();
$services_menu[] = array(gettext("DHCP Server"), "/services_dhcp.php", "fa-network-wired");
$services_menu[] = array(gettext("DNS Forwarder"), "/services_dnsmasq.php", "fa-globe");
$services_menu[] = array(gettext("DNS Resolver"), "/services_unbound.php", "fa-search");
$services_menu[] = array(gettext("Content Filtering"), "/content_filter.php", "fa-shield-alt");
$services_menu[] = array(gettext("Captive Portal"), "/services_captiveportal.php", "fa-door-open");
$services_menu[] = array(gettext("NTP"), "/services_ntpd.php", "fa-clock");
$services_menu[] = array(gettext("SNMP"), "/services_snmp.php", "fa-chart-line");
$services_menu[] = array(gettext("Wake-on-LAN"), "/services_wol.php", "fa-power-off");
$services_menu = msort(array_merge($services_menu, return_ext_menu("Services")), 0);

// VPN
$vpn_menu = array();
$vpn_menu[] = array(gettext("IPsec"), "/vpn_ipsec.php", "fa-lock");
$vpn_menu[] = array(gettext("OpenVPN"), "/vpn_openvpn_server.php", "fa-shield-alt");
$vpn_menu[] = array(gettext("L2TP"), "/vpn_l2tp.php", "fa-network-wired");
$vpn_menu = msort(array_merge($vpn_menu, return_ext_menu("VPN")), 0);

// Status
$status_menu = array();
$status_menu[] = array(gettext("Interfaces"), "/status_interfaces.php", "fa-network-wired");
$status_menu[] = array(gettext("Gateways"), "/status_gateways.php", "fa-route");
$status_menu[] = array(gettext("Services"), "/status_services.php", "fa-cogs");
$status_menu[] = array(gettext("System Logs"), "/status_logs.php", "fa-clipboard-list");
$status_menu[] = array(gettext("Traffic Graph"), "/status_graph.php", "fa-chart-line");
$status_menu[] = array(gettext("DHCP Leases"), "/status_dhcp_leases.php", "fa-network-wired");
$status_menu[] = array(gettext("OpenVPN"), "/status_openvpn.php", "fa-shield-alt");
$status_menu[] = array(gettext("IPsec"), "/status_ipsec.php", "fa-lock");
$status_menu = msort(array_merge($status_menu, return_ext_menu("Status")), 0);

// Diagnostics
$diagnostics_menu = array();
$diagnostics_menu[] = array(gettext("System Activity"), "/diag_system_activity.php", "fa-chart-line");
$diagnostics_menu[] = array(gettext("Command Prompt"), "/diag_command.php", "fa-terminal");
$diagnostics_menu[] = array(gettext("Ping"), "/diag_ping.php", "fa-network-wired");
$diagnostics_menu[] = array(gettext("Traceroute"), "/diag_traceroute.php", "fa-route");
$diagnostics_menu[] = array(gettext("DNS Lookup"), "/diag_dns.php", "fa-search");
$diagnostics_menu[] = array(gettext("Packet Capture"), "/diag_packet_capture.php", "fa-network-wired");
$diagnostics_menu[] = array(gettext("ARP Table"), "/diag_arp.php", "fa-table");
$diagnostics_menu[] = array(gettext("Routes"), "/diag_routes.php", "fa-route");
$diagnostics_menu = msort(array_merge($diagnostics_menu, return_ext_menu("Diagnostics")), 0);

if (!g_get('disablehelpmenu')) {
	$help_menu = array();
	$help_menu[] = array(gettext("About this Page"), $helpurl);
	if (g_get('product_name') == "pfSense") {
		$help_menu[] = array(gettext("Bug Database"), "https://redirects.netgate.com/issues");
	}

	$help_menu[] = array(gettext("User Forum"), "https://redirects.netgate.com/forum");
	$help_menu[] = array(gettext("Documentation"), "https://redirects.netgate.com/docs");
	$help_menu[] = array(gettext("Paid Support"), "https://redirects.netgate.com/support");
	$help_menu[] = array(gettext("pfSense Book"), "https://redirects.netgate.com/book");
	$help_menu[] = array(gettext("FreeBSD Handbook"), "https://redirects.netgate.com/fbsdhandbook");
	$help_menu[] = array(gettext("User survey"), "https://redirects.netgate.com/survey_1");
	if  (strpos(g_get('product_label'), 'Plus') === false) {
		$help_menu[] = array(gettext("Upgrade to pfSense Plus"), "https://redirects.netgate.com/upgrade");
	}
	$help_menu = msort(array_merge($help_menu, return_ext_menu("Help")), 0);
}

$menuclass = "static";

if ($user_settings['webgui']['webguifixedmenu'] == "fixed") {
	$menuclass = "fixed";
}

$numColumns = (int) $user_settings['webgui']['dashboardcolumns'];

if (($pagename === "index.php") && ($numColumns > 2)) {
	$columnsContainer = 'style="max-width: ' . 585*$numColumns . 'px;width: 100%"';
}

$display_notices = false;
$allow_clear_notices = false;

if (are_notices_pending()) {
	// Evaluate user privs to determine if notices should be displayed, and if the user can clear them.
	$user_entry = getUserEntry($_SESSION['Username']);
	if (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "user-view-clear-notices") || userHasPrivilege($user_entry, "page-all")) {
		$display_notices = true;
		$allow_clear_notices = true;
	} elseif (userHasPrivilege($user_entry, "user-view-notices")) {
		$display_notices = true;
	}
}

if ($display_notices) {
    $notices = get_notices();
    if (!is_array($notices)) {
        $notices = array();
	}
}
?>
<body id="<?=$numColumns?>" class="<?=$g['theme']?>">
<div class="wrapper">
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="/" class="navbar-brand">
                <img src="/logo.svg" alt="SecuEdge Logo" class="logo">
                <span class="logo-text">SecuEdge</span>
            </a>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="/index.php" class="nav-link <?=($page == 'index.php') ? 'active' : ''?>">
                    <i class="fa fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- System Menu -->
            <li class="nav-item">
                <a href="#systemMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-cog"></i>
                    <span>System</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="systemMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($system_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>

            <!-- Interfaces Menu -->
            <li class="nav-item">
                <a href="#interfacesMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-network-wired"></i>
                    <span>Interfaces</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="interfacesMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($interfaces_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>

            <!-- Firewall Menu -->
            <li class="nav-item">
                <a href="#firewallMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-shield-alt"></i>
                    <span>Firewall</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="firewallMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($firewall_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>

            <!-- Services Menu -->
            <li class="nav-item">
                <a href="#servicesMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-cogs"></i>
                    <span>Services</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="servicesMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($services_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>

            <!-- VPN Menu -->
            <li class="nav-item">
                <a href="#vpnMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-lock"></i>
                    <span>VPN</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="vpnMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($vpn_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>

            <!-- Status Menu -->
            <li class="nav-item">
                <a href="#statusMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-chart-line"></i>
                    <span>Status</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="statusMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($status_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>

            <!-- Diagnostics Menu -->
            <li class="nav-item">
                <a href="#diagnosticsMenu" class="nav-link has-submenu" data-toggle="collapse" aria-expanded="false">
                    <i class="fa fa-stethoscope"></i>
                    <span>Diagnostics</span>
                    <i class="fa fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse submenu" id="diagnosticsMenu">
                    <ul class="nav flex-column">
                        <?php foreach ($diagnostics_menu as $item): ?>
                            <?php if ($item[0] == '-DIVIDER-'): ?>
                                <li class="divider"></li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a href="<?=$item[1]?>" class="nav-link">
                                        <?php if (isset($item[2])): ?>
                                            <i class="fa <?=$item[2]?>"></i>
                                        <?php endif; ?>
                                        <span><?=$item[0]?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        </ul>

        <div class="sidebar-footer">
            <?php if ($display_notices): ?>
                <a href="#" data-toggle="modal" data-target="#notices" class="nav-link">
                    <i class="fa fa-bell"></i>
                    <span class="badge"><?=count($notices)?></span>
                </a>
            <?php endif; ?>
            <a href="/index.php?logout" usepost class="nav-link">
                <i class="fa fa-sign-out-alt"></i>
                <span><?=gettext("Logout") . " (" . $_SESSION['Username'] . ")"?></span>
            </a>
        </div>
    </nav>

    <div id="content">
        <?php if (isset($notitle)): ?>
            <br />
        <?php endif; ?>
        <div class="container-fluid">

<?php
	// Print a warning if current user = admin and the password hash is still set to the default value
	if ($_SESSION['Username'] == "admin") {
		$cu = getUserEntry("admin");

		if (local_backed("admin", g_get('factory_shipped_password'))) {
			print('<div class="alert alert-danger">' .
				sprintf(gettext('%sWARNING:%s The \'admin\' account password is set to the default value. ' .
				' %s Change the password in the User Manager.%s'),
				'<strong>', '</strong>', '<a href="/system_usermanager.php?act=edit&userid=' . $cu['uid'] . '">', '</a>') .
				'</div>');
		}
	}
?>

	<header class="header">

<?php
	// If you set $notitle = true BEFORE including head.inc, the page title will be supressed
	if (isset($notitle)) {
		print('<br />');
		unset($notitle);
	} else {
		if (isset($pglinks)) {
			print(genhtmltitle($pgtitle, $pglinks));
		} else {
			print(genhtmltitle($pgtitle));
		}
	}
?>
		<ul class="context-links">

	<?php if (isset($widgets)): ?>
		<li>
			<a href="#" title="<?=gettext("Save dashboard layout")?>" id="btnstore" class="invisible">
				<i class="fa fa-save icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($system_logs_filter_form_hidden): ?>
		<li>
			<a onclick="$('#filter-form').toggle(360)" title="<?=gettext("Log filter")?>">
				<i class="fa fa-filter icon-pointer"></i>
			</a>
		</li>
	<?php endif ?>

	<?php if ($system_logs_manage_log_form_hidden):
			/* If the user does not have access to status logs settings page, then exclude the manage log panel icon from the title bar. */
			if (isAllowedPage("status_logs_settings.php")) {
	?>
		<li>
			<a onclick="$('#manage-log-form').toggle(360)" title="<?=gettext("Manage log")?>">
				<i class="fa fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php	}
		endif
	?>

	<?php if ($monitoring_settings_form_hidden): ?>
		<li>
			<a onclick="$('#monitoring-settings-form').toggle(360);" title="<?=gettext("Settings")?>">
				<i class="fa fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($status_monitoring): ?>
		<li>
			<a class="update-graph" title="<?=gettext("Refresh Graph")?>">
				<i class="fa fa-repeat icon-pointer"></i>
			</a>
		</li>
		<li>
			<a class="export-graph" id="export-graph" title="<?=gettext("Export Graph")?>">
				<i class="fa fa-download icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

<?php
/* Determine shortcut section for XML-based packages */
if (empty($shortcut_section) && !empty($xmlfile)) {
	$shortcut_section = basename($pagename, '.xml');
}

if (!$hide_service_status && !empty($shortcuts[$shortcut_section]['service']) && isAllowedPage('status_services.php')) {
	$ssvc = array();
	switch ($shortcut_section) {
		case "openvpn":
			$ssvc = find_service_by_openvpn_vpnid($vpnid);
			break;
		case "captiveportal":
			$ssvc = find_service_by_cp_zone($cpzone);
			break;
		default:
			$ssvc = find_service_by_name($shortcuts[$shortcut_section]['service']);
	}
	if (!empty($ssvc)) {
		// echo '<li>'. get_service_status_icon($ssvc, false). '</li>'; TODO: Add missing function
		echo '<li>' . get_service_control_links($ssvc, false) . '</li>';
	}
}

if (('' != ($link = get_shortcut_main_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['main']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_status_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['status']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_log_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['log']))) {
	echo '<li>' . $link . '</li>';
}

?>
		</ul>
	</header>
<?php
/* if upgrade in progress, alert user */
$warning_text = "";
if (file_exists('/conf/needs_package_sync') && platform_booting()) {
	$warning_text = sprintf(gettext(
	    '%1$s%3$s is booting, then packages will be reinstalled in the ' .
	    'background.%2$s%1$sDo not make changes in the GUI until this is ' .
	    'complete.%2$s'), '<p>', '</p>', g_get('product_label'));
} elseif (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	$warning_text = sprintf(gettext('%1$sPackages are currently being ' .
	    'reinstalled in the background.%2$s%1$sDo not make changes in ' .
	    'the GUI until this is complete.%2$s'), '<p>', '</p>');
	$warning_text .= sprintf(gettext('%1$sIf the above message is still ' .
	    'displayed after a couple of hours, use the \'Clear Package ' .
	    'Lock\' button on the %3$s page and reinstall packages manually.' .
	    '%2$s'), '<p>', '</p>', sprintf('<a href="diag_backup.php" ' .
	    'title="%1$s &gt; %2$s">%1$s &gt; %2$s</a>', gettext('Diagnostics'),
	    htmlspecialchars(gettext('Backup & Restore'))));
}

if (!empty($warning_text)) {
	print_info_box($warning_text);
}

/*	If this page is being remotely managed then do not allow the loading of the contents. */
if ($config['remote_managed_pages']['item']) {
	foreach ($config['remote_managed_pages']['item'] as $rmp) {
		if ($rmp == $_SERVER['SCRIPT_NAME']) {
			print_info_box(gettext("This page is currently being managed by a remote machine."));
			include("foot.inc");
			exit;
		}
	}
}

// Modal notices window
// The notices modal needs to be outside of the page display div or things get messy
if ($display_notices):
?>

<div id="notices" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>

				<h3 class="modal-title" id="myModalLabel"><?=gettext("Notices")?></h3>
			</div>

			<div class="modal-body">
<?php
	$noticeCategories = array();

	if (is_array($notices)) {
		foreach ($notices as $time => $notice) {
			if (!isset($noticeCategories[ $notice['category'] ])) {
				$noticeCategories[ $notice['category'] ] = array();
			}

			$notice['time'] = $time;
			array_push($noticeCategories[ $notice['category'] ], $notice);
		}
	}

	foreach ($noticeCategories as $category => $catNotices):?>
				<h4><?=$category?></h4>
				<ul>
<?php
	foreach ($catNotices as $notice):
?>
					<li>
						<b>
<?php if (!empty($notice['url'])):?>
							<a href="<?=htmlspecialchars($notice['url'])?>"><?=htmlspecialchars($notice['id'])?></a> -
<?php endif;?>
						</b>
						<?=str_replace("\n", "<br/>", htmlspecialchars(htmlspecialchars_decode(htmlspecialchars_decode($notice['notice']))))?>
						<i>@ <?=date('Y-m-d H:i:s', $notice['time'])?></i>
					</li>
<?php	endforeach;?>
				</ul>
<?php endforeach;?>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-info" data-dismiss="modal"><i class="fa fa-times icon-embed-btn"></i><?=gettext("Close")?></button>
<?php if ($allow_clear_notices && isAllowedPage("/index.php")):?>
				<button type="button" id="clearallnotices" class="btn btn-primary"><i class="fa fa-trash-o icon-embed-btn"></i><?=gettext("Mark All as Read")?></button>
<?php endif;?>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function() {
	    $('#clearallnotices').click(function() {
			ajaxRequest = $.ajax({
				url: "/index.php",
				type: "post",
				data: { closenotice: "all"},
				success: function() {
					window.location = window.location.href;
				},
				failure: function() {
					alert("Error clearing notices!");
				}
			});
		});
	});

    // Handle submenu toggling
    $('.nav-link.has-submenu').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $submenu = $(this).siblings('.submenu');
        var $icon = $(this).find('.submenu-icon');
        
        // Close other submenus
        $('.submenu').not($submenu).slideUp(300);
        $('.submenu-icon').not($icon).removeClass('rotate-180');
        
        // Toggle current submenu
        $submenu.slideToggle(300);
        $icon.toggleClass('rotate-180');
        
        // Toggle active state
        $('.nav-link.has-submenu').not(this).removeClass('active');
        $(this).toggleClass('active');
    });

    // Set active state based on current URL
    var currentPath = window.location.pathname;
    $('.nav-link').each(function() {
        var linkPath = $(this).attr('href');
        if (linkPath === currentPath) {
            $(this).addClass('active');
            // If it's a submenu item, show parent menu and add active state
            var $parentMenu = $(this).closest('.submenu');
            if ($parentMenu.length) {
                $parentMenu.show();
                $parentMenu.siblings('.nav-link.has-submenu')
                    .addClass('active')
                    .find('.submenu-icon')
                    .addClass('rotate-180');
            }
        }
	});
//]]>
</script>

<?php
endif; // ($display_notices)

// Get the flash Messages
get_flash_message();
