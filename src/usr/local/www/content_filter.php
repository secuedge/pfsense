<?php
/*
 * content_filter.php
 *
 * part of SecuEdge
 * Copyright (c) 2024 SecuEdge
 * All rights reserved.
 */

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/content_filter.inc");

// Remove debug code
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// try {
//     $debug_db = new SQLite3('/var/db/content_filter.db');
//     $tables = $debug_db->query("SELECT name FROM sqlite_master WHERE type='table'");
//     echo "<pre>Available tables:\n";
//     while ($table = $tables->fetchArray()) {
//         echo $table['name'] . "\n";
//     }
//     echo "</pre>";
// } catch (Exception $e) {
//     echo "<pre>Database Error: " . $e->getMessage() . "</pre>";
// }

$pgtitle = array(gettext("Services"), gettext("Content Filtering"));
$pglinks = array("", "@self");

// Initialize content filter manager
try {
    $filterManager = new ContentFilterManager();
    $error_message = null;
} catch (Exception $e) {
    $error_message = "Failed to initialize Content Filter: " . $e->getMessage();
}

if ($error_message) {
    print_info_box($error_message, 'danger');
} else {
    // Add CSRF token generation at the top
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            print_info_box('Invalid CSRF token. Please refresh the page.', 'danger');
        } else {
            $action = isset($_POST['action']) ? $_POST['action'] : '';
            $success = false;
            $message = '';
            
            switch ($action) {
                case 'add_group':
                    if (isset($_POST['name']) && !empty($_POST['name'])) {
                        $success = $filterManager->addGroup(
                            $_POST['name'],
                            isset($_POST['description']) ? $_POST['description'] : ''
                        );
                        $message = $success ? 'Group added successfully' : 'Failed to add group';
                    }
                    break;
                
                case 'add_block':
                    if (isset($_POST['url']) && !empty($_POST['url'])) {
                        $blockData = array(
                            'url' => $_POST['url'],
                            'type' => isset($_POST['type']) ? $_POST['type'] : 'domain',
                            'group_name' => isset($_POST['group_name']) ? $_POST['group_name'] : null,
                            'category_id' => isset($_POST['category_id']) ? intval($_POST['category_id']) : null,
                            'schedule_id' => isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : null,
                            'description' => isset($_POST['description']) ? $_POST['description'] : '',
                            'is_active' => 1
                        );
                        $success = $filterManager->addBlock($blockData);
                        $message = $success ? 'Block rule added successfully' : 'Failed to add block rule';
                    }
                    break;
                
                case 'add_whitelist':
                    if (isset($_POST['url']) && !empty($_POST['url'])) {
                        $success = $filterManager->addToWhitelist(
                            isset($_POST['group_name']) ? $_POST['group_name'] : null,
                            $_POST['url'],
                            isset($_POST['description']) ? $_POST['description'] : ''
                        );
                        $message = $success ? 'Whitelist entry added successfully' : 'Failed to add whitelist entry';
                    }
                    break;
                
                case 'remove_block':
                    if (isset($_POST['id'])) {
                        $success = $filterManager->deleteBlock(intval($_POST['id']));
                        $message = $success ? 'Block rule removed successfully' : 'Failed to remove block rule';
                    }
                    break;
                
                case 'toggle_status':
                    if (isset($_POST['id'])) {
                        $success = $filterManager->toggleStatus(intval($_POST['id']));
                        $message = $success ? 'Status updated successfully' : 'Failed to update status';
                    }
                    break;
                
                case 'toggle_blacklist_category':
                    if (isset($_POST['category_id']) && isset($_POST['enabled'])) {
                        $category_id = $_POST['category_id'];
                        $enabled = intval($_POST['enabled']);
                        $category_type = isset($_POST['category_type']) ? $_POST['category_type'] : 'black';
                        
                        // Here you would implement the logic to enable/disable the blacklist category
                        // For now, we'll just return success
                        $success = true;
                        $message = $enabled ? 'Category enabled successfully' : 'Category disabled successfully';
                        
                        // You could also load the blacklist data and add it to the blocklist
                        if ($enabled) {
                            $domains = $filterManager->loadBlacklistData($category_id, 'domains');
                            $urls = $filterManager->loadBlacklistData($category_id, 'urls');
                            
                            // Add domains to blocklist
                            foreach ($domains as $domain) {
                                $blockData = array(
                                    'url' => trim($domain),
                                    'type' => 'domain',
                                    'group_name' => isset($_POST['group_name']) ? $_POST['group_name'] : null,
                                    'category_id' => null, // You could create a category for this
                                    'description' => 'Auto-added from blacklist: ' . $category_id,
                                    'is_active' => 1
                                );
                                $filterManager->addBlock($blockData);
                            }
                            
                            // Add URLs to blocklist
                            foreach ($urls as $url) {
                                $blockData = array(
                                    'url' => trim($url),
                                    'type' => 'url',
                                    'group_name' => isset($_POST['group_name']) ? $_POST['group_name'] : null,
                                    'category_id' => null,
                                    'description' => 'Auto-added from blacklist: ' . $category_id,
                                    'is_active' => 1
                                );
                                $filterManager->addBlock($blockData);
                            }
                        }
                    }
                    break;
                
                case 'load_blacklist_data':
                    if (isset($_GET['category_id']) && isset($_GET['data_type'])) {
                        $category_id = $_GET['category_id'];
                        $data_type = $_GET['data_type'];
                        
                        $data = $filterManager->loadBlacklistData($category_id, $data_type);
                        
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'data' => $data
                        ]);
                        exit;
                    }
                    break;
                
                case 'reset_database':
                    if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
                        try {
                            $filterManager->resetDatabase();
                            $message = 'Database reset successfully. All content filtering data has been cleared.';
                            $success = true;
                        } catch (Exception $e) {
                            $message = 'Failed to reset database: ' . $e->getMessage();
                            $success = false;
                        }
                    }
                    break;
            }
            
            if ($message) {
                $class = $success ? 'success' : 'danger';
                print_info_box($message, $class);
            }
        }
    }

    // Get data with error handling
    try {
        $groups = $filterManager->getGroups();
        $categories = $filterManager->getCategories();
        $blacklist_categories = $filterManager->getBlacklistCategories();
        $schedules = $filterManager->getSchedules();
        $current_group = isset($_GET['group']) ? $_GET['group'] : null;
        $blocklist = $filterManager->getBlocklist($current_group);
        $whitelist = $filterManager->getWhitelist($current_group);
    } catch (Exception $e) {
        print_info_box("Error loading data: " . $e->getMessage(), 'danger');
        $groups = array();
        $categories = array();
        $blacklist_categories = array();
        $schedules = array();
        $blocklist = array();
        $whitelist = array();
    }
}

