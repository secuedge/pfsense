<?php
/*
 * system_information.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Scott Dale
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');
require_once('system.inc');
include_once("includes/functions.inc.php");
require_once("includes/system_functions.inc.php");

$sysinfo_items = array(
	'name' => gettext('Name'),
	'user' => gettext('User'),
	'version' => gettext('Version'),
	'cpu_type' => gettext('CPU Type'),
	'uptime' => gettext('Uptime'),
	'current_datetime' => gettext('Current Date/Time'),
	'dns_servers' => gettext('DNS Server(s)'),
	'last_config_change' => gettext('Last Config Change'),
	'temperature' => gettext('Temperature'),
	'cpu_usage' => gettext('CPU Usage'),
	'memory_usage' => gettext('Memory Usage')
	);

// Declared here so that JavaScript can access it
$updtext = sprintf(gettext("Obtaining update status %s"), "<i class='fa fa-cog fa-spin'></i>");
$state_tt = gettext("Adaptive state handling is enabled, state timeouts are reduced by ");

if ($_REQUEST['getupdatestatus']) {
	require_once("pkg-utils.inc");

	$cache_file = g_get('version_cache_file');

	if (isset($config['system']['firmware']['disablecheck'])) {
		exit;
	}

	/* If $_REQUEST['getupdatestatus'] == 2, force update */
	$system_version = get_system_pkg_version(false,
		($_REQUEST['getupdatestatus'] == 1),
		false, /* get upgrades from other repos */
		true /* see https://redmine.pfsense.org/issues/15055 */
	);

	if ($system_version === false) {
		print(gettext("<i>Unable to check for updates</i>"));
		exit;
	}

	if (!is_array($system_version) ||
	    !isset($system_version['version']) ||
	    !isset($system_version['installed_version'])) {
		print(gettext("<i>Error in version information</i>"));
		exit;
	}

	switch ($system_version['pkg_version_compare']) {
	case '<':
?>
		<div>
			<?=gettext("Version ")?>
			<span class="text-success"><?=$system_version['version']?></span> <?=gettext("is available.")?>
			<a class="fa fa-cloud-download fa-lg" href="/pkg_mgr_install.php?id=firmware"></a>
		</div>
<?php
		break;
	case '=':
		printf('<span class="text-success">%s</span>' . "\n",
		    gettext("The system is on the latest version."));
		break;
	case '>':
		printf("%s\n", gettext(
		    "The system is on a later version than official release."));
		break;
	default:
		printf("<i>%s</i>\n", gettext(
		    "Error comparing installed with latest version available"));
		break;
	}

	if (file_exists($cache_file)):
?>
	<div>
		<?printf("%s %s", gettext("Version information updated at"),
		    date("D M j G:i:s T Y", filemtime($cache_file)));?>
		    &nbsp;
		    <a id="updver" href="#" class="fa fa-refresh"></a>
	</div>
<?php
	endif;

	exit;
} elseif ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();

	foreach ($sysinfo_items as $sysinfo_item_key => $sysinfo_item_name) {
		array_push($validNames, $sysinfo_item_key);
	}

	// Force exclude removed items
	$removed_items = array('system', 'bios', 'hwcrypto', 'pti', 'mds', 'state_table_size', 'mbuf_usage', 'load_average');
	
	if (is_array($_POST['show'])) {
		// Remove any removed items from the show array
		$_POST['show'] = array_diff($_POST['show'], $removed_items);
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_merge($validNames, $removed_items));
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved System Information Widget Filter via Dashboard."));
	header("Location: /index.php");
}

$hwcrypto = get_cpu_crypto_support();

$skipsysinfoitems = explode(",", $user_settings['widgets'][$widgetkey]['filter']);

// Force exclude removed items
$removed_items = array('system', 'bios', 'hwcrypto', 'pti', 'mds', 'state_table_size', 'mbuf_usage', 'load_average');
foreach ($removed_items as $item) {
    if (!in_array($item, $skipsysinfoitems)) {
        $skipsysinfoitems[] = $item;
    }
}

$rows_displayed = false;
// use the preference of the first thermal sensor widget, if it's available (false == empty)
$temp_use_f = (isset($user_settings['widgets']['thermal_sensors-0']) && !empty($user_settings['widgets']['thermal_sensors-0']['thermal_sensors_widget_show_fahrenheit']));

$system_info = system_get_info();

