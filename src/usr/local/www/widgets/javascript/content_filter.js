$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Handle bulk actions
    $('#bulk-action').change(function() {
        var action = $(this).val();
        if (action) {
            var selected = $('input[name="selected[]"]:checked');
            if (selected.length > 0) {
                if (confirm('Are you sure you want to ' + action + ' the selected items?')) {
                    var ids = selected.map(function() {
                        return $(this).val();
                    }).get();

                    $.post('content_filter.php', {
                        action: action,
                        ids: ids
                    }, function(response) {
                        location.reload();
                    });
                }
            } else {
                alert('Please select at least one item');
            }
            $(this).val('');
        }
    });

    // Handle quick add form
    $('#quick-add-form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.find('input[name="url"]').val();
        var type = form.find('select[name="type"]').val();

        $.post('content_filter.php', {
            action: 'add_block',
            url: url,
            type: type
        }, function(response) {
            location.reload();
        });
    });

    // Handle schedule changes
    $('.schedule-toggle').change(function() {
        var id = $(this).data('id');
        var status = $(this).prop('checked') ? 1 : 0;

        $.post('content_filter.php', {
            action: 'toggle_schedule',
            id: id,
            status: status
        }, function(response) {
            location.reload();
        });
    });

    // Auto-refresh widget
    setInterval(function() {
        $.get('widgets/content_filter.widget.php', function(data) {
            $('#content-filter-widget').html(data);
        });
    }, 30000);

    // Initialize data tables
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            "pageLength": 25,
            "order": [[0, "desc"]],
            "language": {
                "emptyTable": "No blocks configured"
            }
        });
    }

    // Handle category selection
    $('#category-select').change(function() {
        var category = $(this).val();
        if (category) {
            $.get('content_filter.php', {
                action: 'get_category_sites',
                category: category
            }, function(response) {
                var sites = JSON.parse(response);
                var list = $('#category-sites');
                list.empty();
                sites.forEach(function(site) {
                    list.append('<div class="checkbox"><label><input type="checkbox" name="sites[]" value="' + site + '"> ' + site + '</label></div>');
                });
            });
        }
    });

    // Handle bulk import
    $('#import-form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var sites = form.find('input[name="sites[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (sites.length > 0) {
            $.post('content_filter.php', {
                action: 'bulk_import',
                sites: sites
            }, function(response) {
                location.reload();
            });
        } else {
            alert('Please select at least one site to import');
        }
    });
}); 