include("head.inc");
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-shield-alt"></i> <?=gettext("Content Filtering")?></h1>
        <div class="group-selector">
            <select class="form-control select2" id="group-select" onchange="window.location.href='?group=' + this.value">
                <option value=""><?=gettext("Select a Group")?></option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?=htmlspecialchars($group['name'])?>" <?=$current_group === $group['name'] ? 'selected' : ''?>>
                        <?=htmlspecialchars($group['name'])?><?=$group['description'] ? ' - ' . htmlspecialchars($group['description']) : ''?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="system_groupmanager.php?act=new" class="btn btn-primary btn-create" target="_blank">
                <i class="fas fa-plus"></i> <?=gettext("New Group")?>
            </a>
        </div>
    </div>

    <?php if (empty($groups)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong><?=gettext("No Groups Available")?></strong><br>
        <?=gettext("Content filtering requires user groups to be configured. Please create groups in the")?> 
        <a href="system_groupmanager.php" target="_blank"><?=gettext("System Group Manager")?></a> 
        <?=gettext("first, then return here to configure content filtering rules.")?>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message) && strpos($error_message, 'database') !== false): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <strong><?=gettext("Database Error")?></strong><br>
        <?=gettext("There appears to be a database issue with the content filtering system. If the problem persists, you can reset the database.")?>
        <br><br>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="reset_database">
            <input type="hidden" name="confirm" value="yes">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?=gettext('This will delete all content filtering data. Are you sure?')?>')">
                <i class="fas fa-database"></i> <?=gettext("Reset Database")?>
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($current_group): ?>
    <div class="category-section mb-4 p-4 bg-light rounded shadow-sm">
        <h4 class="mb-3 fw-bold border-bottom pb-2"><i class="fas fa-shield-alt me-2"></i><?=gettext("Content Filtering Categories")?></h4>
        <div class="row">
            <?php 
            // Organize blacklist categories into logical groups
            $category_groups = [
                'security' => [
                    'title' => 'Security & Threats',
                    'icon' => 'fa-shield-virus',
                    'color' => 'danger',
                    'description' => 'Block malware, phishing, and security threats',
                    'categories' => ['malware', 'phishing', 'hacking', 'ddos', 'cryptojacking', 'stalkerware', 'doh', 'vpn', 'residential-proxies', 'dynamic-dns']
                ],
                'adult' => [
                    'title' => 'Adult Content',
                    'icon' => 'fa-exclamation-triangle',
                    'color' => 'warning',
                    'description' => 'Block adult and inappropriate content',
                    'categories' => ['adult', 'mixed_adult', 'lingerie', 'dating', 'sexual_education']
                ],
                'social' => [
                    'title' => 'Social & Communication',
                    'icon' => 'fa-users',
                    'color' => 'info',
                    'description' => 'Control access to social networking and communication platforms',
                    'categories' => ['social_networks', 'webmail', 'chat', 'forums', 'blog']
                ],
                'entertainment' => [
                    'title' => 'Entertainment & Media',
                    'icon' => 'fa-gamepad',
                    'color' => 'primary',
                    'description' => 'Manage access to entertainment and media sites',
                    'categories' => ['games', 'gambling', 'audio-video', 'radio', 'streaming', 'manga', 'celebrity', 'sports']
                ],
                'shopping' => [
                    'title' => 'Shopping & Commerce',
                    'icon' => 'fa-shopping-cart',
                    'color' => 'success',
                    'description' => 'Control access to shopping and commercial sites',
                    'categories' => ['shopping', 'filehosting', 'webhosting', 'bitcoin']
                ],
                'productivity' => [
                    'title' => 'Productivity & Work',
                    'icon' => 'fa-briefcase',
                    'color' => 'secondary',
                    'description' => 'Manage access to productivity and work-related sites',
                    'categories' => ['jobsearch', 'press', 'translation', 'update', 'cleaning', 'download']
                ],
                'restricted' => [
                    'title' => 'Restricted Content',
                    'icon' => 'fa-ban',
                    'color' => 'dark',
                    'description' => 'Block restricted and potentially harmful content',
                    'categories' => ['warez', 'drogue', 'dangerous_material', 'violence', 'agressif', 'sect', 'astrology', 'fakenews', 'tricheur', 'tricheur_pix']
                ],
                'bypass' => [
                    'title' => 'Bypass & Proxy',
                    'icon' => 'fa-unlink',
                    'color' => 'danger',
                    'description' => 'Block proxy and bypass attempts',
                    'categories' => ['redirector', 'strict_redirector', 'strong_redirector', 'shortener']
                ],
                'whitelist' => [
                    'title' => 'Whitelisted Sites',
                    'icon' => 'fa-check-circle',
                    'color' => 'success',
                    'description' => 'Sites that are explicitly allowed',
                    'categories' => ['liste_bu', 'liste_blanche', 'exceptions_liste_bu', 'child', 'educational_games', 'sexual_education', 'bank', 'jobsearch', 'translation', 'update', 'cleaning', 'download', 'examen_pix']
                ]
            ];
            
            foreach ($category_groups as $group_key => $group_info): 
                // Filter blacklist categories for this group
                $group_categories = array_filter($blacklist_categories, function($cat) use ($group_info) {
                    return in_array($cat['id'], $group_info['categories']);
                });
                
                if (empty($group_categories)) {
                    continue;
                }
            ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <div class="d-flex align-items-center mb-2">
                            <span data-toggle="tooltip" title="<?=$group_info['description']?>">
                                <i class="fas <?=$group_info['icon']?> text-<?=$group_info['color']?> me-2"></i>
                            </span>
                            <span class="fw-bold fs-6"><?=$group_info['title']?></span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="category-list">
                            <?php foreach ($group_categories as $category): ?>
                            <div class="category-item d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div class="category-info">
                                    <div class="category-name"><?=htmlspecialchars($category['name'])?></div>
                                    <?php if ($category['description']): ?>
                                    <div class="category-description text-muted small"><?=htmlspecialchars($category['description'])?></div>
                                    <?php endif; ?>
                                    <div class="category-stats text-muted small">
                                        <?php if ($category['domain_count'] > 0): ?>
                                            <span class="me-2"><i class="fas fa-globe"></i> <?=number_format($category['domain_count'])?> domains</span>
                                        <?php endif; ?>
                                        <?php if ($category['url_count'] > 0): ?>
                                            <span><i class="fas fa-link"></i> <?=number_format($category['url_count'])?> URLs</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="category-controls">
                                    <label class="modern-switch mb-0">
                                        <input type="checkbox" <?=$category['type'] === 'white' ? 'checked' : ''?> 
                                               data-category-id="<?=$category['id']?>" 
                                               data-category-type="<?=$category['type']?>">
                                        <span class="modern-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-ban"></i> <?=gettext("Custom Site Blocking")?></h2>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold border-bottom pb-2 mb-4"><i class="fas fa-ban me-2"></i><?=gettext("Custom Site Blocking")?></h4>
                    <form method="post" action="content_filter.php" class="custom-form">
                        <input type="hidden" name="action" value="add_block">
                        <input type="hidden" name="group_name" value="<?=$current_group?>">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                </div>
                                <input type="text" class="form-control" id="url" name="url" required 
                                       placeholder="Enter domain or URL to block">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                </div>
                                <select class="form-control" id="type" name="type">
                                    <option value="domain"><?=gettext("Domain Block")?></option>
                                    <option value="url"><?=gettext("URL Block")?></option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-block btn-primary">
                            <i class="fas fa-plus"></i> <?=gettext("Add Block")?>
                        </button>
                    </form>

                    <div class="form-group mb-3">
                        <label for="quick_add_site" class="form-label">Quick Add Popular Site</label>
                        <select class="form-select" id="quick_add_site">
                            <option value="">Select a site...</option>
                            <?php
                            $popular_sites = [
                                ['Facebook', 'facebook.com', 'fa-facebook', 'bg-facebook'],
                                ['Instagram', 'instagram.com', 'fa-instagram', 'bg-instagram'],
                                ['Twitter', 'twitter.com', 'fa-twitter', 'bg-twitter'],
                                ['YouTube', 'youtube.com', 'fa-youtube', 'bg-youtube'],
                                ['TikTok', 'tiktok.com', 'fa-music', 'bg-tiktok'],
                                ['LinkedIn', 'linkedin.com', 'fa-linkedin', 'bg-linkedin']
                            ];
                            foreach ($popular_sites as $site):
                            ?>
                            <option value="<?=$site[1]?>"><?=$site[0]?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary mt-2" id="quick_add_btn">Add</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-check-circle"></i> <?=gettext("Whitelist")?></h2>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold border-bottom pb-2 mb-4"><i class="fas fa-check-circle me-2"></i><?=gettext("Whitelist")?></h4>
                    <form method="post" action="content_filter.php" class="custom-form">
                        <input type="hidden" name="action" value="add_whitelist">
                        <input type="hidden" name="group_name" value="<?=$current_group?>">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                </div>
                                <input type="text" class="form-control" id="whitelist_url" name="url" required
                                       placeholder="Enter domain or URL to whitelist">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                </div>
                                <input type="text" class="form-control" id="description" name="description"
                                       placeholder="Enter a description (optional)">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-block btn-success">
                            <i class="fas fa-plus"></i> <?=gettext("Add to Whitelist")?>
                        </button>
                    </form>

                    <div class="whitelist-table">
                        <h3><?=gettext("Current Whitelist")?></h3>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th><?=gettext("URL/Domain")?></th>
                                        <th><?=gettext("Description")?></th>
                                        <th><?=gettext("Actions")?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($whitelist as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="url-cell">
                                                <i class="fas fa-globe"></i>
                                                <?=htmlspecialchars($item['url'])?>
                                            </div>
                                        </td>
                                        <td><?=htmlspecialchars($item['description'])?></td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-whitelist" 
                                                    data-id="<?=$item['id']?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?=gettext("Create New Group")?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?=gettext("Groups are managed through the System Group Manager. Please use the link above to create new groups.")?></p>
            </div>
            <div class="modal-footer">
                <a href="system_groupmanager.php?act=new" class="btn btn-primary" target="_blank">
                    <i class="fas fa-external-link-alt"></i> <?=gettext("Open Group Manager")?>
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=gettext("Close")?></button>
            </div>
        </div>
    </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');
body {
    font-family: 'Inter', Arial, sans-serif;
    background: #f4f6fa;
}
.content-wrapper {
    max-width: 1200px;
    margin: 32px auto;
    padding: 32px 0;
}
.dashboard-card, .category-section, .modern-card, .card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 2px 16px rgba(80,120,200,0.08);
    padding: 2rem 1.5rem 1.5rem 1.5rem;
    margin-bottom: 2rem;
    border: none;
    transition: box-shadow 0.2s;
}
.dashboard-card:hover, .category-section:hover, .modern-card:hover, .card:hover {
    box-shadow: 0 6px 32px rgba(80,120,200,0.13);
}
.section-header {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2563eb;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-header i {
    font-size: 1.3rem;
    color: #2563eb;
    background: #e0e7ff;
    border-radius: 50%;
    padding: 7px;
    margin-right: 8px;
}
.list-group-item {
    background: none;
    border: none;
    font-size: 1.08rem;
    padding: 0.7rem 0;
}
.modern-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}
.modern-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.modern-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: #e0e7ff;
    transition: .4s;
    border-radius: 34px;
}
.modern-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 4px;
    bottom: 4px;
    background: #fff;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 8px #2563eb22;
}
input:checked + .modern-slider {
    background: #2563eb;
}
input:checked + .modern-slider:before {
    transform: translateX(22px);
    box-shadow: 0 0 8px 2px #2563eb44;
}
.btn, .btn-primary, .btn-success {
    border-radius: 8px;
    font-size: 1.08rem;
    font-weight: 600;
    padding: 0.7rem 1.5rem;
    transition: background 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px #2563eb11;
    border: none;
}
.btn-primary {
    background: #2563eb;
    color: #fff;
}
.btn-primary:hover, .btn-success:hover {
    background: #1746a2;
    color: #fff;
}
.btn-success {
    background: #22c55e;
    color: #fff;
}
.table {
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    font-size: 1.05rem;
    box-shadow: 0 2px 8px #2563eb11;
}
.table th, .table td {
    font-size: 1.05rem;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid #f0f2f7;
}
.table th {
    color: #2563eb;
    font-weight: 600;
    background: #f4f6fa;
}
input.form-control, select.form-select {
    border-radius: 8px;
    font-size: 1.08rem;
    padding: 0.7rem 1rem;
    border: 1px solid #e0e7ff;
    background: #f9fafd;
}
input.form-control:focus, select.form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px #2563eb22;
}
.form-label {
    font-weight: 600;
    color: #222;
}
.accent { color: #2563eb; }
.category-item {
    background: none;
    border: none;
    font-size: 1.08rem;
    padding: 0.7rem 0;
}
.category-info {
    flex: 1;
    min-width: 0;
}
.category-name {
    font-weight: 500;
    color: #333;
    margin-bottom: 2px;
    font-size: 0.95rem;
}
.category-description {
    font-size: 0.8rem;
    line-height: 1.3;
    margin-bottom: 4px;
}
.category-stats {
    font-size: 0.75rem;
}
.category-stats span {
    display: inline-block;
    margin-right: 8px;
}
.category-controls {
    flex-shrink: 0;
    margin-left: 12px;
}
.category-list {
    max-height: 400px;
    overflow-y: auto;
}
.category-list::-webkit-scrollbar {
    width: 4px;
}
.category-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}
.category-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}
.category-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
.modern-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}
</style>

