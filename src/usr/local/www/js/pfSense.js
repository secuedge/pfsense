/*
 * pfSense.js
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

/*
 * This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	// Initialize Select2 for multi-select elements
	$('select[multiple]').select2({
		theme: 'bootstrap',
		width: '100%',
		placeholder: 'Select options...',
		allowClear: true
	});

	// Initialize Select2 for single-select elements
	$('select:not([multiple])').select2({
		theme: 'bootstrap',
		width: '100%',
		minimumResultsForSearch: 10
	});

	// Handle multi-select elements
	$('select[multiple]').each(function() {
		var select = $(this);
		var container = $('<div class="checkbox-list"></div>');
		var name = select.attr('name');
		
		// Hide the original select
		select.hide();
		
		// Create checkboxes for each option
		select.find('option').each(function() {
			var option = $(this);
			var value = option.val();
			var label = option.text();
			var checked = option.prop('selected') ? 'checked' : '';
			
			var checkbox = $('<div class="checkbox">' +
				'<label>' +
				'<input type="checkbox" name="' + name + '[]" value="' + value + '" ' + checked + '> ' +
				label +
				'</label>' +
				'</div>');
			
			container.append(checkbox);
		});
		
		// Insert the checkboxes after the select
		select.after(container);
		
		// Add "Select All" checkbox
		var selectAll = $('<div class="checkbox">' +
			'<label>' +
			'<input type="checkbox" class="select-all"> Select All' +
			'</label>' +
			'</div>');
		
		container.prepend(selectAll);
		
		// Handle "Select All" functionality
		selectAll.find('input').on('change', function() {
			var checked = $(this).prop('checked');
			container.find('input[type="checkbox"]:not(.select-all)').prop('checked', checked);
			updateSelectValues(select, container);
		});
		
		// Update select values when checkboxes change
		container.find('input[type="checkbox"]:not(.select-all)').on('change', function() {
			updateSelectValues(select, container);
			updateSelectAllState(container);
		});
	});

	// Function to update the hidden select values
	function updateSelectValues(select, container) {
		var values = [];
		container.find('input[type="checkbox"]:not(.select-all):checked').each(function() {
			values.push($(this).val());
		});
		select.val(values);
	}

	// Function to update the "Select All" checkbox state
	function updateSelectAllState(container) {
		var allChecked = container.find('input[type="checkbox"]:not(.select-all):checked').length === 
						container.find('input[type="checkbox"]:not(.select-all)').length;
		container.find('.select-all').prop('checked', allChecked);
	}

	// Attach collapsable behaviour to select options
	(function()
	{
		var selects = $('select[data-toggle="collapse"]');

		selects.on('change', function(){
			var options = $(this).find('option');
			var selectedValue = $(this).find(':selected').val();

			options.each(function(){
				if ($(this).val() == selectedValue)
					return;

				targets = $('.toggle-'+ $(this).val() +'.in:not(.toggle-'+ selectedValue +')');

				// Hide related collapsables which are visible (.in)
				targets.collapse('hide');

				// Disable all invisible inputs
				targets.find(':input').prop('disabled', true);
			});

			$('.toggle-' + selectedValue).collapse('show').find(':input').prop('disabled', false);
		});

		// Trigger change to open currently selected item
		selects.trigger('change');
	})();


	// Add +/- buttons to certain Groups; to allow adding multiple entries
	// This time making the buttons col-2 wide so they can fit on the same line as the
	// rest of the group (providing the total width of the group is col-8 or less)
	(function()
	{
		var groups = $('div.form-group.user-duplication-horiz');
		var controlsContainer = $('<div class="col-sm-2"></div>');
		var plus = $('<a class="btn btn-sm btn-success"><i class="fa fa-plus icon-embed-btn"></i>Add</a>');
		var minus = $('<a class="btn btn-sm btn-warning"><i class="fa fa-trash icon-embed-btn"></i>Delete</a>');

		minus.on('click', function(){
			$(this).parents('div.form-group').remove();
		});

		plus.on('click', function(){
			var group = $(this).parents('div.form-group');

			var clone = group.clone(true);
			clone.find('*').val('');
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			var controlsClone = controlsContainer.clone(true).appendTo(group);
			minus.clone(true).appendTo(controlsClone);

			if (group == group.parentNode.lastElementChild)
				plus.clone(true).appendTo(controlsClone);
		});
	})();

	// Add +/- buttons to certain Groups; to allow adding multiple entries
	(function()
	{
		var groups = $('div.form-group.user-duplication');
		var controlsContainer = $('<div class="col-sm-10 col-sm-offset-2 controls"></div>');
		var plus = $('<a class="btn btn-xs btn-success"><i class="fa fa-plus icon-embed-btn"></i>Add</a>');
		var minus = $('<a class="btn btn-xs btn-warning"><i class="fa fa-trash icon-embed-btn"></i>Delete</a>');

		minus.on('click', function(){
			$(this).parents('div.form-group').remove();
		});

		plus.on('click', function(){
			var group = $(this).parents('div.form-group');

			var clone = group.clone(true);
			clone.find('*').removeAttr('value');
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			var controlsClone = controlsContainer.clone(true).appendTo(group);
			minus.clone(true).appendTo(controlsClone);

			if (group == group.parentNode.lastElementChild)
				plus.clone(true).appendTo(controlsClone);
		});
	})();

	// Add +/- buttons to certain Groups; to allow adding multiple entries
	(function()
	{
		var groups = $('div.form-listitem.user-duplication');
		var fg = $('<div class="form-group"></div>');
		var controlsContainer = $('<div class="col-sm-10 col-sm-offset-2 controls"></div>');
		var plus = $('<a class="btn btn-xs btn-success"><i class="fa fa-plus icon-embed-btn"></i>Add</a>');
		var minus = $('<a class="btn btn-xs btn-warning"><i class="fa fa-trash icon-embed-btn"></i>Delete</a>');

		minus.on('click', function(){
			var groups = $('div.form-listitem.user-duplication');
			if (groups.length > 1) {
				$(this).parents('div.form-listitem').remove();
			}
		});

		plus.on('click', function(){
			var group = $(this).parents('div.form-listitem');
			var clone = group.clone(true);
			bump_input_id(clone);
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			var fgClone = fg.clone(true).appendTo(group);
			var controlsClone = controlsContainer.clone(true).appendTo(fgClone);
			minus.clone(true).appendTo(controlsClone);
			plus.clone(true).appendTo(controlsClone);
		});
	})();

	// Automatically change IpAddress mask selectors to 128/32 options for IPv6/IPv4 addresses
	$('span.pfIpMask + select').each(function (idx, select){
		var input = $(select).prevAll('input[type=text]');

		input.on('change', function(e){
			var isV6 = (input.val().indexOf(':') != -1), min = 0, max = 128;

			if (!isV6)
				max = 32;

			if (input.val() == "") {
				return;
			}

			var attr = $(select).attr('disabled');

			// Don't do anything if the mask selector is disabled
			if (typeof attr === typeof undefined || attr === false) {
				// Eat all of the options with a value greater than max. We don't want them to be available
				while (select.options[0].value > max)
					select.remove(0);

				if (select.options.length < max) {
					for (var i=select.options.length; i<=max; i++)
						select.options.add(new Option(i, i), 0);

					if (isV6) {
						// Make sure index 0 is selected otherwise it will stay in "32" for V6
						select.options.selectedIndex = "0";
					}
				}
			}
		});

		// Fire immediately
		input.change();
	});

	// Add confirm to all btn-danger buttons and fa-trash icons
	// Use element title in the confirmation message, or if not available
	// the element value
	$('.btn-danger, .fa-trash').on('click', function(e){
		if (!($(this).hasClass('no-confirm')) && !($(this).hasClass('icon-embed-btn'))) {
			// Anchors using the automatic get2post system (pfSenseHelpers.js) perform the confirmation dialog
			// in those functions
			var attr = $(this).attr('usepost');
			if (typeof attr === typeof undefined || attr === false) {
				var msg = $.trim(this.textContent).toLowerCase();

				if (!msg)
					var msg = $.trim(this.value).toLowerCase();

				var q = 'Are you sure you wish to '+ msg +'?';

				if ($(this).attr('title') != undefined)
					q = 'Are you sure you wish to '+ $(this).attr('title').toLowerCase() + '?';

				if (!confirm(q)) {
					e.preventDefault();
					e.stopPropagation();	// Don't leave ancestor(s) selected.
				}
			}
		}
	});

	// Add toggle-all when there are multiple checkboxes
	$('.control-label + .checkbox.multi').each(function() {
		var a = $('<a name="btntoggleall" class="btn btn-xs btn-info"><i class="fa fa-check-square-o icon-embed-btn"></i>Toggle All</a>');

		a.on('click', function() {
			var wrap = $(this).parents('.form-group').find('.checkbox.multi'),
				all = wrap.find('input[type=checkbox]'),
				checked = wrap.find('input[type=checkbox]:checked');

			all.prop('checked', (all.length != checked.length));
		});

		if ( ! $(this).parent().hasClass("notoggleall")) {
			a.appendTo($(this));
		}
	});

	// The need to NOT hide the advanced options if the elements therein are not set to the system
	// default values makes it better to handle advanced option hiding in each PHP file so this is being
	// disabled for now by changing the class name it acts on to "auto-advanced"

	// Hide advanced inputs by default
	if ($('.auto-advanced').length > 0)
	{
		var advButt = $('<a id="toggle-advanced" class="btn btn-default">toggle advanced options</a>');
		advButt.on('click', function() {
			$('.advanced').parents('.form-group').collapse('toggle');
		});

		advButt.insertAfter($('#save'));

		$('.auto-advanced').parents('.form-group').collapse({toggle: true});
	}

	var originalLeave = $.fn.popover.Constructor.prototype.leave;
	$.fn.popover.Constructor.prototype.leave = function(obj){
	  var self = obj instanceof this.constructor ?
	    obj : $(obj.currentTarget)[this.type](this.getDelegateOptions()).data('bs.' + this.type)
	  var container, timeout;

	  originalLeave.call(this, obj);

	  if (self.$tip && self.$tip.length) {
	    container = self.$tip;
	    timeout = self.timeout;
	    container.one('mouseenter', function(){
	      //We entered the actual popover - call off the dogs
	      clearTimeout(timeout);
	      //Let's monitor popover content instead
	      container.one('mouseleave', function(){
	        $.fn.popover.Constructor.prototype.leave.call(self, self);
	      });
	    })
	  }
	};

	// Bootstrap 3.4.1 sanitizes the contents of popovers even when data-html is specified
	// Add table tags to the list of elements permitted by the sanitizer
	var defaultWhiteList = $.fn.tooltip.Constructor.DEFAULTS.whiteList

	defaultWhiteList.table = []
	defaultWhiteList.thead = []
	defaultWhiteList.tr = ["class"]
	defaultWhiteList.th = ["style"]
	defaultWhiteList.tbody = []
	defaultWhiteList.td = ["style"]

	// Enable popovers globally
	$('[data-toggle="popover"]').popover({ delay: {show: 50, hide: 400} });

	// Force correct initial state for toggleable checkboxes
	$('input[type=checkbox][data-toggle="collapse"]:not(:checked)').each(function() {
		$( $(this).data('target') ).addClass('collapse');
	});

	$('input[type=checkbox][data-toggle="disable"]:not(:checked)').each(function() {
		$( $(this).data('target') ).prop('disabled', true);
	});

	$('.table-rowdblclickedit>tbody>tr').dblclick(function () {
		$(this).find(".fa-pencil")[0].click();
	});

	// Focus first input
	$(':input:enabled:visible:first').focus();

	$(".resizable").each(function() {
		$(this).css('height', 80).resizable({minHeight: 80, minWidth: 200}).parent().css('padding-bottom', 0);
		$(this).css('height', 78);
	});

	// Run in-page defined events
	while (func = window.events.shift())
		func();
});

// Implement data-toggle=disable
// Source: https://github.com/visionappscz/bootstrap-ui/blob/master/src/js/disable.js
;(function($, window, document) {
	'use strict';

	var Disable = function($element) {
		this.$element = $element;
	};

	Disable.prototype.toggle = function() {
		this.$element.prop('disabled', !this.$element.prop('disabled'));
	};

	function Plugin(options) {
		$(document).trigger('toggle.sui.disable');

		this.each(function() {
			var $this = $(this);
			var data = $this.data('sui.disable');

			if (!data) {
				$this.data('sui.disable', (data = new Disable($this)));
			}

			if (options === 'toggle') {
				data.toggle();
			}
		});

		$(document).trigger('toggled.sui.disable');

		return this;
	}

	var old = $.fn.disable;

	$.fn.disable = Plugin;
	$.fn.disable.Constructor = Disable;

	$.fn.disable.noConflict = function() {
		$.fn.disable = old;
		return this;
	};

	(function(Plugin, $, window) {
		$(window).on("load", function() {
			var $controls = $('[data-toggle=disable]');

			$controls.each(function() {
				var $this = $(this);
				var eventType = $this.data('disable-event');
				if (!eventType) {
					eventType = 'change';
				}
				$this.on(eventType + '.sui.disable.data-api', function() {
					Plugin.call($($this.data('target')), 'toggle');
				});
			});
		});
	}(Plugin, $, window, document));
}(jQuery, window, document));

// Menu Handling
document.addEventListener('DOMContentLoaded', function() {
	// Initialize menu state
	initializeMenuState();
	
	// Handle submenu toggles
	initializeSubmenuToggles();
	
	// Handle mobile menu
	initializeMobileMenu();
	
	// Initialize notifications
	initializeNotifications();
});

function initializeMenuState() {
	const currentPath = window.location.pathname;
	
	// Set active states based on current URL
	document.querySelectorAll('.nav-link').forEach(link => {
		const href = link.getAttribute('href');
		if (href && currentPath === href) {
			link.classList.add('active');
			
			// If in submenu, expand parent
			const submenu = link.closest('.submenu');
			if (submenu) {
				submenu.classList.add('show');
				const parentLink = document.querySelector(`[data-bs-target="#${submenu.id}"]`);
				if (parentLink) {
					parentLink.classList.add('active');
					parentLink.setAttribute('aria-expanded', 'true');
					const icon = parentLink.querySelector('.submenu-icon');
					if (icon) icon.classList.add('rotate-180');
				}
			}
		}
	});
}

function initializeSubmenuToggles() {
	document.querySelectorAll('.nav-link.has-submenu').forEach(toggle => {
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			const targetId = this.getAttribute('href');
			const submenu = document.querySelector(targetId);
			const icon = this.querySelector('.submenu-icon');
			
			// Close other submenus
			document.querySelectorAll('.submenu.show').forEach(menu => {
				if (menu !== submenu) {
					menu.classList.remove('show');
					const otherToggle = document.querySelector(`[href="#${menu.id}"]`);
					if (otherToggle) {
						otherToggle.classList.remove('active');
						otherToggle.setAttribute('aria-expanded', 'false');
						const otherIcon = otherToggle.querySelector('.submenu-icon');
						if (otherIcon) otherIcon.classList.remove('rotate-180');
					}
				}
			});
			
			// Toggle current submenu
			if (submenu) {
				submenu.classList.toggle('show');
				this.classList.toggle('active');
				this.setAttribute('aria-expanded', submenu.classList.contains('show'));
				if (icon) icon.classList.toggle('rotate-180');
			}
		});
	});
}

function initializeMobileMenu() {
	const sidebarToggle = document.querySelector('.navbar-toggler');
	const sidebar = document.querySelector('.sidebar');
	const content = document.getElementById('content');
	
	if (sidebarToggle && sidebar && content) {
		// Toggle menu on button click
		sidebarToggle.addEventListener('click', function(e) {
			e.preventDefault();
			sidebar.classList.toggle('active');
			content.classList.toggle('active');
		});
		
		// Close menu when clicking outside
		document.addEventListener('click', function(e) {
			if (window.innerWidth <= 768) {
				if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
					sidebar.classList.remove('active');
					content.classList.remove('active');
				}
			}
		});
		
		// Handle window resize
		let resizeTimer;
		window.addEventListener('resize', function() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function() {
				if (window.innerWidth > 768) {
					sidebar.classList.remove('active');
					content.classList.remove('active');
				}
			}, 250);
		});
	}
}

function initializeNotifications() {
	const badges = document.querySelectorAll('.badge[data-count]');
	badges.forEach(badge => {
		const count = parseInt(badge.getAttribute('data-count'));
		if (count > 0) {
			badge.textContent = count > 99 ? '99+' : count;
			badge.style.display = 'inline';
		} else {
			badge.style.display = 'none';
		}
	});
}

// Utility Functions
function getUrlParameter(name) {
	name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
	const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
	const results = regex.exec(location.search);
	return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Initialize Bootstrap components if available
if (typeof bootstrap !== 'undefined') {
	// Initialize tooltips
	const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
	tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
	
	// Initialize popovers
	const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
	popovers.forEach(popover => new bootstrap.Popover(popover));
}

// Export utilities
window.pfSense = {
	getUrlParameter,
	initializeMenuState,
	initializeSubmenuToggles,
	initializeMobileMenu,
	initializeNotifications
};
