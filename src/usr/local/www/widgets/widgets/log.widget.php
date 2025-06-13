<?php
/*
 * log.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Scott Dale
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

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("syslog.inc");

if ($_REQUEST['widgetkey'] && !$_REQUEST['ajax']) {
	set_customwidgettitle($user_settings);

	if (is_numeric($_POST['filterlogentries'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentries'] = $_POST['filterlogentries'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentries']);
	}

	$acts = array();
	if ($_POST['actpass']) {
		$acts[] = "Pass";
	}
	if ($_POST['actblock']) {
		$acts[] = "Block";
	}
	if ($_POST['actreject']) {
		$acts[] = "Reject";
	}

	if (!empty($acts)) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesacts'] = implode(" ", $acts);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesacts']);
	}
	unset($acts);

	if (($_POST['filterlogentriesinterfaces']) and ($_POST['filterlogentriesinterfaces'] != "All")) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterfaces'] = trim($_POST['filterlogentriesinterfaces']);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterfaces']);
	}

	if (is_numeric($_POST['filterlogentriesinterval'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterval'] = $_POST['filterlogentriesinterval'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterval']);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Filter Log Entries via Dashboard."));
	Header("Location: /");
	exit(0);
}

// When this widget is included in the dashboard, $widgetkey is already defined before the widget is included.
// When the ajax call is made to refresh the firewall log table, 'widgetkey' comes in $_REQUEST.
if ($_REQUEST['widgetkey']) {
	$widgetkey = $_REQUEST['widgetkey'];
}

$iface_descr_arr = get_configured_interface_with_descr();

$nentries = isset($user_settings['widgets'][$widgetkey]['filterlogentries']) ? $user_settings['widgets'][$widgetkey]['filterlogentries'] : 8;

//set variables for log
$nentriesacts		= isset($user_settings['widgets'][$widgetkey]['filterlogentriesacts']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesacts'] : 'All';
$nentriesinterfaces = isset($user_settings['widgets'][$widgetkey]['filterlogentriesinterfaces']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array(
	"act" => $nentriesacts,
	"interface" => isset($iface_descr_arr[$nentriesinterfaces]) ? $iface_descr_arr[$nentriesinterfaces] : $nentriesinterfaces
);

$nentriesinterval = isset($user_settings['widgets'][$widgetkey]['filterlogentriesinterval']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesinterval'] : 60;

$filter_logfile = "{$g['varlog_path']}/filter.log";

$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);

$widgetkey_nodash = str_replace("-", "", $widgetkey);

if (!$_REQUEST['ajax']) {
?>
<script type="text/javascript">
//<![CDATA[
	var logWidgetLastRefresh<?=htmlspecialchars($widgetkey_nodash)?> = <?=time()?>;
//]]>
</script>

<style>
/* Container and base styles */
.table-responsive {
    position: relative;
    padding: 1px;
    border-radius: 16px;
    background: linear-gradient(120deg, #2196f3, #1976D2);
}

.table-responsive::before {
    content: '';
    position: absolute;
    inset: 0;
    padding: 1px;
    border-radius: 16px;
    background: linear-gradient(120deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
}

/* Table Base */
.logs-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    line-height: 20px;
    overflow: hidden;
    position: relative;
}

/* Header Styles */
.logs-table thead th {
    color: #1a237e;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-align: left;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.9);
    border-bottom: 1px solid rgba(25, 118, 210, 0.1);
    position: relative;
}

.logs-table thead th::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 1.5rem;
    right: 1.5rem;
    height: 2px;
    background: linear-gradient(90deg, #2196f3, transparent);
}

/* Row Styles */
.logs-table tbody tr {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.logs-table tbody tr:hover {
    background: rgba(33, 150, 243, 0.05);
    transform: translateY(-2px) scale(1.002);
    box-shadow: 0 4px 20px rgba(33, 150, 243, 0.15);
    z-index: 1;
}

.logs-table td {
    padding: 1.5rem;
    font-size: 13px;
    line-height: 20px;
    color: #2c3e50;
    vertical-align: middle;
    font-weight: 500;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(25, 118, 210, 0.06);
}

/* Status Icons */
.log-action {
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 12px;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    transition: transform 0.3s ease;
}

.logs-table tr:hover .log-action {
    transform: scale(1.1);
}

.log-action.pass {
    background: #E8F5E9;
    color: #00c853;
    text-shadow: 0 0 10px rgba(0, 200, 83, 0.3);
}

.log-action.block {
    background: #FFEBEE;
    color: #ff1744;
    text-shadow: 0 0 10px rgba(255, 23, 68, 0.3);
}

.log-action.reject {
    background: #FFF3E0;
    color: #FF9100;
    text-shadow: 0 0 10px rgba(255, 145, 0, 0.3);
}

/* Widget Title */
.widget-heading {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    color: #1a237e;
    font-weight: 600;
    font-size: 14px;
    letter-spacing: 0.5px;
}

.widget-heading i {
    font-size: 16px;
    color: #1976D2;
    background: rgba(25, 118, 210, 0.1);
    padding: 8px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.widget-heading:hover i {
    transform: rotate(15deg);
    background: rgba(25, 118, 210, 0.15);
}

/* Animation Keyframes */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Apply Animations */
.logs-table tbody tr {
    animation: fadeIn 0.5s ease-out forwards;
    animation-delay: calc(var(--row-index, 0) * 0.1s);
    opacity: 0;
}

/* Interface Name */
.interface-name {
    color: #1976D2;
    font-weight: 500;
    background: rgba(25, 118, 210, 0.1);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    transition: all 0.3s ease;
}

.logs-table tr:hover .interface-name {
    background: rgba(25, 118, 210, 0.15);
}

/* IP Addresses */
.ip-address {
    color: #2c3e50;
    text-decoration: none;
    transition: all 0.3s ease;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}

.ip-address:hover {
    color: #1976D2;
    background: rgba(25, 118, 210, 0.1);
    text-decoration: none;
}

/* Empty State */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
    color: #546e7a;
    background: linear-gradient(to bottom right, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.9));
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .logs-table td, 
    .logs-table th {
        padding: 1rem;
    }
}
</style>

<div class="table-responsive">
    <table class="logs-table">
        <thead>
            <tr>
                <th><?=gettext("Act")?></th>
                <th><?=gettext("Time")?></th>
                <th><?=gettext("IF")?></th>
                <th><?=gettext("Source")?></th>
                <th><?=gettext("Destination")?></th>
            </tr>
        </thead>
        <tbody>
<?php
    foreach ($filterlog as $filterent):
        if ($filterent['version'] == '6') {
            $srcIP = "[" . htmlspecialchars($filterent['srcip']) . "]";
            $dstIP = "[" . htmlspecialchars($filterent['dstip']) . "]";
        } else {
            $srcIP = htmlspecialchars($filterent['srcip']);
            $dstIP = htmlspecialchars($filterent['dstip']);
        }

        if ($filterent['act'] == "block") {
            $iconfn = "times text-danger";
        } else if ($filterent['act'] == "reject") {
            $iconfn = "hand-stop-o text-warning";
        } else if ($filterent['act'] == "match") {
            $iconfn = "filter";
        } else {
            $iconfn = "check text-success";
        }

        $rule = find_rule_by_number($filterent['rulenum'], $filterent['tracker'], $filterent['act']);
?>
        <tr>
            <td>
                <?php if ($filterent['act'] == "block"): ?>
                    <span class="log-action block">
                        <i class="fa fa-times"></i>
                    </span>
                <?php elseif ($filterent['act'] == "reject"): ?>
                    <span class="log-action reject">
                        <i class="fa fa-hand-stop-o"></i>
                    </span>
                <?php elseif ($filterent['act'] == "match"): ?>
                    <span class="log-action match">
                        <i class="fa fa-filter"></i>
                    </span>
                <?php else: ?>
                    <span class="log-action pass">
                        <i class="fa fa-check"></i>
                    </span>
                <?php endif; ?>
            </td>
            <td><?=substr(htmlspecialchars($filterent['time']),0,-3)?></td>
            <td class="interface-name"><?=htmlspecialchars($filterent['interface'])?></td>
            <td>
                <a href="diag_dns.php?host=<?=$filterent['srcip']?>" 
                   class="ip-address" 
                   title="<?=gettext("Reverse Resolve with DNS")?>">
                    <?=$srcIP?>
                </a>
            </td>
            <td>
                <a href="diag_dns.php?host=<?=$filterent['dstip']?>" 
                   class="ip-address" 
                   title="<?=gettext("Reverse Resolve with DNS")?>">
                    <?=$dstIP?><?php if ($filterent['dstport']) echo ':' . htmlspecialchars($filterent['dstport']); ?>
                </a>
            </td>
        </tr>
<?php
    endforeach;

    if (count($filterlog) == 0):
?>
        <tr>
            <td colspan="5" class="empty-state">
                <i class="fas fa-clipboard fa-2x mb-3" style="color: #B0BEC5; display: block; margin-bottom: 0.5rem;"></i>
                <?=gettext("No logs to display")?>
            </td>
        </tr>
<?php
    endif;
?>
        </tbody>
    </table>
</div>

<?php } ?>

