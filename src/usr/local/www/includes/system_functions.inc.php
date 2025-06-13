<?php
require_once("util.inc");
require_once("config.inc");

function system_get_info() {
    $info = array();
    
    // System uptime
    $info['uptime'] = get_uptime();
    
    // CPU information
    $cpuinfo = get_cpu_info();
    $info['cpu_model'] = $cpuinfo['model'];
    $info['cpu_load'] = get_cpu_load();
    
    // Memory information
    $meminfo = get_memory_info();
    $info['memory_total'] = $meminfo['total'];
    $info['memory_used'] = $meminfo['used'];
    $info['memory_usage'] = round(($meminfo['used'] / $meminfo['total']) * 100);
    
    // Version information
    global $g;
    $info['version'] = g_get('product_version_string');
    $info['os_version'] = php_uname('s') . ' ' . php_uname('r');
    
    // BIOS information
    $info['bios_version'] = trim(shell_exec('dmidecode -s bios-version 2>/dev/null'));
    
    // Last update information
    $info['last_update'] = get_last_update_time();
    
    // Temperature sensors
    $info['temperatures'] = get_temperature_sensors();
    
    return $info;
}

function get_cpu_info() {
    $model = trim(shell_exec('sysctl -n hw.model'));
    return array('model' => $model);
}

function get_cpu_load() {
    $load = sys_getloadavg();
    return round($load[0] * 100);
}

function get_memory_info() {
    $pagesize = intval(shell_exec('sysctl -n hw.pagesize'));
    $physmem = intval(shell_exec('sysctl -n hw.physmem'));
    $free = intval(shell_exec('sysctl -n vm.stats.vm.v_free_count')) * $pagesize;
    
    $total = $physmem;
    $used = $total - $free;
    
    return array(
        'total' => $total,
        'used' => $used,
        'free' => $free
    );
}

function get_last_update_time() {
    global $config;
    if (isset($config['revision']['time'])) {
        return date("Y-m-d H:i:s", intval($config['revision']['time']));
    }
    return 'Unknown';
}

function get_temperature_sensors() {
    $sensors = array();
    
    // CPU temperature
    $cpu_temp = intval(shell_exec('sysctl -n dev.cpu.0.temperature 2>/dev/null'));
    if ($cpu_temp) {
        $sensors[] = array(
            'label' => 'CPU',
            'value' => round($cpu_temp / 10)
        );
    }
    
    // System temperature (if available)
    $sys_temp = intval(shell_exec('sysctl -n hw.acpi.thermal.tz0.temperature 2>/dev/null'));
    if ($sys_temp) {
        $sensors[] = array(
            'label' => 'System',
            'value' => round($sys_temp / 10)
        );
    }
    
    return $sensors;
} 