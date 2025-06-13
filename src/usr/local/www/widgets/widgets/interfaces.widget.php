<?php
/*
 * interfaces.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c)  2007 Scott Dale
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/interfaces.inc");

$ifdescrs = get_configured_interface_with_descr();
// Update once per minute by default, instead of every 10 seconds
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 * 6 : 60000;

if ($_POST['widgetkey'] && !$_REQUEST['ajax']) {
	set_customwidgettitle($user_settings);

	$validNames = array();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($validNames, $ifdescr);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Interfaces Filter via Dashboard."));
	header("Location: /index.php");
}

// When this widget is included in the dashboard, $widgetkey is already defined before the widget is included.
// When the ajax call is made to refresh the interfaces table, 'widgetkey' comes in $_REQUEST.
if ($_REQUEST['widgetkey']) {
	$widgetkey = $_REQUEST['widgetkey'];
}

function get_table_content($widgetkey) {
	global $user_settings, $ifdescrs;
	$output = "";
	
	$skipinterfaces = explode(",", $user_settings['widgets'][$widgetkey]['iffilter']);
	$interface_is_displayed = false;
	
	foreach ($ifdescrs as $ifdescr => $ifname) {
		if (in_array($ifdescr, $skipinterfaces)) {
			continue;
		}
		
		$interface_is_displayed = true;
		$ifinfo = get_interface_info($ifdescr);
		
		// Determine interface type icon
		if ($ifinfo['pppoelink'] || $ifinfo['pptplink'] || $ifinfo['l2tplink']) {
			$typeicon = 'hdd-o';
		} else if ($ifinfo['ppplink']) {
			$typeicon = 'signal';
		} else if (is_interface_wireless($ifdescr)) {
			$typeicon = 'wifi';
		} else {
			$typeicon = 'sitemap';
		}
		
		// Determine status icon and class
		$status_class = '';
		if ($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") {
			$icon = 'arrow-up';
			$status_class = 'up';
		} elseif ($ifinfo['status'] == "no carrier" || $ifinfo['status'] == "down") {
			$icon = 'arrow-down';
			$status_class = 'down';
		} else {
			$icon = 'question-circle';
			$status_class = 'unknown';
		}
		
		$output .= '<tr>';
		
		// Interface column
		$output .= '<td>';
		$output .= '<div class="interface-name">';
		$output .= '<i class="fa fa-' . $typeicon . ' mr-2"></i>';
		$output .= '<a href="/interfaces.php?if=' . $ifdescr . '">' . htmlspecialchars($ifname) . '</a>';
		$output .= '</div>';
		$output .= '<div class="interface-details">' . htmlspecialchars($ifinfo['macaddr']) . '</div>';
		$output .= '</td>';
		
		// Status column
		$output .= '<td>';
		$output .= '<div class="interface-status ' . $status_class . '">';
		$output .= '<i class="fa fa-' . $icon . '"></i>';
		$output .= '<span>' . htmlspecialchars($ifinfo['status']) . '</span>';
		$output .= '</div>';
		$output .= '</td>';
		
		// Traffic/Details column
		$output .= '<td>';
		if ($ifinfo['pppoelink'] == "up" || $ifinfo['pptplink'] == "up" || $ifinfo['l2tplink'] == "up" || $ifinfo['ppplink'] == "up") {
			$output .= '<div class="interface-details">' . sprintf(gettext("Uptime: %s"), htmlspecialchars($ifinfo['ppp_uptime'])) . '</div>';
		} elseif (isset($ifinfo['laggproto'])) {
			$output .= '<div class="interface-details">' . sprintf(gettext("LAGG Ports: %s"), htmlspecialchars(get_lagg_ports($ifinfo['laggport']))) . '</div>';
		} else {
			$output .= '<div class="interface-details">' . htmlspecialchars($ifinfo['media']) . '</div>';
		}
		
		if (!empty($ifinfo['ipaddr']) || !empty($ifinfo['ipaddrv6'])) {
			$output .= '<div class="interface-traffic">';
			if (!empty($ifinfo['ipaddr'])) {
				$output .= '<div class="traffic-in"><i class="fa fa-circle"></i><span class="traffic-value">' . htmlspecialchars($ifinfo['ipaddr']) . '</span></div>';
			}
			if (!empty($ifinfo['ipaddrv6'])) {
				$output .= '<div class="traffic-out"><i class="fa fa-circle"></i><span class="traffic-value">' . htmlspecialchars($ifinfo['ipaddrv6']) . '</span></div>';
			}
			$output .= '</div>';
		} else {
			$output .= '<div class="interface-details">n/a</div>';
		}
		$output .= '</td>';
		
		$output .= '</tr>';
	}
	
	if (!$interface_is_displayed) {
		$output .= '<tr><td colspan="3" class="text-center">' . gettext('All interfaces are hidden.') . '</td></tr>';
	}
	
	return $output;
}

?>

<div class="table-responsive">
	<style>
	.interfaces-widget-card {
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		padding: 1.5rem;
		font-family: Inter, sans-serif;
		font-size: 13px;
		line-height: 20px;
	}

	.interfaces-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
	}

	.interfaces-table th {
		text-align: left;
		padding: 0.75rem;
		border-bottom: 1px solid #e2e8f0;
		font-weight: 500;
		color: #4a5568;
		font-size: 13px;
		line-height: 20px;
		background: #f5f8fa;
	}

	.interfaces-table td {
		padding: 1rem 0.75rem;
		border-bottom: 1px solid #e2e8f0;
		vertical-align: top;
		font-size: 13px;
		line-height: 20px;
	}

	.interfaces-table tr:last-child td {
		border-bottom: none;
	}

	.interfaces-table tr:hover {
		background-color: #f7fafc;
	}

	.interface-name {
		font-weight: 500;
		color: #2d3748;
		display: flex;
		align-items: center;
		margin-bottom: 0.25rem;
		font-size: 13px;
		line-height: 20px;
	}

	.interface-name i {
		margin-right: 0.5rem;
		color: #4a5568;
	}

	.interface-name a {
		color: #2d3748;
		text-decoration: none;
		font-weight: 500;
	}

	.interface-name a:hover {
		color: #4299e1;
	}

	.interface-details {
		font-size: 13px;
		line-height: 20px;
		color: #718096;
	}

	.interface-status {
		display: flex;
		align-items: center;
		font-size: 13px;
		line-height: 20px;
		padding: 0.25rem 0.5rem;
		border-radius: 4px;
		width: fit-content;
		font-weight: 500;
	}

	.interface-status i {
		margin-right: 0.5rem;
	}

	.interface-status.up {
		background-color: #C6F6D5;
		color: #2F855A;
	}

	.interface-status.down {
		background-color: #FED7D7;
		color: #C53030;
	}

	.interface-status.unknown {
		background-color: #EDF2F7;
		color: #4A5568;
	}

	.interface-traffic {
		margin-top: 0.5rem;
	}

	.traffic-in, .traffic-out {
		display: flex;
		align-items: center;
		font-size: 13px;
		line-height: 20px;
		color: #4a5568;
		margin-bottom: 0.25rem;
	}

	.traffic-in i, .traffic-out i {
		font-size: 8px;
		margin-right: 0.5rem;
	}

	.traffic-in i {
		color: #48BB78;
	}

	.traffic-out i {
		color: #4299E1;
	}

	.traffic-value {
		font-family: "Inter Mono", monospace;
		font-size: 13px;
		line-height: 20px;
	}
	</style>
	<div id="interfaces-<?=$widgetkey?>" class="interfaces-widget-card">
		<table class="interfaces-table">
			<thead>
				<tr>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("Status")?></th>
					<th><?=gettext("Details")?></th>
				</tr>
			</thead>
			<tbody>
				<?=get_table_content($widgetkey)?>
			</tbody>
		</table>
	</div>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/interfaces.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Interface")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$skipinterfaces = explode(",", $user_settings['widgets'][$widgetkey]['iffilter']);
				$idx = 0;

				foreach ($ifdescrs as $ifdescr => $ifname):
?>
						<tr>
							<td><?=$ifname?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$ifdescr?>" type="checkbox" <?=(!in_array($ifdescr, $skipinterfaces) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<?php

/* for AJAX response, we only need the panels */
if ($_REQUEST['ajax']) {
	exit;
}
?>

<script type="text/javascript">
//<![CDATA[

	events.push(function(){

		/// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function interfaces_callback(s) {
			$(s).html(s);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey: "<?=$widgetkey?>"
		};

		// Create an object defining the widget refresh AJAX call
		var interfacesObject = new Object();
		interfacesObject.name = "Interfaces";
		interfacesObject.url = "/widgets/widgets/interfaces.widget.php";
		interfacesObject.callback = interfaces_callback;
		interfacesObject.parms = postdata;
		interfacesObject.freq = 60;

		// Register the AJAX object
		register_ajax(interfacesObject);

		// ---------------------------------------------------------------------------------------------------

		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");

	});
//]]>
</script>
