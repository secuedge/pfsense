<?php
/*
 * ipsec.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2004-2005 T. Lechat <dev@lechat.org> (BSD 2 clause)
 * Copyright (c) 2007 Jonathan Watt <jwatt@jwatt.org> (BSD 2 clause)
 * Copyright (c) 2007 Scott Dale (BSD 2 clause)
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

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");

// Should always be initialized
init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));

$ipsec_widget_tabs = array(
	'overview' => gettext('Overview'),
	'tunnel' => gettext('Tunnels'),
	'mobile' => gettext('Mobile')
);

if ($_POST['widgetkey']) {
	if (!is_array($user_settings['widgets'][$_POST['widgetkey']])) {
		$user_settings['widgets'][$_POST['widgetkey']] = array();
	}

	if (isset($_POST['default_tab']) && array_key_exists($_POST['default_tab'], $ipsec_widget_tabs)) {
		$user_settings['widgets'][$_POST['widgetkey']]['default_tab'] = $_POST['default_tab'];
		save_widget_settings($_SESSION['Username'], $user_settings['widgets'], gettext("Updated IPsec widget settings via dashboard."));
	}

	header("Location: /");
	exit(0);
}

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {

	if (ipsec_enabled() && get_service_status(array('name' => 'ipsec'))) {
		$cmap = ipsec_status();
		$mobile = ipsec_dump_mobile();
	} else {
		$cmap = array();
	}

	$mobileactive = 0;
	$mobileinactive = 0;
	if (is_array($mobile['pool'])) {
		foreach ($mobile['pool'] as $pool) {
			$mobileactive += $pool['online'];
			$mobileinactive += $pool['offline'];
		}
	}

	// Generate JSON formatted data for the widget to update from
	$data = new stdClass();
	$data->overview = "<tr>";
	if (!empty($cmap) && is_array($cmap['connected']['p1']) && is_array($cmap['connected']['p2'])) {
		$data->overview .= "<td>" . count($cmap['connected']['p1']) . " / ";
		$data->overview .= count($cmap['connected']['p1']) + count($cmap['disconnected']['p1']) . "</td>";
		$data->overview .= "<td>" . count($cmap['connected']['p2']) . " / ";
		$data->overview .= count($cmap['connected']['p2']) + count($cmap['disconnected']['p2']) . "</td>";
		$data->overview .= "<td>" . htmlspecialchars($mobileactive) . " / ";
		$data->overview .= htmlspecialchars($mobileactive + $mobileinactive) . "</td>";
	} else {
		$data->overview .= "<td></td><td></td><td></td>";
	}
	$data->overview .= "</tr>";

	$gateways_status = return_gateways_status(true);
	$data->tunnel = "";
	foreach ($cmap as $k => $tunnel) {
		if (in_array($k, array('connected', 'disconnected')) ||
		    (!array_key_exists('p1', $tunnel) ||
		    isset($tunnel['p1']['disabled'])) ||
		    isset($tunnel['p1']['mobile'])) {
			continue;
		}

		// convert_friendly_interface_to_friendly_descr($ph1ent['interface'])
		$p1src = ipsec_get_phase1_src($tunnel['p1'], $gateways_status);
		if (empty($p1src)) {
			$p1src = gettext("Unknown");
		} else {
			$p1src = str_replace(',', ', ', $p1src);
		}
		$p1dst = ipsec_get_phase1_dst($tunnel['p1']);
		$data->tunnel .= "<tr>";
		$data->tunnel .= "<td colspan=2>" . htmlspecialchars($p1src) . "</td>";
		$data->tunnel .= "<td colspan=2>" . htmlspecialchars($p1dst) . "</td>";
		$data->tunnel .= "<td colspan=2>" . htmlspecialchars($tunnel['p1']['descr']) . "</td>";
		$p1conid = ipsec_conid($tunnel['p1'], null);

		// This is an array, take value of last entry only
		if (is_array($tunnel['status'])) {
			$tstatus = array_pop($tunnel['status']);
		} else {
			$tstatus = array('state' => 'DISCONNECTED');
		}

		switch ($tstatus['state']) {
			case 'ESTABLISHED':
				$statusicon = 'arrow-up';
				$iconcolor = 'success';
				$icontitle = gettext('Connected');
				$buttonaction = 'disconnect';
				$buttontarget = 'ike';
				break;
			case 'CONNECTING':
				$statusicon = 'spinner fa-spin';
				$iconcolor = 'warning';
				$icontitle = gettext('Connecting');
				$buttonaction = 'disconnect';
				$buttontarget = 'ike';
				break;
			default:
				$statusicon = 'arrow-down';
				$iconcolor = 'danger';
				$icontitle = gettext('Disconnected');
				$buttonaction = 'connect';
				$buttontarget = 'all';
				break;
		}

		$data->tunnel .= '<td><i class="fa fa-' . $statusicon .
					' text-' . $iconcolor . '" ' .
					'title="' . $icontitle . '"></i> ';
		$data->tunnel .= ipsec_status_button('ajax', $buttonaction, $buttontarget, $p1conid, null, false);
		$data->tunnel .= '</td>';
		$data->tunnel .= "</tr>";

		if (is_array($tunnel['p2'])) {
			foreach ($tunnel['p2'] as $p2) {
				if (isset($p2['mobile']) || isset($ph2['disabled'])) {
					continue;
				}
				$p2src = ipsec_idinfo_to_text($p2['localid']);
				$p2dst = ipsec_idinfo_to_text($p2['remoteid']);

				if ($tunnel['p1']['iketype'] == 'ikev2' && !isset($tunnel['p1']['splitconn'])) {
					$p2conid = ipsec_conid($tunnel['p1']);
				} else {
					$p2conid = ipsec_conid($tunnel['p1'], $p2);
				}

				$data->tunnel .= "<tr>";
				$data->tunnel .= "<td>&nbsp;</td>";
				$data->tunnel .= "<td>" . htmlspecialchars($p2src) . "</td>";
				$data->tunnel .= "<td>&nbsp;</td>";
				$data->tunnel .= "<td>" . htmlspecialchars($p2dst) . "</td>";
				$data->tunnel .= "<td>&nbsp;</td>";
				$data->tunnel .= "<td>" . htmlspecialchars($p2['descr']) . "</td>";


				if (isset($p2['connected'])) {
					$statusicon = 'arrow-up';
					$iconcolor = 'success';
					$icontitle = gettext('Connected');
					$buttonaction = 'disconnect';
					$buttontarget = 'child';
				} else {
					$statusicon = 'arrow-down';
					$iconcolor = 'danger';
					$icontitle = gettext('Disconnected');
					$buttonaction = 'connect';
					$buttontarget = 'child';
				}

				$data->tunnel .= '<td><i class="fa fa-' . $statusicon .
							' text-' . $iconcolor . '" ' .
							'title="' . $icontitle . '"></i> ';
				$data->tunnel .= ipsec_status_button('ajax', $buttonaction, $buttontarget, $p2conid, null, false);
				$data->tunnel .= '</td>';
				$data->tunnel .= "</tr>";
			}
		} else {
			$data->tunnel .= '<tr><td colspan="7">&nbsp;' . gettext("No Phase 2 entries") . '</tr>';
		}
	}
	
	$data->mobile = "";
	if (is_array($mobile['pool'])) {
		$mucount = 0;
		foreach ($mobile['pool'] as $pool) {
			if (!is_array($pool['lease'])) {
				continue;
			}
			if(is_array($pool['lease']) && !empty($pool['lease'])){
				foreach ($pool['lease'] as $muser) {
					$mucount++;
					if ($muser['status'] == 'online') {
						$data->mobile .= "<tr style='background-color: #c5e5bb'>";
					} else {
						$data->mobile .= "<tr>";
					}
					$data->mobile .= "<td>" . htmlspecialchars($muser['id']) . "</td>";
					$data->mobile .= "<td>" . htmlspecialchars($muser['host']) . "</td>";
					$data->mobile .= "<td>";
					if ($muser['status'] == 'online') {
						$data->mobile .= "<span class='fa fa-check'></span><span style='font-weight: bold'> ";
					} else {
						$data->mobile .= "<span>  ";
					}
					$data->mobile .= htmlspecialchars($muser['status']) . "</span></td>";
					$data->mobile .= "</tr>";
				}
			}
		}
		if ($mucount == 0) {
			$data->mobile .= '<tr><td colspan="3">' . gettext("No mobile leases") . '</tr>';
		}
	} else {
		$data->mobile .= '<tr><td colspan="3">' . gettext("No mobile pools configured") . '</tr>';
	}
	
	print(json_encode($data));
	exit;
}

$widgetkey_nodash = str_replace("-", "", $widgetkey);

if (ipsec_enabled()) {
	if (is_array($user_settings['widgets'][$widgetkey]) &&
	    isset($user_settings['widgets'][$widgetkey]['default_tab'])) {
		$activetab = $user_settings['widgets'][$widgetkey]['default_tab'];
	} else {
		$activetab = 'overview';
	}
	$tab_array = array();
	foreach ($ipsec_widget_tabs as $tabname => $tabdescr) {
		if ($tabname == $activetab) {
			$active = true;
		} else {
			$active = false;
		}
		$tab_array[] = array($tabdescr, $active, htmlspecialchars($widgetkey_nodash) . '-' . $tabname);
	}

	display_widget_tabs($tab_array);
}

$mobile = ipsec_dump_mobile();
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;

if (ipsec_enabled()): ?>
<style>
.ipsec-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    font-family: Inter, sans-serif;
    margin-bottom: 1.5rem;
}

.ipsec-widget:last-child {
    margin-bottom: 0;
}

.ipsec-widget-header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
}

.ipsec-widget-title {
    font-size: 1.125rem;
    font-weight: 500;
    color: #2d3748;
    margin: 0;
}

.ipsec-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.ipsec-table th {
    color: #4a5568;
    font-weight: 500;
    background: #f5f8fa;
    padding: 0.75rem;
    font-size: 13px;
    line-height: 20px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.ipsec-table td {
    padding: 1rem 0.75rem;
    font-size: 13px;
    line-height: 20px;
    color: #4a5568;
    border-bottom: 1px solid #e2e8f0;
}

.ipsec-table tr:last-child td {
    border-bottom: none;
}

.ipsec-table tr:hover {
    background-color: #f7fafc;
}

.ipsec-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.ipsec-status i {
    font-size: 14px;
}

.ipsec-status i.fa-arrow-up {
    color: #48BB78;
}

.ipsec-status i.fa-arrow-down {
    color: #E53E3E;
}

.ipsec-status i.fa-spinner {
    color: #ECC94B;
}

.ipsec-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.ipsec-action button {
    padding: 0.25rem 0.5rem;
    font-size: 12px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ipsec-action button:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.ipsec-mobile-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ipsec-mobile-user.online {
    background-color: #f0fff4;
}

.ipsec-mobile-user.offline {
    background-color: #fff5f5;
}

.ipsec-mobile-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.ipsec-mobile-status i {
    font-size: 14px;
}

.ipsec-mobile-status i.fa-check {
    color: #48BB78;
}

.ipsec-empty {
    text-align: center;
    padding: 2rem;
    color: #718096;
    font-style: italic;
}

.ipsec-address {
    font-family: "Inter Mono", monospace;
    font-size: 12px;
    color: #4a5568;
}

.ipsec-description {
    font-size: 13px;
    color: #2d3748;
    font-weight: 500;
}
</style>

<div id="<?=htmlspecialchars($widgetkey_nodash)?>-overview" style="display: <?=$activetab == 'overview' ? 'block' : 'none'?>">
    <div class="ipsec-widget">
        <div class="ipsec-widget-header">
            <h3 class="ipsec-widget-title"><?=gettext("IPsec Status")?></h3>
        </div>
        <div class="table-responsive">
            <table class="ipsec-table" data-sortable>
                <thead>
                    <tr>
                        <th><?=gettext("Phase 1")?></th>
                        <th><?=gettext("Phase 2")?></th>
                        <th><?=gettext("Mobile")?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (ipsec_enabled() && get_service_status(array('name' => 'ipsec'))) {
                        $cmap = ipsec_status();
                        $mobile = ipsec_dump_mobile();
                    } else {
                        $cmap = array();
                    }

                    $mobileactive = 0;
                    $mobileinactive = 0;
                    if (is_array($mobile['pool'])) {
                        foreach ($mobile['pool'] as $pool) {
                            $mobileactive += $pool['online'];
                            $mobileinactive += $pool['offline'];
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($cmap) && is_array($cmap['connected']['p1']) && is_array($cmap['disconnected']['p1'])): ?>
                                <span class="ipsec-status">
                                    <i class="fa fa-arrow-up"></i>
                                    <?=count($cmap['connected']['p1'])?> / <?=count($cmap['connected']['p1']) + count($cmap['disconnected']['p1'])?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($cmap) && is_array($cmap['connected']['p2']) && is_array($cmap['disconnected']['p2'])): ?>
                                <span class="ipsec-status">
                                    <i class="fa fa-arrow-up"></i>
                                    <?=count($cmap['connected']['p2'])?> / <?=count($cmap['connected']['p2']) + count($cmap['disconnected']['p2'])?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ipsec-status">
                                <i class="fa fa-arrow-up"></i>
                                <?=$mobileactive?> / <?=$mobileactive + $mobileinactive?>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
    </div>

<div id="<?=htmlspecialchars($widgetkey_nodash)?>-tunnel" style="display: <?=$activetab == 'tunnel' ? 'block' : 'none'?>">
    <div class="ipsec-widget">
        <div class="ipsec-widget-header">
            <h3 class="ipsec-widget-title"><?=gettext("Tunnels")?></h3>
        </div>
        <div class="table-responsive">
            <table class="ipsec-table" data-sortable>
                <thead>
                    <tr>
                        <th><?=gettext("Source")?></th>
                        <th><?=gettext("Destination")?></th>
                        <th><?=gettext("Description")?></th>
                        <th><?=gettext("Status")?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $gateways_status = return_gateways_status(true);
                    foreach ($cmap as $k => $tunnel):
                        if (in_array($k, array('connected', 'disconnected')) ||
                            (!array_key_exists('p1', $tunnel) ||
                            isset($tunnel['p1']['disabled'])) ||
                            isset($tunnel['p1']['mobile'])) {
                            continue;
                        }

                        $p1src = ipsec_get_phase1_src($tunnel['p1'], $gateways_status);
                        if (empty($p1src)) {
                            $p1src = gettext("Unknown");
                        } else {
                            $p1src = str_replace(',', ', ', $p1src);
                        }
                        $p1dst = ipsec_get_phase1_dst($tunnel['p1']);
                        $p1conid = ipsec_conid($tunnel['p1'], null);

                        if (is_array($tunnel['status'])) {
                            $tstatus = array_pop($tunnel['status']);
                        } else {
                            $tstatus = array('state' => 'DISCONNECTED');
                        }

                        switch ($tstatus['state']) {
                            case 'ESTABLISHED':
                                $statusicon = 'arrow-up';
                                $iconcolor = 'success';
                                $icontitle = gettext('Connected');
                                $buttonaction = 'disconnect';
                                $buttontarget = 'ike';
                                break;
                            case 'CONNECTING':
                                $statusicon = 'spinner fa-spin';
                                $iconcolor = 'warning';
                                $icontitle = gettext('Connecting');
                                $buttonaction = 'disconnect';
                                $buttontarget = 'ike';
                                break;
                            default:
                                $statusicon = 'arrow-down';
                                $iconcolor = 'danger';
                                $icontitle = gettext('Disconnected');
                                $buttonaction = 'connect';
                                $buttontarget = 'all';
                                break;
                        }
                    ?>
                    <tr>
                        <td class="ipsec-address"><?=htmlspecialchars($p1src)?></td>
                        <td class="ipsec-address"><?=htmlspecialchars($p1dst)?></td>
                        <td class="ipsec-description"><?=htmlspecialchars($tunnel['p1']['descr'])?></td>
                        <td>
                            <div class="ipsec-status">
                                <i class="fa fa-<?=$statusicon?> text-<?=$iconcolor?>" title="<?=$icontitle?>"></i>
                                <?=ipsec_status_button('ajax', $buttonaction, $buttontarget, $p1conid, null, false)?>
                            </div>
                        </td>
                    </tr>
                    <?php
                        if (is_array($tunnel['p2'])) {
                            foreach ($tunnel['p2'] as $p2) {
                                if (isset($p2['mobile']) || isset($ph2['disabled'])) {
                                    continue;
                                }
                                $p2src = ipsec_idinfo_to_text($p2['localid']);
                                $p2dst = ipsec_idinfo_to_text($p2['remoteid']);

                                if ($tunnel['p1']['iketype'] == 'ikev2' && !isset($tunnel['p1']['splitconn'])) {
                                    $p2conid = ipsec_conid($tunnel['p1']);
                                } else {
                                    $p2conid = ipsec_conid($tunnel['p1'], $p2);
                                }

                                if (isset($p2['connected'])) {
                                    $statusicon = 'arrow-up';
                                    $iconcolor = 'success';
                                    $icontitle = gettext('Connected');
                                    $buttonaction = 'disconnect';
                                    $buttontarget = 'child';
                                } else {
                                    $statusicon = 'arrow-down';
                                    $iconcolor = 'danger';
                                    $icontitle = gettext('Disconnected');
                                    $buttonaction = 'connect';
                                    $buttontarget = 'child';
                                }
                    ?>
                    <tr>
                        <td class="ipsec-address"><?=htmlspecialchars($p2src)?></td>
                        <td class="ipsec-address"><?=htmlspecialchars($p2dst)?></td>
                        <td class="ipsec-description"><?=htmlspecialchars($p2['descr'])?></td>
                        <td>
                            <div class="ipsec-status">
                                <i class="fa fa-<?=$statusicon?> text-<?=$iconcolor?>" title="<?=$icontitle?>"></i>
                                <?=ipsec_status_button('ajax', $buttonaction, $buttontarget, $p2conid, null, false)?>
                            </div>
                        </td>
                    </tr>
                    <?php
                            }
                        }
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

<div id="<?=htmlspecialchars($widgetkey_nodash)?>-mobile" style="display: <?=$activetab == 'mobile' ? 'block' : 'none'?>">
    <div class="ipsec-widget">
        <div class="ipsec-widget-header">
            <h3 class="ipsec-widget-title"><?=gettext("Mobile Users")?></h3>
        </div>
        <div class="table-responsive">
            <table class="ipsec-table" data-sortable>
                <thead>
                    <tr>
                        <th><?=gettext("ID")?></th>
                        <th><?=gettext("Host")?></th>
                        <th><?=gettext("Status")?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (is_array($mobile['pool'])) {
                        $mucount = 0;
                        foreach ($mobile['pool'] as $pool) {
                            if (!is_array($pool['lease'])) {
                                continue;
                            }
                            if(is_array($pool['lease']) && !empty($pool['lease'])){
                                foreach ($pool['lease'] as $muser) {
                                    $mucount++;
                    ?>
                    <tr class="ipsec-mobile-user <?=$muser['status'] == 'online' ? 'online' : 'offline'?>">
                        <td><?=htmlspecialchars($muser['id'])?></td>
                        <td><?=htmlspecialchars($muser['host'])?></td>
                        <td>
                            <div class="ipsec-mobile-status">
                                <?php if ($muser['status'] == 'online'): ?>
                                    <i class="fa fa-check"></i>
                                <?php endif; ?>
                                <span><?=htmlspecialchars($muser['status'])?></span>
                            </div>
                        </td>
                    </tr>
                    <?php
                                }
                            }
                        }
                        if ($mucount == 0) {
                    ?>
                    <tr>
                        <td colspan="3" class="ipsec-empty"><?=gettext("No mobile leases")?></td>
                    </tr>
                    <?php
                        }
                    } else {
                    ?>
                    <tr>
                        <td colspan="3" class="ipsec-empty"><?=gettext("No mobile pools configured")?></td>
                    </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
	<div>
		<h5 style="padding-left:10px;"><?= htmlspecialchars(gettext("There are no configured or enabled IPsec Tunnels")) ?></h5>
		<p  style="padding-left:10px;"><?= sprintf(htmlspecialchars(gettext('IPsec can be configured %shere.%s')), '<a href="vpn_ipsec.php">', '</a>') ?></p>
	</div>
<?php endif;

?>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
<form action="/widgets/widgets/ipsec.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Default tab')?></label>
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<div class="col-sm-6">
<?php
	foreach ($ipsec_widget_tabs as $tabname => $tabdescr) {
		if ($tabname == $activetab) {
			$checked_button = 'checked';
		} else {
			$checked_button = '';
		}
?>
			<div class="radio">
				<label><input name="default_tab" type="radio" id="<?=$tabname?>_tab" value="<?=$tabname?>" <?=$checked_button;?> /> <?=$tabdescr?></label>
			</div>
<?php
	};
?>
		</div>
	</div>
	<br />

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>
<script type="text/javascript">
//<![CDATA[

curtab = "<?=$activetab?>";

function changeTabDIV(selectedDiv) {
	var dashpos = selectedDiv.indexOf("-");
	var tabclass = selectedDiv.substring(0, dashpos);
	curtab = selectedDiv.substring(dashpos+1, 20);
	d = document;

	//get deactive tabs first
	tabclass = tabclass + "-class-tabdeactive";

	var tabs = document.getElementsByClassName(tabclass);
	var incTabSelected = selectedDiv + "-deactive";

	for (i = 0; i < tabs.length; i++) {
		var tab = tabs[i].id;
		dashpos = tab.lastIndexOf("-");
		var tab2 = tab.substring(0, dashpos) + "-deactive";

		if (tab2 == incTabSelected) {
			tablink = d.getElementById(tab2);
			tablink.style.display = "none";
			tab2 = tab.substring(0, dashpos) + "-active";
			tablink = d.getElementById(tab2);
			tablink.style.display = "table-cell";

			//now show main div associated with link clicked
			tabmain = d.getElementById(selectedDiv);
			tabmain.style.display = "block";
		} else {
			tab2 = tab.substring(0, dashpos) + "-deactive";
			tablink = d.getElementById(tab2);
			tablink.style.display = "table-cell";
			tab2 = tab.substring(0, dashpos) + "-active";
			tablink = d.getElementById(tab2);
			tablink.style.display = "none";

			//hide sections we don't want to see
			tab2 = tab.substring(0, dashpos);
			tabmain = d.getElementById(tab2);
			tabmain.style.display = "none";
		}
	}
}

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function ipsec_callback(s) {
		try{
			var obj = JSON.parse(s);

			$('tbody', '#<?= htmlspecialchars($widgetkey_nodash) ?>-overview').html(obj.overview);
			$('tbody', '#<?= htmlspecialchars($widgetkey_nodash) ?>-tunnel').html(obj.tunnel);
			$('tbody', '#<?= htmlspecialchars($widgetkey_nodash) ?>-mobile').html(obj.mobile);
		}catch(e){

		}
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax"
	 };

	// Create an object defining the widget refresh AJAX call
	var ipsecObject = new Object();
	ipsecObject.name = "IPsec";
	ipsecObject.url = "/widgets/widgets/ipsec.widget.php";
	ipsecObject.callback = ipsec_callback;
	ipsecObject.parms = postdata;
	ipsecObject.freq = 1;

	// Register the AJAX object
	register_ajax(ipsecObject);

	// ---------------------------------------------------------------------------------------------------
});
//]]>
</script>
