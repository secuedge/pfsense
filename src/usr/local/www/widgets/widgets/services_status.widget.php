<?php
/*
 * services_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Sam Wenham
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
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

$services = get_services();

$numsvcs = count($services);

for ($idx=0; $idx<$numsvcs; $idx++) {
	if (!is_array($services[$idx])) {
		$services[$idx] = array();
	}
	$services[$idx]['dispname'] = $services[$idx]['name'];
}

// If there are any duplicated names, add an incrementing suffix
for ($idx=1; $idx < $numsvcs; $idx++) {
	$name = $services[$idx]['name'];

	for ($chk = $idx +1, $sfx=2; $chk <$numsvcs; $chk++) {
		if ($services[$chk]['dispname'] == $name) {
			$services[$chk]['dispname'] .= '_' . $sfx++;
		}
	}
}

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();

	foreach ($services as $service) {
		array_push($validNames, $service['dispname']);
	}

	if (is_array($_POST['show'])) {
		array_set_path($user_settings, "widgets/{$_POST['widgetkey']}/filter", implode(',', array_diff($validNames, $_POST['show'])));
	} else {
		array_set_path($user_settings, "widgets/{$_POST['widgetkey']}/filter", implode(',', $validNames));
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Service Status Filter via Dashboard."));
	header("Location: /index.php");
}

?>
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
.services-table {
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
.services-table thead th {
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

.services-table thead th::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 1.5rem;
    right: 1.5rem;
    height: 2px;
    background: linear-gradient(90deg, #2196f3, transparent);
}

/* Row Styles */
.services-table tbody tr {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.services-table tbody tr:hover {
    background: rgba(33, 150, 243, 0.05);
    transform: translateY(-2px) scale(1.002);
    box-shadow: 0 4px 20px rgba(33, 150, 243, 0.15);
    z-index: 1;
}

.services-table td {
    padding: 1.5rem;
    font-size: 13px;
    line-height: 20px;
    color: #2c3e50;
    vertical-align: middle;
    font-weight: 500;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(25, 118, 210, 0.06);
}

/* Service Name */
.service-name {
    font-weight: 500;
    color: #1976D2;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Status Icons */
.status-icon {
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

.services-table tr:hover .status-icon {
    transform: scale(1.1);
}

.status-icon.success {
    background: #E8F5E9;
    color: #00c853;
    text-shadow: 0 0 10px rgba(0, 200, 83, 0.3);
}

.status-icon.error {
    background: #FFEBEE;
    color: #ff1744;
    text-shadow: 0 0 10px rgba(255, 23, 68, 0.3);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    align-items: center;
    gap: 8px;
}

.action-btn {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    color: #1976D2;
    background: rgba(33, 150, 243, 0.1);
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.action-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.action-btn:hover {
    background: #1976D2;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(25, 118, 210, 0.25);
}

.action-btn:hover::before {
    transform: translateX(100%);
}

.action-btn:active {
    transform: translateY(0);
}

/* Empty State */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
    color: #546e7a;
    background: linear-gradient(to bottom right, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.9));
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
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Apply Animations */
.services-table tbody tr {
    animation: fadeIn 0.5s ease-out forwards;
    animation-delay: calc(var(--row-index, 0) * 0.1s);
    opacity: 0;
}

.status-icon.success {
    animation: pulse 2s infinite;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .services-table td, 
    .services-table th {
        padding: 1rem;
    }
    
    .action-buttons {
        gap: 4px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
    }
}
</style>

<div class="table-responsive">
    <table class="services-table">
        <thead>
            <tr>
                <th><?=gettext("Service")?></th>
                <th><?=gettext("Description")?></th>
                <th><?=gettext("Actions")?></th>
            </tr>
        </thead>
        <tbody>
<?php
$services = get_services();
if (count($services) > 0):
    $row_index = 0;
    foreach ($services as $service):
        if (!$service['name']) continue;
?>
            <tr style="--row-index: <?=$row_index++?>;">
                <td>
                    <div class="service-name">
                        <?php if ($service['status'] === true): ?>
                            <span class="status-icon success">
                                <i class="fas fa-check"></i>
                            </span>
                        <?php else: ?>
                            <span class="status-icon error">
                                <i class="fas fa-times"></i>
                            </span>
                        <?php endif; ?>
                        <?=$service['name']?>
                    </div>
                </td>
                <td><?=$service['description'] ?: get_pkg_descr($service['name'])?></td>
                <td>
                    <div class="action-buttons">
                        <?=get_service_control_links($service)?>
                    </div>
                </td>
            </tr>
<?php
    endforeach;
else:
?>
            <tr>
                <td colspan="3" class="empty-state"><?=gettext("No services found.")?></td>
            </tr>
<?php endif; ?>
        </tbody>
    </table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/services_status.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Service")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$idx = 0;
				foreach ($services as $service):
					if (!empty(trim($service['dispname'])) || is_numeric($service['dispname'])) {
?>
						<tr>
							<td><?=$service['dispname']?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$service['dispname']?>" type="checkbox" <?=(!in_array($service['dispname'], $skipservices) ? 'checked':'')?>></td>
						</tr>
<?php
					}
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
	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
	});
//]]>
</script>