function compact_uptime($uptime) {
    // Convert any case (e.g., Hour, hour, Hours) to lower for matching
    $uptime = strtolower($uptime);
    $map = [
        '/(\d+)\s*day[s]?/' => 'd',
        '/(\d+)\s*hour[s]?/' => 'h',
        '/(\d+)\s*min(ute)?s?/' => 'm',
        '/(\d+)\s*sec(ond)?s?/' => 's',
    ];
    $result = [];
    foreach ($map as $regex => $abbr) {
        if (preg_match($regex, $uptime, $matches)) {
            $result[] = $matches[1] . $abbr;
        }
    }
    return $result ? implode(' ', $result) : $uptime;
}
?>
<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css">
<style>
.sysinfo-compact-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(25,118,210,0.07);
    padding: 1.5rem 2rem 1.2rem 2rem;
    margin-bottom: 1.5rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

/* Typography updates for selected components */
.sysinfo-table td.label,
.sysinfo-table td.value {
    font-family: Inter, sans-serif;
    font-size: 13px;
    line-height: 20px;
    font-style: normal;
}

.sysinfo-table td.label {
    color: #888;
    font-weight: 500;
    width: 38%;
    text-align: right;
    padding-right: 1.2rem;
}

.sysinfo-table td.value {
    font-weight: 500;
    text-align: left;
}

/* Keep other styles unchanged */
.sysinfo-kpis {
    display: flex;
    gap: 2.5rem;
    justify-content: space-between;
    margin-bottom: 1.2rem;
}
.sysinfo-kpi {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 70px;
}
.sysinfo-kpi i {
    font-size: 1.3rem;
    color: #1976D2;
    margin-bottom: 0.2rem;
}
.sysinfo-kpi .kpi-label {
    font-size: 0.85rem;
    color: #888;
    margin-bottom: 0.1rem;
}
.sysinfo-kpi .kpi-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #222;
}
.sysinfo-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}
.sysinfo-table td {
    padding: 0.35rem 0.5rem;
    font-size: 1rem;
    color: #333;
}
@media (max-width: 700px) {
    .sysinfo-compact-card { padding: 1rem 0.5rem; }
    .sysinfo-kpis { gap: 1.2rem; }
    .sysinfo-table td.label { padding-right: 0.5rem; }
}
</style>
<div class="sysinfo-compact-card">
    <div class="sysinfo-kpis">
        <div class="sysinfo-kpi">
            <i class="fa fa-clock"></i>
            <span class="kpi-label">Uptime</span>
            <span class="kpi-value"><?= htmlspecialchars(compact_uptime($system_info['uptime'])) ?></span>
        </div>
        <div class="sysinfo-kpi">
            <i class="fa fa-microchip"></i>
            <span class="kpi-label">CPU</span>
            <span class="kpi-value"><?= (int)$system_info['cpu_load'] ?>%</span>
        </div>
        <div class="sysinfo-kpi">
            <i class="fa fa-memory"></i>
            <span class="kpi-label">Memory</span>
            <span class="kpi-value"><?= (int)$system_info['memory_usage'] ?>%</span>
        </div>
        <div class="sysinfo-kpi">
            <i class="fa fa-thermometer-half"></i>
            <span class="kpi-label">Temp</span>
            <span class="kpi-value"><?= htmlspecialchars($system_info['temperature']) ?></span>
        </div>
    </div>
    <table class="sysinfo-table">
        <tr>
            <td class="label">Hostname</td>
            <td class="value"><?= htmlspecialchars($system_info['name']) ?></td>
        </tr>
        <tr>
            <td class="label">Version</td>
            <td class="value"><?= htmlspecialchars($system_info['version']) ?></td>
        </tr>
        <tr>
            <td class="label">CPU Model</td>
            <td class="value"><?= htmlspecialchars($system_info['cpu_model']) ?></td>
        </tr>
        <tr>
            <td class="label">Total Memory</td>
            <td class="value"><?= format_bytes($system_info['memory_total']) ?></td>
        </tr>
        <tr>
            <td class="label">BIOS Version</td>
            <td class="value"><?= htmlspecialchars($system_info['bios_version']) ?></td>
        </tr>
        <tr>
            <td class="label">DNS Servers</td>
            <td class="value"><?= htmlspecialchars($system_info['dns_servers']) ?></td>
        </tr>
        <tr>
            <td class="label">User</td>
            <td class="value"><?= htmlspecialchars($system_info['user']) ?></td>
        </tr>
        <tr>
            <td class="label">Last Update</td>
            <td class="value"><?= htmlspecialchars($system_info['last_update']) ?></td>
        </tr>
    </table>
</div>