<script type="text/javascript">
//<![CDATA[

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function logs_callback(s) {
		$(<?=json_encode('#widget-' . $widgetkey . '_panel-body')?>).html(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>,
		lastsawtime: logWidgetLastRefresh<?=htmlspecialchars($widgetkey_nodash)?>
	 };

	// Create an object defining the widget refresh AJAX call
	var logsObject = new Object();
	logsObject.name = "Firewall Logs";
	logsObject.url = "/widgets/widgets/log.widget.php";
	logsObject.callback = logs_callback;
	logsObject.parms = postdata;
	logsObject.freq = <?=$nentriesinterval?>/5;

	// Register the AJAX object
	register_ajax(logsObject);

	// ---------------------------------------------------------------------------------------------------
});
//]]>
</script>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
<div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<?php
$pconfig['nentries'] = isset($user_settings['widgets'][$widgetkey]['filterlogentries']) ? $user_settings['widgets'][$widgetkey]['filterlogentries'] : '';
$pconfig['nentriesinterval'] = isset($user_settings['widgets'][$widgetkey]['filterlogentriesinterval']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesinterval'] : '';
?>
	<form action="/widgets/widgets/log.widget.php" method="post"
		class="form-horizontal">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<?=gen_customwidgettitle_div($widgetconfig['title']); ?>

		<div class="form-group">
			<label for="filterlogentries" class="col-sm-4 control-label"><?=gettext('Number of entries')?></label>
			<div class="col-sm-6">
				<input type="number" name="filterlogentries" id="filterlogentries" value="<?=$pconfig['nentries']?>" placeholder="5"
					min="1" max="50" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('Filter actions')?></label>
			<div class="col-sm-6 checkbox">
			<?php $include_acts = explode(" ", strtolower($nentriesacts)); ?>
			<label><input name="actpass" type="checkbox" value="Pass"
				<?=(in_array('pass', $include_acts) ? 'checked':'')?> />
				<?=gettext('Pass')?>
			</label>
			<label><input name="actblock" type="checkbox" value="Block"
				<?=(in_array('block', $include_acts) ? 'checked':'')?> />
				<?=gettext('Block')?>
			</label>
			<label><input name="actreject" type="checkbox" value="Reject"
				<?=(in_array('reject', $include_acts) ? 'checked':'')?> />
				<?=gettext('Reject')?>
			</label>
			</div>
		</div>

		<div class="form-group">
			<label for="filterlogentriesinterfaces" class="col-sm-4 control-label">
				<?=gettext('Filter interface')?>
			</label>
			<div class="col-sm-6 checkbox">
				<select name="filterlogentriesinterfaces" id="filterlogentriesinterfaces" class="form-control">
			<?php foreach (array("All" => "ALL") + $iface_descr_arr as $iface => $ifacename):?>
				<option value="<?=$iface?>"
						<?=($nentriesinterfaces==$iface?'selected':'')?>><?=htmlspecialchars($ifacename)?></option>
			<?php endforeach;?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="filterlogentriesinterval" class="col-sm-4 control-label"><?=gettext('Update interval')?></label>
			<div class="col-sm-4">
				<input type="number" name="filterlogentriesinterval" id="filterlogentriesinterval" value="<?=$pconfig['nentriesinterval']?>" placeholder="60"
					min="1" class="form-control" />
			</div>
			<?=gettext('Seconds');?>
		</div>

		<div class="form-group">
			<div class="col-sm-offset-4 col-sm-6">
				<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			</div>
		</div>
	</form>

<script type="text/javascript">
//<![CDATA[
if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
			http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		};
		http_request.open('GET', url, true);
		http_request.send(null);
	};
}

function outputrule(req) {
	alert(req.content);
}
//]]>
</script>
