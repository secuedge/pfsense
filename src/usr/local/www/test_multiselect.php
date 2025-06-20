<?php
require_once("guiconfig.inc");
require_once("functions.inc");

$pgtitle = array(gettext("Test"), gettext("Enhanced Multiselect"));
include("head.inc");

// Test data with more options
$test_values = array(
	'opt1' => 'Network Interface 1',
	'opt2' => 'Network Interface 2', 
	'opt3' => 'Network Interface 3',
	'opt4' => 'Network Interface 4',
	'opt5' => 'Network Interface 5',
	'opt6' => 'Virtual Interface 1',
	'opt7' => 'Virtual Interface 2',
	'opt8' => 'VPN Interface',
	'opt9' => 'Bridge Interface',
	'opt10' => 'VLAN Interface'
);

// Handle form submission
if ($_POST) {
	print_info_box("Selected values: " . print_r($_POST['test_multiselect'], true), 'success');
}

$form = new Form;

$section = new Form_Section('Enhanced Multiselect Test');

$section->addInput(new Form_Select(
	'test_multiselect',
	'Select Interfaces',
	isset($_POST['test_multiselect']) ? $_POST['test_multiselect'] : array('opt2', 'opt4'),
	$test_values,
	true
))->setHelp('This enhanced multiselect provides:<br>
• Search functionality - type to filter options<br>
• Clear all button - remove all selections at once<br>
• Tag-style selected items with easy removal<br>
• Professional appearance with smooth animations<br>
• Keyboard navigation support<br>
• Mobile-friendly interface');

$section->addInput(new Form_Select(
	'test_single',
	'Single Select Example',
	isset($_POST['test_single']) ? $_POST['test_single'] : 'opt1',
	$test_values,
	false
))->setHelp('Single select dropdowns are also enhanced for consistency.');

$form->add($section);

print $form;

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Additional customization example
	$('#test_multiselect').on('select2:select', function (e) {
		console.log('Option selected:', e.params.data);
	});
	
	$('#test_multiselect').on('select2:unselect', function (e) {
		console.log('Option unselected:', e.params.data);
	});
});
//]]>
</script>

<?php include("foot.inc"); ?> 