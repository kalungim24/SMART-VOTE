<?php
/**
 * Advanced Election Status Management System
 * Handles election statuses with better control and flexibility
 */

class ElectionManager {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get the current active election without modifying any statuses
     */
    public function getActiveElection(): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM elections WHERE status = 'active' ORDER BY start_date DESC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Update election statuses based on time (only for non-active elections)
     */
    public function updateElectionStatuses(): void {
        $now = date('Y-m-d H:i:s');
        
        // Only update elections that are NOT currently active
        $this->pdo->prepare("
            UPDATE elections 
            SET status = CASE 
                WHEN ? > end_date THEN 'expired'
                WHEN ? < start_date THEN 'pending'
                WHEN ? >= start_date AND ? <= end_date THEN 'active'
                ELSE 'closed'
            END
            WHERE status != 'active'
        ")->execute([$now, $now, $now, $now]);
        
        // Only expire active elections if they are past their end date
        $this->pdo->prepare("
            UPDATE elections 
            SET status = 'expired'
            WHERE status = 'active' AND ? > end_date
        ")->execute([$now]);
    }
    
    /**
     * Manually activate an election
     */
    public function activateElection(int $electionId): array {
        $now = date('Y-m-d H:i:s');
        
        // Get election details
        $election = $this->pdo->prepare("SELECT * FROM elections WHERE id = ?");
        $election->execute([$electionId]);
        $electionData = $election->fetch(PDO::FETCH_ASSOC);
        
        if (!$electionData) {
            return ['success' => false, 'message' => 'Election not found'];
        }
        
        if ($now > $electionData['end_date']) {
            return ['success' => false, 'message' => 'Cannot activate election: it has already ended'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Close all other active elections
            $this->pdo->exec("UPDATE elections SET status = 'closed' WHERE status = 'active'");
            
            // Activate the selected election
            $stmt = $this->pdo->prepare("UPDATE elections SET status = 'active' WHERE id = ?");
            $stmt->execute([$electionId]);
            
            $this->pdo->commit();
            
            $startDate = date('M j, Y g:i A', strtotime($electionData['start_date']));
            $endDate = date('M j, Y g:i A', strtotime($electionData['end_date']));
            
            if ($now < $electionData['start_date']) {
                $message = "Election activated successfully. Voting will begin on {$startDate}.";
            } else {
                $message = "Election activated successfully. Voting is now open until {$endDate}.";
            }
            
            return ['success' => true, 'message' => $message];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error activating election: ' . $e->getMessage()];
        }
    }
    
    /**
     * Manually close an election
     */
    public function closeElection(int $electionId): array {
        try {
            $stmt = $this->pdo->prepare("UPDATE elections SET status = 'closed' WHERE id = ?");
            $stmt->execute([$electionId]);
            
            return ['success' => true, 'message' => 'Election closed successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error closing election: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get election status with detailed information
     */
    public function getElectionStatus(int $electionId): array {
        $election = $this->pdo->prepare("SELECT * FROM elections WHERE id = ?");
        $election->execute([$electionId]);
        $electionData = $election->fetch(PDO::FETCH_ASSOC);
        
        if (!$electionData) {
            return ['found' => false];
        }
        
        $now = date('Y-m-d H:i:s');
        $startTime = strtotime($electionData['start_date']);
        $endTime = strtotime($electionData['end_date']);
        $currentTime = strtotime($now);
        
        $statusInfo = [
            'found' => true,
            'election' => $electionData,
            'current_status' => $electionData['status'],
            'time_based_status' => $this->calculateTimeBasedStatus($currentTime, $startTime, $endTime),
            'is_manually_activated' => $electionData['status'] === 'active' && $currentTime < $startTime,
            'can_activate' => ($electionData['status'] === 'pending' || $electionData['status'] === 'closed') && $currentTime < $endTime,
            'can_close' => $electionData['status'] === 'active',
            'time_remaining' => $this->calculateTimeRemaining($currentTime, $endTime),
            'is_within_voting_period' => $currentTime >= $startTime && $currentTime <= $endTime
        ];
        
        return $statusInfo;
    }
    
    /**
     * Calculate what the status should be based on time
     */
    private function calculateTimeBasedStatus(int $currentTime, int $startTime, int $endTime): string {
        if ($currentTime > $endTime) {
            return 'expired';
        } elseif ($currentTime < $startTime) {
            return 'pending';
        } elseif ($currentTime >= $startTime && $currentTime <= $endTime) {
            return 'active';
        }
        return 'closed';
    }
    
    /**
     * Calculate time remaining until election ends
     */
    private function calculateTimeRemaining(int $currentTime, int $endTime): ?array {
        if ($currentTime >= $endTime) {
            return null; // Election has ended
        }
        
        $remaining = $endTime - $currentTime;
        $hours = floor($remaining / 3600);
        $minutes = floor(($remaining % 3600) / 60);
        $seconds = $remaining % 60;
        
        return [
            'total_seconds' => $remaining,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'formatted' => sprintf('%d hours, %d minutes', $hours, $minutes)
        ];
    }
    
    /**
     * Get all elections with their status information
     */
    public function getAllElectionsWithStatus(): array {
        $elections = $this->pdo->query("SELECT * FROM elections ORDER BY created_at DESC")->fetchAll();
        $result = [];
        
        foreach ($elections as $election) {
            $statusInfo = $this->getElectionStatus($election['id']);
            $result[] = array_merge($election, $statusInfo);
        }
        
        return $result;
    }
}
?>
