<?php
/*
 * openvpn.widget.php
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

require_once("guiconfig.inc");
require_once("openvpn.inc");

// Output the widget panel from this function so that it can be called from the AJAX handler as well as
// when first rendering the page
if (!function_exists('printPanel')) {
	function printPanel($widgetkey) {
		global $user_settings;

		$servers = openvpn_get_active_servers();
		$sk_servers = openvpn_get_active_servers("p2p");
		$clients = openvpn_get_active_clients();
		$skipovpns = explode(",", $user_settings['widgets'][$widgetkey]['filter']);

		$opstring = "";
		$got_ovpn_server = false;

		foreach ($servers as $server):
			if (in_array($server['vpnid'], $skipovpns)) {
				continue;
			}

			$got_ovpn_server = true;

			$opstring .= "<div class=\"openvpn-widget\">";
			$opstring .= "<div class=\"openvpn-widget-header\">";
			$opstring .= "<h3 class=\"openvpn-widget-title\">" . htmlspecialchars($server['name']);

			if (!empty($server['conns']) && $server['conns'][0]['common_name'] != '[error]') {
				$opstring .= ' <span style="color: #718096; font-size: 0.875rem;">(' . sizeof($server['conns']) . ')</span>';
			} else {
				$opstring .= ' <span style="color: #718096; font-size: 0.875rem;">(0)</span>';
			}
			$opstring .= "</h3></div>";

			if (empty($server['conns']) || (isset($server['conns'][0]['common_name']) && $server['conns'][0]['common_name'] == '[error]')) {
				$opstring .= "<div class=\"openvpn-empty\">" . gettext("No client connections") . "</div>";
			} else {
				$opstring .= "<div class=\"table-responsive\">";
				$opstring .= "<table class=\"openvpn-table\" data-sortable>";
				$opstring .= "<thead>";
				$opstring .= "<tr>";
				$opstring .= "<th>" . gettext('Name/Time') . "</th>";
				$opstring .= "<th>" . gettext('Real/Virtual IP') . "</th>";
				$opstring .= "<th></th>";
				$opstring .= "</tr>";
				$opstring .= "</thead>";
				$opstring .= "<tbody>";

				$rowIndex = 0;
				foreach ($server['conns'] as $conn):
					$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
					$rowIndex++;

					$opstring .= "<tr name=\"r:" . $server['mgmt'] . ":" . $conn['remote_host'] . "\" class=\"" . $evenRowClass . "\">";
					$opstring .= "<td>";
					$opstring .= "<div class=\"openvpn-connection\">";
					$opstring .= "<span>" . $conn['common_name'] . "</span>";
					$opstring .= "<span class=\"openvpn-connection-time\">" . $conn['connect_time'] . "</span>";
					$opstring .= "</div>";
					$opstring .= "</td>";
					$opstring .= "<td>";
					$opstring .= "<div class=\"openvpn-connection\">";
					$opstring .= "<span class=\"openvpn-ip\">" . $conn['remote_host'] . "</span>";
					if (!empty($conn['virtual_addr'])) {
						$opstring .= "<span class=\"openvpn-ip\">" . $conn['virtual_addr'] . "</span>";
					}
					if (!empty($conn['virtual_addr6'])) {
						$opstring .= "<span class=\"openvpn-ip\">" . $conn['virtual_addr6'] . "</span>";
					}
					$opstring .= "</div>";
					$opstring .= "</td>";
					$opstring .= "<td>";
					$opstring .= "<div class=\"openvpn-action\">";
					$opstring .= "<i class=\"fa fa-times\" ";
					$opstring .= "onclick=\"killClient('" . $server['mgmt'] . "', '" . $conn['remote_host'] . "', '');\" ";
					$opstring .= "name=\"i:" . $server['mgmt'] . ":" . $conn['remote_host'] . "\" ";
					$opstring .= "title=\"" . sprintf(gettext('Kill client connection from %s'), $conn['remote_host']) . "\">";
					$opstring .= "</i>";
					$opstring .= "<i class=\"fa fa-times-circle\" ";
					$opstring .= "onclick=\"killClient('" . $server['mgmt'] . "', '" . $conn['remote_host'] . "', '" . $conn['client_id'] . "');\" ";
					$opstring .= "name=\"i:" . $server['mgmt'] . ":" . $conn['remote_host'] . "\" ";
					$opstring .= "title=\"" . sprintf(gettext('Halt client connection from %s'), $conn['remote_host']) . "\">";
					$opstring .= "</i>";
					$opstring .= "</div>";
					$opstring .= "</td>";
					$opstring .= "</tr>";
				endforeach;

				$opstring .= "</tbody>";
				$opstring .= "</table>";
				$opstring .= "</div>";
			}
			$opstring .= "</div>";
		endforeach;

		print($opstring);

		$got_sk_server = false;

		if (!empty($sk_servers)):
			foreach ($sk_servers as $sk_server):
				if (!in_array($sk_server['vpnid'], $skipovpns)) {
					$got_sk_server = true;
					break;
				}
			endforeach;
		endif;

		if ($got_sk_server):
			$opstring = "";
			$opstring .= "<div class=\"openvpn-widget\">";
			$opstring .= "<div class=\"openvpn-widget-header\">";
			$opstring .= "<h3 class=\"openvpn-widget-title\">" . gettext("Peer to Peer Server Instance Statistics") . "</h3>";
			$opstring .= "</div>";
			$opstring .= "<div class=\"table-responsive\">";
			$opstring .= "<table class=\"openvpn-table\" data-sortable>";
			$opstring .= "<thead>";
			$opstring .= "<tr>";
			$opstring .= "<th>" . gettext('Name/Time') . "</th>";
			$opstring .= "<th>" . gettext('Remote/Virtual IP') . "</th>";
			$opstring .= "<th></th>";
			$opstring .= "</tr>";
			$opstring .= "</thead>";
			$opstring .= "<tbody>";

			foreach ($sk_servers as $sk_server):
				if (in_array($sk_server['vpnid'], $skipovpns)) {
					continue;
				}

				$opstring .= "<tr name=\"r:" . $sk_server['port'] . ":" . $sk_server['remote_host'] . "\">";
				$opstring .= "<td>";
				$opstring .= "<div class=\"openvpn-connection\">";
				$opstring .= "<span>" . $sk_server['name'] . "</span>";
				$opstring .= "<span class=\"openvpn-connection-time\">" . $sk_server['connect_time'] . "</span>";
				$opstring .= "</div>";
				$opstring .= "</td>";
				$opstring .= "<td>";
				$opstring .= "<div class=\"openvpn-connection\">";
				$opstring .= "<span class=\"openvpn-ip\">" . $sk_server['remote_host'] . "</span>";
				if (!empty($sk_server['virtual_addr'])) {
					$opstring .= "<span class=\"openvpn-ip\">" . $sk_server['virtual_addr'] . "</span>";
				}
				if (!empty($sk_server['virtual_addr6'])) {
					$opstring .= "<span class=\"openvpn-ip\">" . $sk_server['virtual_addr6'] . "</span>";
				}
				$opstring .= "</div>";
				$opstring .= "</td>";
				$opstring .= "<td>";
				$opstring .= "<div class=\"openvpn-status\">";
				if (strtolower($sk_server['state']) == "connected") {
					$opstring .= "<i class=\"fa fa-arrow-up\"></i>";
				} else {
					$opstring .= "<i class=\"fa fa-arrow-down\"></i>";
				}
				$opstring .= "</div>";
				$opstring .= "</td>";
				$opstring .= "</tr>";
			endforeach;

			$opstring .= "</tbody>";
			$opstring .= "</table>";
			$opstring .= "</div>";
			$opstring .= "</div>";

			print($opstring);
		endif;

		$got_ovpn_client = false;

		if (!empty($clients)):
			foreach ($clients as $client):
				if (!in_array($client['vpnid'], $skipovpns)) {
					$got_ovpn_client = true;
					break;
				}
			endforeach;
		endif;

		if ($got_ovpn_client):
			$opstring = "";
			$opstring .= "<div class=\"openvpn-widget\">";
			$opstring .= "<div class=\"openvpn-widget-header\">";
			$opstring .= "<h3 class=\"openvpn-widget-title\">" . gettext("Client Instance Statistics") . "</h3>";
			$opstring .= "</div>";
			$opstring .= "<div class=\"table-responsive\">";
			$opstring .= "<table class=\"openvpn-table\" data-sortable>";
			$opstring .= "<thead>";
			$opstring .= "<tr>";
			$opstring .= "<th>" . gettext('Name/Time') . "</th>";
			$opstring .= "<th>" . gettext('Remote/Virtual IP') . "</th>";
			$opstring .= "<th></th>";
			$opstring .= "</tr>";
			$opstring .= "</thead>";
			$opstring .= "<tbody>";

			foreach ($clients as $client):
				if (in_array($client['vpnid'], $skipovpns)) {
					continue;
				}

				$opstring .= "<tr name=\"r:" . $client['port'] . ":" . $client['remote_host'] . "\">";
				$opstring .= "<td>";
				$opstring .= "<div class=\"openvpn-connection\">";
				$opstring .= "<span>" . $client['name'] . "</span>";
				$opstring .= "<span class=\"openvpn-connection-time\">" . $client['connect_time'] . "</span>";
				$opstring .= "</div>";
				$opstring .= "</td>";
				$opstring .= "<td>";
				$opstring .= "<div class=\"openvpn-connection\">";
				$opstring .= "<span class=\"openvpn-ip\">" . $client['remote_host'] . "</span>";
				if (!empty($client['virtual_addr'])) {
					$opstring .= "<span class=\"openvpn-ip\">" . $client['virtual_addr'] . "</span>";
				}
				if (!empty($client['virtual_addr6'])) {
					$opstring .= "<span class=\"openvpn-ip\">" . $client['virtual_addr6'] . "</span>";
				}
				$opstring .= "</div>";
				$opstring .= "</td>";
				$opstring .= "<td>";
				$opstring .= "<div class=\"openvpn-status\">";
				if (strtolower($client['state']) == "connected") {
					$opstring .= "<i class=\"fa fa-arrow-up\"></i>";
				} else {
					$opstring .= "<i class=\"fa fa-arrow-down\"></i>";
				}
				$opstring .= "</div>";
				$opstring .= "</td>";
				$opstring .= "</tr>";
			endforeach;

			$opstring .= "</tbody>";
			$opstring .= "</table>";
			$opstring .= "</div>";
			$opstring .= "</div>";

			print($opstring);
		endif;

		if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
			$none_to_display_text = gettext("No OpenVPN instances defined");
		} else if (!$got_ovpn_server && !$got_sk_server && !$got_ovpn_client) {
			$none_to_display_text = gettext("All OpenVPN instances are hidden");
		} else {
			$none_to_display_text = "";
		}

		if (strlen($none_to_display_text) > 0) {
			print('<div class="openvpn-no-instances">' . $none_to_display_text . '</div>');
		}
	}
}

/* Handle AJAX */
if ($_POST['action']) {
	if ($_POST['action'] == "kill") {
		$port = $_POST['port'];
		$remipp = $_POST['remipp'];
		$client_id  = $_POST['client_id'];
		if (!empty($port) and !empty($remipp)) {
			$retval = openvpn_kill_client($port, $remipp, $client_id);
			echo htmlentities("|{$port}|{$remipp}|{$retval}|");
		} else {
			echo gettext("invalid input");
		}
		exit;
	}
}

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {
	printPanel($_REQUEST['widgetkey']);
	exit;
} else if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();
	$servers = openvpn_get_active_servers();
	$sk_servers = openvpn_get_active_servers("p2p");
	$clients = openvpn_get_active_clients();

	foreach ($servers as $server) {
		array_push($validNames, $server['vpnid']);
	}

	foreach ($sk_servers as $sk_server) {
		array_push($validNames, $sk_server['vpnid']);
	}

	foreach ($clients as $client) {
		array_push($validNames, $client['vpnid']);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved OpenVPN Filter via Dashboard."));
	header("Location: /index.php");
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>

<style>
.openvpn-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    font-family: Inter, sans-serif;
    margin-bottom: 1.5rem;
}

