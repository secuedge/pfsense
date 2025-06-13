<?php
/*
 * notification_helper.inc
 *
 * part of SecuEdge
 * Copyright (c) 2024 SecuEdge
 * All rights reserved.
 */

require_once("guiconfig.inc");

/**
 * Display a branded success notification
 */
function print_secuedge_success($message, $title = 'Success') {
    $notification = '<div class="alert alert-success">';
    $notification .= '<h4>' . htmlspecialchars($title) . '</h4>';
    $notification .= '<p>' . htmlspecialchars($message) . '</p>';
    $notification .= '<div class="notification-progress"></div>';
    $notification .= '</div>';
    print_info_box($notification, 'success');
}

/**
 * Display a branded error notification
 */
function print_secuedge_error($message, $title = 'Error') {
    $notification = '<div class="alert alert-danger">';
    $notification .= '<h4>' . htmlspecialchars($title) . '</h4>';
    $notification .= '<p>' . htmlspecialchars($message) . '</p>';
    $notification .= '<div class="notification-progress"></div>';
    $notification .= '</div>';
    print_info_box($notification, 'danger');
}

/**
 * Display a branded warning notification
 */
function print_secuedge_warning($message, $title = 'Warning') {
    $notification = '<div class="alert alert-warning">';
    $notification .= '<h4>' . htmlspecialchars($title) . '</h4>';
    $notification .= '<p>' . htmlspecialchars($message) . '</p>';
    $notification .= '<div class="notification-progress"></div>';
    $notification .= '</div>';
    print_info_box($notification, 'warning');
}

/**
 * Display a branded info notification
 */
function print_secuedge_info($message, $title = 'Information') {
    $notification = '<div class="alert alert-info">';
    $notification .= '<h4>' . htmlspecialchars($title) . '</h4>';
    $notification .= '<p>' . htmlspecialchars($message) . '</p>';
    $notification .= '<div class="notification-progress"></div>';
    $notification .= '</div>';
    print_info_box($notification, 'info');
}

/**
 * Generate a branded email notification
 */
function generate_secuedge_email($subject, $body, $footer = '') {
    $email = '<div class="email-notification">';
    $email .= '<div class="email-notification-header">';
    $email .= '<img src="/SecuEdge_Logo.png" alt="SecuEdge Logo" style="height: 40px;">';
    $email .= '<h2>' . htmlspecialchars($subject) . '</h2>';
    $email .= '</div>';
    $email .= '<div class="email-notification-body">';
    $email .= $body;
    $email .= '</div>';
    $email .= '<div class="email-notification-footer">';
    $email .= $footer ? $footer : '© ' . date('Y') . ' SecuEdge. All rights reserved.';
    $email .= '</div>';
    $email .= '</div>';
    return $email;
}

/**
 * Generate a branded PDF report
 */
function generate_secuedge_pdf_report($title, $content, $subtitle = '') {
    $report = '<div class="pdf-report">';
    $report .= '<div class="pdf-report-header">';
    $report .= '<img src="/SecuEdge_Logo.png" alt="SecuEdge Logo" style="height: 40px;">';
    $report .= '<div class="pdf-report-title">' . htmlspecialchars($title) . '</div>';
    if ($subtitle) {
        $report .= '<div class="pdf-report-subtitle">' . htmlspecialchars($subtitle) . '</div>';
    }
    $report .= '</div>';
    $report .= '<div class="pdf-report-content">';
    $report .= $content;
    $report .= '</div>';
    $report .= '<div class="pdf-report-footer">';
    $report .= 'Generated on ' . date('Y-m-d H:i:s') . '<br>';
    $report .= '© ' . date('Y') . ' SecuEdge. All rights reserved.';
    $report .= '</div>';
    $report .= '</div>';
    return $report;
}

/**
 * Display a toast notification
 */
function print_secuedge_toast($message, $type = 'info', $duration = 5000) {
    $toast = '<div class="toast-notification alert alert-' . $type . '">';
    $toast .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
    $toast .= '<span aria-hidden="true">&times;</span>';
    $toast .= '</button>';
    $toast .= '<p>' . htmlspecialchars($message) . '</p>';
    $toast .= '<div class="notification-progress"></div>';
    $toast .= '</div>';
    
    // Add JavaScript to handle the toast
    $script = '<script>';
    $script .= '$(document).ready(function() {';
    $script .= 'setTimeout(function() {';
    $script .= '$(".toast-notification").fadeOut();';
    $script .= '}, ' . $duration . ');';
    $script .= '});';
    $script .= '</script>';
    
    echo $toast . $script;
} 