<script type="text/javascript">
$(document).ready(function() {
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap',
        placeholder: 'Select a Group'
    });

    // Quick add site buttons
    $('#quick_add_btn').click(function() {
        var site = $('#quick_add_site').val();
        var group = <?=$current_group?>;
        if (!site) return;
        $.post('content_filter.php', {
            action: 'add_block',
            group_name: group,
            url: site,
            type: 'domain',
            csrf_token: window.CSRF_TOKEN
        }, function(response) {
            showToast('Block added successfully', 'success');
            reloadBlocklist();
        }).fail(function(xhr) {
            showToast('Failed to add block: ' + xhr.responseText, 'danger');
        });
    });
    
    // Category toggle buttons
    $('.toggle-card').click(function() {
        var categoryId = $(this).data('category-id');
        var categoryName = $(this).data('category-name');
        
        $(this).toggleClass('active');
        $(this).find('input[type="checkbox"]').prop('checked', 
            !$(this).find('input[type="checkbox"]').prop('checked'));
        
        $.post('content_filter.php', {
            action: 'toggle_category',
            category_id: categoryId,
            csrf_token: window.CSRF_TOKEN
        }, function() {
            location.reload();
        }).always(function() {
            var btn = $(this);
            btn.prop('disabled', false);
        });
    });

    // Handle category toggles
    $('input[data-category-id]').change(function() {
        var categoryId = $(this).data('category-id');
        var categoryType = $(this).data('category-type');
        var isChecked = $(this).is(':checked');
        
        // Show loading state
        $(this).prop('disabled', true);
        
        $.post('content_filter.php', {
            action: 'toggle_blacklist_category',
            category_id: categoryId,
            category_type: categoryType,
            enabled: isChecked ? 1 : 0,
            csrf_token: window.CSRF_TOKEN
        }, function(response) {
            if (response.success) {
                showToast('Category updated successfully', 'success');
                // Optionally reload the page to show updated data
                // location.reload();
            } else {
                showToast('Failed to update category: ' + response.message, 'danger');
                // Revert the checkbox
                $(this).prop('checked', !isChecked);
            }
        }).fail(function(xhr) {
            showToast('Failed to update category', 'danger');
            // Revert the checkbox
            $(this).prop('checked', !isChecked);
        }).always(function() {
            $(this).prop('disabled', false);
        });
    });

    // Load blacklist data for a category
    function loadBlacklistData(categoryId, type) {
        $.get('content_filter.php', {
            action: 'load_blacklist_data',
            category_id: categoryId,
            data_type: type
        }, function(response) {
            if (response.success) {
                // Display the data in a modal or update the interface
                showBlacklistDataModal(categoryId, type, response.data);
            } else {
                showToast('Failed to load blacklist data: ' + response.message, 'danger');
            }
        }).fail(function() {
            showToast('Failed to load blacklist data', 'danger');
        });
    }

    // Show blacklist data in a modal
    function showBlacklistDataModal(categoryId, type, data) {
        var modal = $('<div class="modal fade" tabindex="-1">' +
            '<div class="modal-dialog modal-lg">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">Blacklist Data - ' + categoryId + ' (' + type + ')</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="table-responsive">' +
            '<table class="table table-striped">' +
            '<thead><tr><th>Entry</th></tr></thead>' +
            '<tbody>' +
            data.map(function(item) { return '<tr><td>' + item + '</td></tr>'; }).join('') +
            '</tbody>' +
            '</table>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>');
        
        $('body').append(modal);
        modal.modal('show');
        
        modal.on('hidden.bs.modal', function() {
            modal.remove();
        });
    }

    // Remove whitelist entry
    $('.remove-whitelist').click(function() {
        if (confirm('Are you sure you want to remove this whitelist entry?')) {
            var id = $(this).data('id');
            $.post('content_filter.php', {
                action: 'remove_whitelist',
                id: id,
                csrf_token: window.CSRF_TOKEN
            }, function() {
                location.reload();
            }).always(function() {
                var btn = $(this);
                btn.prop('disabled', false);
            });
        }
    });
});
</script>

<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>
<script>
function showToast(message, type) {
    var toastId = 'toast-' + Date.now();
    var toastHtml = `<div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>`;
    $('#toast-container').append(toastHtml);
    setTimeout(function() { $('#' + toastId).remove(); }, 4000);
}
</script>

<script>window.CSRF_TOKEN = '<?=$csrf_token?>';</script>

<?php include("foot.inc"); ?> 