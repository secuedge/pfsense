<?php
/*
 * index.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
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

##|+PRIV
##|*IDENT=page-system-login-logout
##|*NAME=System: Login / Logout / Dashboard
##|*DESCR=Allow access to the 'System: Login / Logout' page and Dashboard.
##|*MATCH=index.php*
##|-PRIV

// Message to display if the session times out and an AJAX call is made
$timeoutmessage = gettext("The dashboard web session has timed out.\\n" .
	"It will not update until you refresh the page and log-in again.");

// Turn on buffering to speed up rendering
ini_set('output_buffering', 'true');

// Start buffering with a cache size of 100000
ob_start(null, "1000");

## Load Essential Includes
require_once('guiconfig.inc');
require_once('functions.inc');
require_once('notices.inc');

if (isset($_POST['closenotice'])) {
	close_notice($_POST['closenotice']);
	sleep(1);
	exit;
}

if (isset($_REQUEST['closenotice'])) {
	close_notice($_REQUEST['closenotice']);
	sleep(1);
}

if ((g_get('disablecrashreporter') != true) && (system_has_crash_data() || system_has_php_errors())) {
	$savemsg = sprintf(gettext("%s has detected a crash report or programming bug."), g_get('product_label')) . " ";
	if (isAllowedPage("/crash_reporter.php")) {
		$savemsg .= sprintf(gettext('Click %1$shere%2$s for more information.'), '<a href="crash_reporter.php">', '</a>');
	} else {
		$savemsg .= sprintf(gettext("Contact a firewall administrator for more information."));
	}
	$class = "warning";
}

## Include each widget php include file.
## These define vars that specify the widget title and title link.

$directory = "/usr/local/www/widgets/include/";
$dirhandle = opendir($directory);
$filename = "";

while (($filename = readdir($dirhandle)) !== false) {
	if (strtolower(substr($filename, -4)) == ".inc" && file_exists($directory . $filename)) {
		include_once($directory . $filename);
	}
}

##build list of widgets
foreach (glob("/usr/local/www/widgets/widgets/*.widget.php") as $file) {
	$basename = basename($file, '.widget.php');
	// Get the widget title that should be in a var defined in the widget's inc file.
	$widgettitle = ${$basename . '_title'};

	if (empty(trim($widgettitle))) {
		// Fall back to constructing a title from the file name of the widget.
		$widgettitle = ucwords(str_replace('_', ' ', $basename));
	}

	$known_widgets[$basename . '-0'] = array(
		'basename' => $basename,
		'title' => $widgettitle,
		'display' => 'none',
		'multicopy' => ${$basename . '_allow_multiple_widget_copies'}
	);
}

// Register the new interface summary widget
$available_widgets[] = array(
	'name' => 'interface_summary',
	'file' => 'interface_summary.widget.php',
	'title' => 'Interface Summary',
	'description' => 'Shows a real-time pie chart and table of firewall log interface summary.'
);

##if no config entry found, initialize config entry
if (!is_array($config['widgets'])) {
	config_set_path('widgets', array());
}

if (!is_array($user_settings['widgets'])) {
	// Set default widgets if user has no custom widgets
	$user_settings['widgets'] = array();
	$user_settings['widgets']['sequence'] = 'analytics-0:col1:open:0,system_information-0:col1:open:0,traffic_graphs-0:col1:open:0';
}

if ($_POST && $_POST['sequence']) {
	// Start with the user's widget settings.
	$widget_settings = $user_settings['widgets'];

	$widget_sep = ',';
	$widget_seq_array = explode($widget_sep, rtrim($_POST['sequence'], $widget_sep));
	$widget_counter_array = array();
	$widget_sequence = '';
	$widget_sep = '';

	// First pass: Record existing widgets and their positions
	foreach ($widget_seq_array as $widget_seq_data) {
		list($basename, $col, $display, $widget_counter) = explode(':', $widget_seq_data);
		
		if ($widget_counter != 'next') {
			if (!is_numeric($widget_counter)) {
				continue;
			}
			$widget_counter_array[$basename][$widget_counter] = true;
			$widget_sequence .= $widget_sep . $widget_seq_data;
			$widget_sep = ',';
		}
	}

	// Second pass: Handle new widgets
	foreach ($widget_seq_array as $widget_seq_data) {
		list($basename, $col, $display, $widget_counter) = explode(':', $widget_seq_data);
		
		if ($widget_counter == 'next') {
			// Find the next available counter for this widget type
			$instance_num = 0;
			while (isset($widget_counter_array[$basename][$instance_num])) {
				$instance_num++;
			}
			
			$widget_sequence .= $widget_sep . $basename . ':' . $col . ':' . $display . ':' . $instance_num;
			$widget_counter_array[$basename][$instance_num] = true;
			$widget_sep = ',';
		}
	}

	$widget_settings['sequence'] = $widget_sequence;

	// Save widget-specific configurations
	foreach ($widget_counter_array as $basename => $instances) {
		foreach ($instances as $instance => $value) {
			$widgetconfigname = $basename . '-' . $instance . '-config';
			if (isset($_POST[$widgetconfigname])) {
				$widget_settings[$widgetconfigname] = $_POST[$widgetconfigname];
			}
		}
	}

	save_widget_settings($_SESSION['Username'], $widget_settings);
	header("Location: /");
	exit;
}

## Load Functions Files
require_once('includes/functions.inc.php');

## Check to see if we have a swap space,
## if true, display, if false, hide it ...
if (file_exists("/usr/sbin/swapinfo")) {
	$swapinfo = `/usr/sbin/swapinfo`;
	if (stristr($swapinfo, '%') == true) $showswap=true;
}

## If it is the first time webConfigurator has been
## accessed since initial install show this stuff.
if (file_exists('/conf/trigger_initial_wizard')) {
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<link rel="stylesheet" href="/css/pfSense.css" />
		<title><?=g_get('product_label')?>.home.arpa - <?=g_get('product_label')?> first time setup</title>
		<meta http-equiv="refresh" content="1;url=wizard.php?xml=setup_wizard.xml" />
	</head>
	<body id="loading-wizard" class="no-menu">
		<div id="jumbotron">
			<div class="container">
				<div class="col-sm-offset-3 col-sm-6 col-xs-12">
					<font color="white">
					<p><h3><?=sprintf(gettext("Welcome to %s!") . "\n", g_get('product_label'))?></h3></p>
					<p><?=gettext("One moment while the initial setup wizard starts.")?></p>
					<p><?=gettext("Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal GUI.")?></p>
					<p><?=sprintf(gettext("To bypass the wizard, click on the %s logo on the initial page."), g_get('product_label'))?></p>
					</font>
				</div>
			</div>
		</div>
	</body>
</html>
<?php
	exit;
}

##build widget saved list information
if ($user_settings['widgets']['sequence'] != "") {
	$dashboardcolumns = isset($user_settings['webgui']['dashboardcolumns']) ? (int) $user_settings['webgui']['dashboardcolumns'] : 2;
	$pconfig['sequence'] = $user_settings['widgets']['sequence'];
	$widgetsfromconfig = array();

	foreach (explode(',', $pconfig['sequence']) as $line) {
		$line_items = explode(':', $line);
		if (count($line_items) == 3) {
			// There can be multiple copies of a widget on the dashboard.
			// Default the copy number if it is not present (e.g. from old configs)
			$line_items[] = 0;
		}

		list($basename, $col, $display, $copynum) = $line_items;
		if (!is_numeric($copynum)) {
			continue;
		}

		// be backwards compatible
		// If the display column information is missing, we will assign a temporary
		// column here. Next time the user saves the dashboard it will fix itself
		if ($col == "") {
			if ($basename == "system_information") {
				$col = "col1";
			} else {
				$col = "col2";
			}
		}

		// Limit the column to the current dashboard columns.
		if (substr($col, 3) > $dashboardcolumns) {
			$col = "col" . $dashboardcolumns;
		}

		$offset = strpos($basename, '-container');
		if (false !== $offset) {
			$basename = substr($basename, 0, $offset);
		}
		
		// Generate a unique widget key
		$widgetkey = $basename . '-' . $copynum;
		
		// Check if this widget already exists
		if (isset($widgetsfromconfig[$widgetkey])) {
			// Find the next available copy number
			$newcopynum = $copynum;
			do {
				$newcopynum++;
				$newkey = $basename . '-' . $newcopynum;
			} while (isset($widgetsfromconfig[$newkey]));
			
			$widgetkey = $newkey;
		}

		if (isset($user_settings['widgets'][$widgetkey]['descr'])) {
			$widgettitle = htmlentities($user_settings['widgets'][$widgetkey]['descr']);
		} else {
			// Get the widget title that should be in a var defined in the widget's inc file.
			$widgettitle = ${$basename . '_title'};

			if (empty(trim($widgettitle))) {
				// Fall back to constructing a title from the file name of the widget.
				$widgettitle = ucwords(str_replace('_', ' ', $basename));
			}
		}

		$widgetsfromconfig[$widgetkey] = array(
			'basename' => $basename,
			'title' => $widgettitle,
			'col' => $col,
			'display' => $display,
			'copynum' => isset($newcopynum) ? $newcopynum : $copynum,
			'multicopy' => ${$basename . '_allow_multiple_widget_copies'}
		);

		// Update the known_widgets entry so we know if any copy of the widget is being displayed
		$known_widgets[$basename . '-0']['display'] = $display;
	}

	// add widgets that may not be in the saved configuration, in case they are to be displayed later
	$widgets = $widgetsfromconfig + $known_widgets;

	##find custom configurations of a particular widget and load its info to $pconfig
	foreach ($widgets as $widgetname => $widgetconfig) {
		if ($config['widgets'][$widgetname . '-config']) {
			$pconfig[$widgetname . '-config'] = config_get_path("widgets/{$widgetname}-config");
		}
	}
}

## Get the configured options for Show/Hide available widgets panel.
$dashboard_available_widgets_hidden = !$user_settings['webgui']['dashboardavailablewidgetspanel'];

if ($dashboard_available_widgets_hidden) {
	$panel_state = 'out';
	$panel_body_state = 'in';
} else {
	$panel_state = 'in';
	$panel_body_state = 'out';
}

## Set Page Title and Include Header
$pgtitle = array(gettext("Status"), gettext("Dashboard"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, $class);
}

?>
<style>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 2rem;
  padding: 2rem;
}
.dashboard-card {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 2px 16px rgba(25, 118, 210, 0.07);
  padding: 2rem 2.5rem;
  margin-bottom: 0;
  min-width: 0;
}
.dashboard-card h2 {
  font-size: 1.3rem;
  font-weight: 700;
  color: #1976D2;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
@media (max-width: 900px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
    padding: 1rem;
  }
  .dashboard-card {
    padding: 1.2rem 1rem;
  }
}
</style>
<div class="dashboard-grid">
    <div class="dashboard-card">
        <h2><i class="fas fa-server"></i> System Information</h2>
        <?php include('/usr/local/www/widgets/widgets/system_information.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-chart-line"></i> Traffic Graphs</h2>
        <?php include('/usr/local/www/widgets/widgets/traffic_graphs.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-cogs"></i> Services Status</h2>
        <?php include('/usr/local/www/widgets/widgets/services_status.widget.php'); ?>
    </div>
	<div class="dashboard-card">
        <h2><i class="fas fa-file-alt"></i> Log</h2>
        <?php include('/usr/local/www/widgets/widgets/log.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-lock"></i> OpenVPN</h2>
        <?php include('/usr/local/www/widgets/widgets/openvpn.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-shield-alt"></i> IPsec</h2>
        <?php include('/usr/local/www/widgets/widgets/ipsec.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-network-wired"></i> Interfaces</h2>
        <?php include('/usr/local/www/widgets/widgets/interfaces.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-route"></i> Gateways</h2>
        <?php include('/usr/local/www/widgets/widgets/gateways.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-project-diagram"></i> CARP Status</h2>
        <?php include('/usr/local/www/widgets/widgets/carp_status.widget.php'); ?>
    </div>
    <div class="dashboard-card">
        <h2><i class="fas fa-user-lock"></i> Captive Portal Status</h2>
        <?php include('/usr/local/www/widgets/widgets/captive_portal_status.widget.php'); ?>
    </div>
</div>

<div class="hidden" id="widgetSequence">
	<form action="/" method="post" id="widgetSequence_form" name="widgetForm">
		<input type="hidden" name="sequence" value="" />
	</form>
</div>

<?php
/*
 * Import the modal form used to display any HTML text a package may want to display
 * on installation or removal
 */

