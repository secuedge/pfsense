<?php
/*
 * carp_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2007 Sam Wenham
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
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/carp_status.inc");

$carp_enabled = get_carp_status();

?>
<style>
.carp-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    font-family: Inter, sans-serif;
}

.carp-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.carp-table th {
    color: #4a5568;
    font-weight: 500;
    background: #f5f8fa;
    padding: 0.75rem;
    font-size: 13px;
    line-height: 20px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.carp-table td {
    padding: 1rem 0.75rem;
    font-size: 13px;
    line-height: 20px;
    color: #4a5568;
    border-bottom: 1px solid #e2e8f0;
}

.carp-table tr:last-child td {
    border-bottom: none;
}

.carp-table tr:hover {
    background-color: #f7fafc;
}

.carp-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.carp-status i {
    font-size: 14px;
}

.carp-status i.fa-play-circle {
    color: #48BB78;
}

.carp-status i.fa-pause-circle {
    color: #ED8936;
}

.carp-status i.fa-question-circle {
    color: #E53E3E;
}

.carp-status i.fa-times-circle {
    color: #718096;
}

.carp-empty {
    text-align: center;
    padding: 2rem;
    color: #718096;
    font-style: italic;
}

.carp-empty a {
    color: #4299e1;
    text-decoration: none;
    transition: color 0.2s ease;
}

.carp-empty a:hover {
    color: #3182ce;
    text-decoration: underline;
}
</style>

<div class="carp-widget">
    <div class="table-responsive">
        <table class="carp-table sortable-theme-bootstrap" data-sortable>
            <thead>
                <tr>
                    <th><?=gettext("CARP Interface")?></th>
                    <th><?=gettext("IP Address")?></th>
                    <th><?=gettext("Status")?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $carpint = 0;
            foreach (config_get_path('virtualip/vip', []) as $carp) {
                if ($carp['mode'] != "carp") {
                    continue;
                }
                $carpint++;
                $ipaddress = $carp['subnet'];
                $netmask = $carp['subnet_bits'];
                $vhid = $carp['vhid'];
                $status = get_carp_interface_status("_vip{$carp['uniqid']}");
            ?>
                <tr>
                    <td>
                        <span title="<?=htmlspecialchars($carp['descr'])?>">
                            <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$vhid}");?>
                        </span>
                    </td>
                    <?php
                    if ($carp_enabled == false) {
                        $icon = 'times-circle';
                        $status = "DISABLED";
                    } else {
                        if ($status == "MASTER") {
                            $icon = 'play-circle';
                        } else if ($status == "BACKUP") {
                            $icon = 'pause-circle';
                        } else if ($status == "INIT") {
                            $icon = 'question-circle';
                        }
                    }
                    if ($ipaddress) {
                    ?>
                        <td><?=htmlspecialchars($ipaddress);?></td>
                        <td>
                            <div class="carp-status">
                                <i class="fa fa-<?=$icon?>"></i>
                                <span><?=htmlspecialchars($status)?></span>
                            </div>
                        </td>
                    <?php
                    } else {
                    ?>
                        <td colspan="2"></td>
                    <?php
                    }
                    ?>
                </tr>
            <?php
            }
            if ($carpint === 0) {
            ?>
                <tr>
                    <td colspan="3" class="carp-empty">
                        <?=gettext('No CARP Interfaces Defined.')?> 
                        <?=sprintf(gettext('Click %1$shere%2$s to configure CARP.'), '<a href="status_carp.php">', '</a>')?>
                    </td>
                </tr>
            <?php
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
