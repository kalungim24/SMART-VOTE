<?php
// Common helper functions

// Include logging helper
require_once __DIR__ . '/logging_helper.php';

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_system_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
}

function update_system_setting(PDO $pdo, string $key, string $value): bool {
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    return $stmt->execute([$key, $value]);
}

function get_system_name(PDO $pdo, string $default = 'SmartLonda'): string {
    $name = get_system_setting($pdo, 'system_name', $default);
    return trim($name) !== '' ? $name : $default;
}

function set_system_name(PDO $pdo, string $systemName): bool {
    return update_system_setting($pdo, 'system_name', trim($systemName));
}

function get_system_email_domain(): string {
    return 'smartlonda.system';
}

function get_security_from_address(PDO $pdo): string {
    return 'SmartLonda Security <security@' . get_system_email_domain() . '>';
}

function get_security_reply_to(): string {
    return 'noreply@' . get_system_email_domain();
}

function current_active_election(PDO $pdo): ?array {
    // Use the new ElectionManager for better status handling
    require_once __DIR__ . '/election_manager.php';
    $electionManager = new ElectionManager($pdo);
    
    // Update statuses first
    $electionManager->updateElectionStatuses();
    
    // Get the active election
    return $electionManager->getActiveElection();
}

function has_voted_in_active(PDO $pdo, string $voterId, ?string $position = null): bool {
    $election = current_active_election($pdo);
    if (!$election) {
        return false;
    }
    
    if ($position === null) {
        // Check if voter has voted in any position for the active election
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM votes 
            WHERE (voter_id = ? OR voter_id_int = ?) 
            AND election_id = ?
        ");
        $stmt->execute([$voterId, $voterId, $election['id']]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    // Check if voter has voted for a specific position in the active election
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM votes 
        WHERE (voter_id = ? OR voter_id_int = ?) 
        AND election_id = ?
        AND (position = ? OR position_id = ?)
    ");
    $stmt->execute([$voterId, $voterId, $election['id'], $position, $position]);
    return (int)$stmt->fetchColumn() > 0;
}

function get_stats(PDO $pdo): array {
    $now = date('Y-m-d H:i:s');
    
    // Update election statuses based on current time
    $pdo->prepare("
        UPDATE elections 
        SET status = CASE 
            WHEN ? > end_date THEN 'expired'
            WHEN ? < start_date THEN 'pending'
            WHEN status = 'active' AND ? >= start_date AND ? <= end_date THEN 'active'
            ELSE 'closed'
        END
    ")->execute([$now, $now, $now, $now]);
    
    $voters = (int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
    
    // Check if active column exists before using it
    $candidatesQuery = "SELECT COUNT(*) FROM candidates";
    try {
        $pdo->query("SELECT active FROM candidates LIMIT 1");
        $candidatesQuery .= " WHERE active = 1";
    } catch (PDOException $e) {
        // active column doesn't exist, use all candidates
    }
    $candidates = (int)$pdo->query($candidatesQuery)->fetchColumn();
    
    $votes = (int)$pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
    
    // Count elections by status
    $active = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status = 'active'")->fetchColumn();
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status = 'pending'")->fetchColumn();
    $expired = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status = 'expired'")->fetchColumn();
    $closed = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status = 'closed'")->fetchColumn();
    
    return compact('voters','candidates','votes','active','pending','expired','closed');
}

function is_election_active(array $election): bool {
    $now = date('Y-m-d H:i:s');
    $startDate = $election['start_date'];
    $endDate = $election['end_date'];
    
    return $election['status'] === 'active' && $now >= $startDate && $now <= $endDate;
}

function get_election_status(array $election): string {
    $now = date('Y-m-d H:i:s');
    $startDate = $election['start_date'];
    $endDate = $election['end_date'];
    
    // Check if election has expired first (regardless of database status)
    if ($now > $endDate) {
        return 'expired';
    }
    
    // If election is manually set to active, respect that status
    if ($election['status'] === 'active') {
        return 'active';
    }
    
    // If election is manually set to closed, respect that status
    if ($election['status'] === 'closed') {
        return 'closed';
    }
    
    // Check if election is pending (before start date)
    if ($now < $startDate) {
        return 'pending';
    }
    
    // If we reach here, election is within time period but not manually activated
    return 'pending';
}

function redirect(string $path): void {
    header("Location: {$path}");
    exit;
}