?>

<script type="text/javascript">
//<![CDATA[

dirty = false;
function updateWidgets(newWidget) {
	var sequence = '';
	var sep = '';
	var widgetCounters = {};
	
	// First, collect all existing widgets and their counters
	$('.panel').each(function() {
		var widgetId = $(this).attr('id');
		if (widgetId) {
			var parts = widgetId.split('-');
			if (parts.length >= 2) {
				var basename = parts[0];
				var counter = parseInt(parts[1]);
				if (!isNaN(counter)) {
					if (!widgetCounters[basename]) {
						widgetCounters[basename] = [];
					}
					widgetCounters[basename].push(counter);
				}
			}
		}
	});
	
	// Build sequence for existing widgets
	$('.panel').each(function() {
		var widgetId = $(this).attr('id');
		if (widgetId) {
			var parts = widgetId.split('-');
			if (parts.length >= 2) {
				var basename = parts[0];
				var counter = parseInt(parts[1]);
				if (!isNaN(counter)) {
					var col = $(this).closest('.col-sm-6').index() + 1;
					var display = $(this).hasClass('panel-collapsed') ? 'closed' : 'open';
					sequence += sep + basename + ':' + col + ':' + display + ':' + counter;
					sep = ',';
				}
			}
		}
	});
	
	// Add new widget if provided
	if (newWidget) {
		var parts = newWidget.split('-');
		if (parts.length >= 2) {
			var basename = parts[0];
			var counter = 0;
			
			// Find the next available counter for this widget type
			if (widgetCounters[basename]) {
				while (widgetCounters[basename].includes(counter)) {
					counter++;
				}
			}
			
			// Add new widget to sequence
			var col = (basename === 'system_information') ? 1 : 2;
			sequence += sep + basename + ':' + col + ':open:' + counter;
		}
	}
	
	$('#sequence').val(sequence);
}