.openvpn-widget:last-child {
    margin-bottom: 0;
}

.openvpn-widget-header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
}

.openvpn-widget-title {
    font-size: 1.125rem;
    font-weight: 500;
    color: #2d3748;
    margin: 0;
}

.openvpn-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.openvpn-table th {
    color: #4a5568;
    font-weight: 500;
    background: #f5f8fa;
    padding: 0.75rem;
    font-size: 13px;
    line-height: 20px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.openvpn-table td {
    padding: 1rem 0.75rem;
    font-size: 13px;
    line-height: 20px;
    color: #4a5568;
    border-bottom: 1px solid #e2e8f0;
}

.openvpn-table tr:last-child td {
    border-bottom: none;
}

.openvpn-table tr:hover {
    background-color: #f7fafc;
}

.openvpn-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.openvpn-status i {
    font-size: 14px;
}

.openvpn-status i.fa-arrow-up {
    color: #48BB78;
}

.openvpn-status i.fa-arrow-down {
    color: #E53E3E;
}

.openvpn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.openvpn-action i {
    font-size: 14px;
    cursor: pointer;
    transition: color 0.2s ease;
}

.openvpn-action i.fa-times {
    color: #718096;
}

.openvpn-action i.fa-times-circle {
    color: #E53E3E;
}

.openvpn-action i:hover {
    opacity: 0.8;
}

.openvpn-empty {
    text-align: center;
    padding: 2rem;
    color: #718096;
    font-style: italic;
}

.openvpn-connection {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.openvpn-connection-time {
    font-size: 12px;
    color: #718096;
}

.openvpn-ip {
    font-family: "Inter Mono", monospace;
    font-size: 12px;
    color: #4a5568;
}

.openvpn-no-instances {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 2rem;
    font-family: Inter, sans-serif;
    text-align: center;
    color: #718096;
    font-style: italic;
}
</style>

<div id="<?=htmlspecialchars($widgetkey)?>-openvpn-mainpanel" class="content">

<?php
	printPanel($widgetkey);
?>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/openvpn.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Name")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$servers = openvpn_get_active_servers();
				$sk_servers = openvpn_get_active_servers("p2p");
				$clients = openvpn_get_active_clients();
				$skipovpns = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
				foreach ($servers as $server):
?>
						<tr>
							<td><?=htmlspecialchars($server['name'])?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$server['vpnid']?>" type="checkbox" <?=(!in_array($server['vpnid'], $skipovpns) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
				foreach ($sk_servers as $sk_server):
?>
						<tr>
							<td><?=htmlspecialchars($sk_server['name'])?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$sk_server['vpnid']?>" type="checkbox" <?=(!in_array($sk_server['vpnid'], $skipovpns) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
				foreach ($clients as $client):
?>
						<tr>
							<td><?=htmlspecialchars($client['name'])?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$client['vpnid']?>" type="checkbox" <?=(!in_array($client['vpnid'], $skipovpns) ? 'checked':'')?>></td>
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

<script type="text/javascript">
//<![CDATA[
	function killClient(mport, remipp, client_id) {

		$.ajax(
			"widgets/widgets/openvpn.widget.php",
			{
				type: "post",
				data: {
					action:           "kill",
					port:             mport,
					remipp:           remipp,
					client_id:        client_id
				},
				complete: killComplete
			}
		);
	}

	function killComplete(req) {
		var values = req.responseText.split("|");
		if (values[3] != "0") {
	//		alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		$('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { $(row).fadeOut(1000); }
		);
	}

	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");

		// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function openvpn_callback(s) {
			$(<?=json_encode('#' . $widgetkey . '-openvpn-mainpanel')?>).html(s);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey: <?=json_encode($widgetkey)?>
		 };

		// Create an object defining the widget refresh AJAX call
		var openvpnObject = new Object();
		openvpnObject.name = "OpenVPN";
		openvpnObject.url = "/widgets/widgets/openvpn.widget.php";
		openvpnObject.callback = openvpn_callback;
		openvpnObject.parms = postdata;
		openvpnObject.freq = 4;

		// Register the AJAX object
		register_ajax(openvpnObject);

		// ---------------------------------------------------------------------------------------------------
	});
//]]>
</script>
