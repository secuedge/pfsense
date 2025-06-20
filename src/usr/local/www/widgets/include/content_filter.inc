<?php
/*
 * content_filter.inc
 *
 * part of SecuEdge
 * Copyright (c) 2024 SecuEdge
 * All rights reserved.
 */

class ContentFilterManager {
    private $config;
    private $db;
    private $db_path = '/var/db/content_filter.db';
    private $db_dir = '/var/db';

    public function __construct() {
        $this->config = &$GLOBALS['config'];
        $this->initializeDatabase();
    }

    private function initializeDatabase() {
        try {
            // Ensure the database directory exists
            if (!is_dir($this->db_dir)) {
                mkdir($this->db_dir, 0755, true);
            }

            // Set proper permissions on the directory
            chmod($this->db_dir, 0755);

            // Create or open the database
            $this->db = new SQLite3($this->db_path);
            
            // Set proper permissions on the database file
            chmod($this->db_path, 0644);

            // Enable foreign key support
            $this->db->exec('PRAGMA foreign_keys = ON');
            
            // Create tables
            $this->createTables();
            
            // Initialize default data
            $this->initializeDefaultCategories();
        } catch (Exception $e) {
            error_log("ContentFilterManager Database Error: " . $e->getMessage());
            throw new Exception("Failed to initialize content filter database: " . $e->getMessage());
        }
    }

    private function createTables() {
        // Groups table
        $this->db->exec('CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        // Categories table
        $this->db->exec('CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            description TEXT,
            is_enabled INTEGER DEFAULT 1
        )');

        // Blocklist table - Updated structure
        $this->db->exec('CREATE TABLE IF NOT EXISTS blocklist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            type TEXT NOT NULL,
            group_id INTEGER,
            category_id INTEGER,
            schedule_id INTEGER,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id),
            FOREIGN KEY (category_id) REFERENCES categories(id),
            FOREIGN KEY (schedule_id) REFERENCES schedules(id)
        )');

        // Whitelist table
        $this->db->exec('CREATE TABLE IF NOT EXISTS whitelist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            group_id INTEGER,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id)
        )');

        // Schedules table
        $this->db->exec('CREATE TABLE IF NOT EXISTS schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            start_time TIME,
            end_time TIME,
            days TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function initializeDefaultCategories() {
        if (!$this->db) {
            throw new Exception("Database connection not initialized");
        }

        $categories = [
            ['Security', 'security', 'Malware, phishing, and security threats'],
            ['Adult Content', 'content', 'Adult and inappropriate content'],
            ['Social Media', 'social', 'Social networking platforms'],
            ['Gaming', 'productivity', 'Gaming and entertainment sites'],
            ['Shopping', 'productivity', 'Online shopping and e-commerce'],
            ['Streaming', 'productivity', 'Video and music streaming services'],
            ['Gambling', 'substance', 'Online gambling and betting sites'],
            ['Violence', 'violence', 'Violent or extremist content'],
            ['Downloads', 'downloads', 'File sharing and download sites']
        ];

        $this->db->exec('BEGIN TRANSACTION');

        try {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO categories (name, type, description) VALUES (:name, :type, :description)');
            
            foreach ($categories as $category) {
                $stmt->reset();
                $stmt->bindValue(':name', $category[0], SQLITE3_TEXT);
                $stmt->bindValue(':type', $category[1], SQLITE3_TEXT);
                $stmt->bindValue(':description', $category[2], SQLITE3_TEXT);
                $stmt->execute();
            }

            $this->db->exec('COMMIT');
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function addGroup($name, $description = '') {
        $stmt = $this->db->prepare('INSERT INTO groups (name, description) VALUES (:name, :description)');
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getGroups() {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return array();
            }

            $result = $this->db->query("SELECT * FROM groups ORDER BY name");
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return array();
            }

            $groups = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $groups[] = $row;
            }

            return $groups;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return array();
        }
    }

    public function getCategories() {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return array();
            }

            $result = $this->db->query("SELECT * FROM categories ORDER BY name");
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return array();
            }