// Determine if all the checkboxes are checked
function are_all_checked(checkbox_panel_ref) {
	var allBoxesChecked = true;
	$(checkbox_panel_ref).each(function() {
		if ((this.type == 'checkbox') && !this.checked) {
			allBoxesChecked = false;
		}
	});
	return allBoxesChecked;
}

// If the checkboxes are all checked, then clear them all.
// Otherwise set them all.
function set_clear_checkboxes(checkbox_panel_ref) {
	checkTheBoxes = !are_all_checked(checkbox_panel_ref);

	$(checkbox_panel_ref).each(function() {
		$(this).prop("checked", checkTheBoxes);
	});
}

// Set the given id to All or None button depending if the checkboxes are all checked.
function set_all_none_button(checkbox_panel_ref, all_none_button_id) {
	if (are_all_checked(checkbox_panel_ref)) {
		text = "<?=gettext('None')?>";
	} else {
		text = "<?=gettext('All')?>";
	}

	$("#" + all_none_button_id).html('<i class="fa fa-undo icon-embed-btn"></i>' + text);
}

// Setup the necessary events to manage the All/None button and included checkboxes
// used for selecting the items to show on a widget.
function set_widget_checkbox_events(checkbox_panel_ref, all_none_button_id) {
		set_all_none_button(checkbox_panel_ref, all_none_button_id);

		$(checkbox_panel_ref).change(function() {
			set_all_none_button(checkbox_panel_ref, all_none_button_id);
		});

		$("#" + all_none_button_id).click(function() {
			set_clear_checkboxes(checkbox_panel_ref);
			set_all_none_button(checkbox_panel_ref, all_none_button_id);
		});
}

