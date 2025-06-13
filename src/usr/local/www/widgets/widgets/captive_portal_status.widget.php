<?php
/*
 * captive_portal_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2007 Sam Wenham
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

if (($_GET['act'] == "del") && !empty($cpzone) && isset($cpzoneid)) {
	captiveportal_disconnect_client($_GET['id'], 6);
}
unset($cpzone);

flush();

if (!function_exists('clientcmp')) {
	function clientcmp($a, $b) {
		global $order;
		return strcmp($a[$order], $b[$order]);
	}
}

$cpdb_all = array();

foreach ($a_cp as $cpzone => $cp) {
	$cpdb = captiveportal_read_db();
	foreach ($cpdb as $cpent) {
		$cpent[10] = $cpzone;
		$cpent[11] = captiveportal_get_last_activity($cpent[2]);
		$cpdb_all[] = $cpent;
	}
}

?>
<style>
.captive-portal-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    font-family: Inter, sans-serif;
}

.captive-portal-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.captive-portal-table th {
    color: #4a5568;
    font-weight: 500;
    background: #f5f8fa;
    padding: 0.75rem;
    font-size: 13px;
    line-height: 20px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.captive-portal-table td {
    padding: 1rem 0.75rem;
    font-size: 13px;
    line-height: 20px;
    color: #4a5568;
    border-bottom: 1px solid #e2e8f0;
}

.captive-portal-table tr:last-child td {
    border-bottom: none;
}

.captive-portal-table tr:hover {
    background-color: #f7fafc;
}

.captive-portal-action {
    color: #e53e3e;
    transition: color 0.2s ease;
}

.captive-portal-action:hover {
    color: #c53030;
}

.captive-portal-empty {
    text-align: center;
    padding: 2rem;
    color: #718096;
    font-style: italic;
}
</style>

<div class="captive-portal-widget">
    <div class="table-responsive">
        <table class="captive-portal-table sortable-theme-bootstrap" data-sortable>
            <thead>
            <tr>
                <th><?=gettext("IP address");?></th>
                <th><?=gettext("MAC address");?></th>
                <th><?=gettext("Username");?></th>
                <th><?=gettext("Session start");?></th>
                <th><?=gettext("Last activity");?></th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($cpdb_all)): ?>
                <tr>
                    <td colspan="6" class="captive-portal-empty">
                        <?=gettext("No active captive portal sessions");?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($cpdb_all as $cpent): ?>
                <tr>
                    <td><?=$cpent[2];?></td>
                    <td><?=$cpent[3];?></td>
                    <td><?=$cpent[4];?></td>
                    <td><?=date("m/d/Y H:i:s", $cpent[0]);?></td>
                    <td>
                        <?php
                        if ($cpent[11] && ($cpent[11] > 0)):
                            echo date("m/d/Y H:i:s", $cpent[11]);
                        else:
                            echo "&nbsp;";
                        endif;
                        ?>
                    </td>
                    <td>
                        <a href="?order=<?=htmlspecialchars($_GET['order']);?>&amp;showact=<?=$showact;?>&amp;act=del&amp;zone=<?=$cpent[10];?>&amp;id=<?=$cpent[5];?>" class="captive-portal-action">
                            <i class="fa fa-trash" title="<?=gettext("delete");?>"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