            $categories = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $categories[] = $row;
            }

            return $categories;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return array();
        }
    }

    public function addBlock($data) {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return false;
            }

            $stmt = $this->db->prepare("INSERT INTO blocklist (url, type, group_id, category_id, schedule_id, description, is_active) 
                                      VALUES (:url, :type, :group_id, :category_id, :schedule_id, :description, :is_active)");
            
            if (!$stmt) {
                error_log("Content Filter Error: Failed to prepare query - " . $this->db->lastErrorMsg());
                return false;
            }

            $stmt->bindValue(':url', filter_var($data['url'], FILTER_SANITIZE_URL));
            $stmt->bindValue(':type', filter_var($data['type'], FILTER_SANITIZE_STRING));
            $stmt->bindValue(':group_id', isset($data['group_id']) ? intval($data['group_id']) : null);
            $stmt->bindValue(':category_id', isset($data['category_id']) ? intval($data['category_id']) : null);
            $stmt->bindValue(':schedule_id', isset($data['schedule_id']) ? intval($data['schedule_id']) : null);
            $stmt->bindValue(':description', isset($data['description']) ? filter_var($data['description'], FILTER_SANITIZE_STRING) : '');
            $stmt->bindValue(':is_active', isset($data['is_active']) ? intval($data['is_active']) : 1);

            $result = $stmt->execute();
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return false;
            }

            return $this->db->lastInsertRowID();
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return false;
        }
    }

    public function updateBlock($id, $data) {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return false;
            }

            $updates = array();
            $params = array(':id' => intval($id));

            if (isset($data['url'])) {
                $updates[] = "url = :url";
                $params[':url'] = filter_var($data['url'], FILTER_SANITIZE_URL);
            }
            if (isset($data['type'])) {
                $updates[] = "type = :type";
                $params[':type'] = filter_var($data['type'], FILTER_SANITIZE_STRING);
            }
            if (isset($data['group_id'])) {
                $updates[] = "group_id = :group_id";
                $params[':group_id'] = intval($data['group_id']);
            }
            if (isset($data['category_id'])) {
                $updates[] = "category_id = :category_id";
                $params[':category_id'] = intval($data['category_id']);
            }
            if (isset($data['schedule_id'])) {
                $updates[] = "schedule_id = :schedule_id";
                $params[':schedule_id'] = intval($data['schedule_id']);
            }
            if (isset($data['description'])) {
                $updates[] = "description = :description";
                $params[':description'] = filter_var($data['description'], FILTER_SANITIZE_STRING);
            }
            if (isset($data['is_active'])) {
                $updates[] = "is_active = :is_active";
                $params[':is_active'] = intval($data['is_active']);
            }

            if (empty($updates)) {
                error_log("Content Filter Error: No fields to update");
                return false;
            }

            $query = "UPDATE blocklist SET " . implode(", ", $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                error_log("Content Filter Error: Failed to prepare query - " . $this->db->lastErrorMsg());
                return false;
            }

            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }

            $result = $stmt->execute();
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteBlock($id) {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return false;
            }

            $stmt = $this->db->prepare("DELETE FROM blocklist WHERE id = :id");
            if (!$stmt) {
                error_log("Content Filter Error: Failed to prepare query - " . $this->db->lastErrorMsg());
                return false;
            }

            $stmt->bindValue(':id', intval($id));
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return false;
        }
    }

    public function addToWhitelist($group_id, $url, $description = '') {
        $stmt = $this->db->prepare('INSERT INTO whitelist (group_id, url, description) VALUES (:group_id, :url, :description)');
        $stmt->bindValue(':group_id', $group_id, SQLITE3_INTEGER);
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getBlocklist($group_id = null) {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return array();
            }

            $query = "SELECT b.*, g.name as group_name, c.name as category_name 
                     FROM blocklist b 
                     LEFT JOIN groups g ON b.group_id = g.id 
                     LEFT JOIN categories c ON b.category_id = c.id";
            
            $params = array();
            if ($group_id !== null) {
                $query .= " WHERE b.group_id = :group_id";
                $params[':group_id'] = intval($group_id);
            }

            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                error_log("Content Filter Error: Failed to prepare query - " . $this->db->lastErrorMsg());
                return array();
            }

            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }

            $result = $stmt->execute();
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return array();
            }

            $blocks = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $blocks[] = $row;
            }

            return $blocks;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return array();
        }
    }

    public function getWhitelist($group_id = null) {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return array();
            }

            $query = "SELECT w.*, g.name as group_name 
                     FROM whitelist w 
                     LEFT JOIN groups g ON w.group_id = g.id";
            
            $params = array();
            if ($group_id !== null) {
                $query .= " WHERE w.group_id = :group_id";
                $params[':group_id'] = intval($group_id);
            }

            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                error_log("Content Filter Error: Failed to prepare query - " . $this->db->lastErrorMsg());
                return array();
            }

            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }

            $result = $stmt->execute();
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return array();
            }

            $whitelist = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $whitelist[] = $row;
            }

            return $whitelist;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return array();
        }
    }

    public function removeBlock($id) {
        $stmt = $this->db->prepare('DELETE FROM blocklist WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();

        $this->updateDNSBlocklist();
        $this->updateFirewallRules();
    }

    public function toggleStatus($id) {
        $stmt = $this->db->prepare('UPDATE blocklist SET status = NOT status WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();

        $this->updateDNSBlocklist();
        $this->updateFirewallRules();
    }

    public function getSchedules() {
        try {
            if (!$this->db) {
                error_log("Content Filter Error: Database connection not initialized");
                return array();
            }

            $result = $this->db->query("SELECT * FROM schedules ORDER BY name");
            if (!$result) {
                error_log("Content Filter Error: Failed to execute query - " . $this->db->lastErrorMsg());
                return array();
            }

            $schedules = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $schedules[] = $row;
            }

            return $schedules;
        } catch (Exception $e) {
            error_log("Content Filter Error: " . $e->getMessage());
            return array();
        }
    }

    private function updateDNSBlocklist() {
        // Update dnsmasq configuration
        $blocklist = $this->getBlocklist();
        $dnsmasq_conf = '';
        
        foreach ($blocklist as $block) {
            if ($block['status'] && $block['type'] === 'domain') {
                $dnsmasq_conf .= "address=/{$block['url']}/0.0.0.0\n";
            }
        }

        file_put_contents('/var/etc/dnsmasq-blocklist.conf', $dnsmasq_conf);
        exec('/usr/local/etc/rc.d/dnsmasq restart');
    }

    private function updateFirewallRules() {
        // Update pf rules for application blocking
        $blocklist = $this->getBlocklist();
        $pf_rules = '';
        
        foreach ($blocklist as $block) {
            if ($block['status'] && $block['type'] === 'app') {
                // Add specific rules based on application type
                switch ($block['url']) {
                    case 'facebook':
                        $pf_rules .= "block in quick proto tcp from any to any port 443\n";
                        break;
                    case 'twitter':
                        $pf_rules .= "block in quick proto tcp from any to any port 443\n";
                        break;
                    // Add more application-specific rules as needed
                }
            }
        }

        file_put_contents('/var/etc/pf-blocklist.rules', $pf_rules);
        exec('/sbin/pfctl -f /var/etc/pf-blocklist.rules');
    }

    public function checkAccess($url, $user) {
        $blocklist = $this->getBlocklist();
        foreach ($blocklist as $block) {
            if (!$block['status']) {
                continue;
            }

            if ($block['type'] === 'domain') {
                if (strpos($url, $block['url']) !== false) {
                    return false;
                }
            } elseif ($block['type'] === 'url') {
                if ($url === $block['url']) {
                    return false;
                }
            }
        }
        return true;
    }
}
?> 