// ---------------------Centralized widget refresh system -------------------------------------------
// These need to live outside of the events.push() function to enable the widgets to see them
var ajaxspecs = new Array();	// Array to hold widget refresh specifications (objects )
var ajaxidx = 0;
var ajaxmutex = false;
var ajaxcntr = 0;

// Add a widget refresh object to the array list
function register_ajax(ws) {
  ajaxspecs.push(ws);
}
// ---------------------------------------------------------------------------------------------------

events.push(function() {
    // Initialize menu expansion
    $('.nav-main > li > a').on('click', function(e) {
        var $this = $(this);
        var $parent = $this.parent();
        var $submenu = $parent.find('> ul');
        
        if ($submenu.length) {
            e.preventDefault();
            
            // Close other open menus
            $('.nav-main > li').not($parent).removeClass('active');
            $('.nav-main > li > ul').not($submenu).slideUp(200);
            
            // Toggle current menu
            $parent.toggleClass('active');
            $submenu.slideToggle(200);
        }
    });

    // Initialize widget tracking
    var widgetCounter = {};
    var widgetSequence = [];

    // Initialize existing widgets
    $('.container .col-md-<?=$columnWidth?>').each(function(idx, col) {
        $('.panel', col).each(function(idx, widget) {
            var widget_basename = widget.id.split('-')[1];
            var widget_counter = parseInt(widget.id.split('-')[2]);
            
            if (widget_basename && !isNaN(widget_counter)) {
                if (!widgetCounter[widget_basename]) {
                    widgetCounter[widget_basename] = [];
                }
                widgetCounter[widget_basename].push(widget_counter);
                
                // Add to sequence
                widgetSequence.push({
                    basename: widget_basename,
                    counter: widget_counter,
                    column: col.id.split('-')[1],
                    display: $('.panel-body', widget).hasClass('in') ? 'open' : 'close'
                });
            }
        });
    });

    // Make panels destroyable
    $('.container .panel-heading a[data-toggle="close"]').each(function (idx, el) {
        $(el).on('click', function(e) {
            var widget = $(el).parents('.panel');
            var widget_basename = widget.attr('id').split('-')[1];
            var widget_counter = parseInt(widget.attr('id').split('-')[2]);
            
            // Remove from counter tracking
            if (widgetCounter[widget_basename]) {
                widgetCounter[widget_basename] = widgetCounter[widget_basename].filter(c => c !== widget_counter);
            }
            
            widget.remove();
            updateWidgets();
            $('[name=widgetForm]').submit();
        });
    });

    // Make panels sortable
    $('.container .col-md-<?=$columnWidth?>').sortable({
        handle: '.panel-heading',
        cursor: 'grabbing',
        connectWith: '.container .col-md-<?=$columnWidth?>',
        update: function(event, ui){
            dirty = true;
            $('#btnstore').removeClass('invisible');
            updateWidgets();
        }
    });

    // Widget addition handler
    $('[id^=btnadd-]').click(function(event) {
        event.preventDefault();
        var widgetName = this.id.replace('btnadd-', '');
        
        // Update widget counter tracking
        if (!widgetCounter[widgetName]) {
            widgetCounter[widgetName] = [];
        }
        
        updateWidgets(widgetName);
        $('[name=widgetForm]').submit();
    });

    // Initialize widget counter on page load
    $('.container .col-md-<?=$columnWidth?>').each(function(idx, col) {
        $('.panel', col).each(function(idx, widget) {
            var widgetName = widget.id.split('-')[1];
            if (!widgetCounter[widgetName]) {
                widgetCounter[widgetName] = 0;
            }
            widgetCounter[widgetName]++;
        });
    });

    $('#btnstore').click(function() {
        updateWidgets();
        dirty = false;
        $(this).addClass('invisible');
        $('[name=widgetForm]').submit();
    });

    // provide a warning message if the user tries to change page before saving
    $(window).bind('beforeunload', function(){
        if (dirty) {
            return ("<?=gettext('One or more widgets have been moved but have not yet been saved')?>");
        } else {
            return undefined;
        }
    });

    // Show the fa-save icon in the breadcrumb bar if the user opens or closes a panel (In case he/she wants to save the new state)
    // (Sometimes this will cause us to see the icon when we don't need it, but better that than the other way round)
    $('.panel').on('hidden.bs.collapse shown.bs.collapse', function (e) {
        if (e.currentTarget.id != 'widget-available') {
            $('#btnstore').removeClass("invisible");
        }
    });

    // --------------------- Centralized widget refresh system ------------------------------
    ajaxtimeout = false;

    function make_ajax_call(wd) {
        ajaxmutex = true;

        $.ajax({
            type: 'POST',
            url: wd.url,
            dataType: 'html',
            data: wd.parms,

            success: function(data){
                if (data.length > 0 ) {
                    // If the session has timed out, display a pop-up
                    if (data.indexOf("SESSION_TIMEOUT") === -1) {
                        wd.callback(data);
                    } else {
                        if (ajaxtimeout === false) {
                            ajaxtimeout = true;
                            alert("<?=$timeoutmessage?>");
                        }
                    }
                }

                ajaxmutex = false;
            },

            error: function(e){
    //                alert("Error: " + e);
                ajaxmutex = false;
            }
        });
    }

    // Loop through each AJAX widget refresh object, make the AJAX call and pass the
    // results back to the widget's callback function
    function executewidget() {
        if (ajaxspecs.length > 0) {
            var freq = ajaxspecs[ajaxidx].freq;	// widget can specify it should be called freq times around the loop

            if (!ajaxmutex) {
                if (((ajaxcntr % freq) === 0) && (typeof ajaxspecs[ajaxidx].callback === "function" )) {
                    make_ajax_call(ajaxspecs[ajaxidx]);
                }

                if (++ajaxidx >= ajaxspecs.length) {
                    ajaxidx = 0;

                    if (++ajaxcntr >= 4096) {
                        ajaxcntr = 0;
                    }
                }
            }

            setTimeout(function() { executewidget(); }, 1000);
        }
    }

    // Kick it off
    executewidget();

    //----------------------------------------------------------------------------------------------------
});
//]]>
</script>

<?php
//build list of javascript include files
foreach (glob('widgets/javascript/*.js') as $file) {
	$mtime = filemtime("/usr/local/www/{$file}");
	echo '<script src="'.$file.'?v='.$mtime.'"></script>';
}

include("foot.inc");
