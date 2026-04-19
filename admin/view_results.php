<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// Get elections for selection
$elections = $pdo->query("
    SELECT e.*, 
           GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as position_names
    FROM elections e 
    LEFT JOIN election_positions ep ON e.id = ep.election_id 
    LEFT JOIN positions p ON ep.position_id = p.id 
    GROUP BY e.id 
    ORDER BY e.created_at DESC
")->fetchAll();

$selectedElection = null;
$results = [];
$electionStats = [];

// Handle export requests
if (isset($_GET['export']) && isset($_GET['election_id'])) {
    $electionId = (int)$_GET['election_id'];
    $exportType = $_GET['export'];
    
    // Get election details for export
    $election = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $election->execute([$electionId]);
    $exportElection = $election->fetch(PDO::FETCH_ASSOC);
    
    if ($exportElection) {
        switch ($exportType) {
            case 'csv':
                exportCSV($pdo, $electionId, $exportElection);
                break;
            case 'excel':
                exportExcel($pdo, $electionId, $exportElection);
                break;
            case 'json':
                exportJSON($pdo, $electionId, $exportElection);
                break;
            case 'pdf':
                exportPDF($pdo, $electionId, $exportElection);
                break;
        }
        exit;
    }
}
  
// Handle election selection
if (isset($_GET['election_id'])) {
    $electionId = (int)$_GET['election_id'];
    
    // Get election details
    $election = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $election->execute([$electionId]);
    $selectedElection = $election->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedElection) {
        // Get positions for this election
        $positions = $pdo->prepare("
            SELECT p.* 
            FROM positions p 
            INNER JOIN election_positions ep ON p.id = ep.position_id 
            WHERE ep.election_id = ? 
            ORDER BY p.name
        ");
        $positions->execute([$electionId]);
        $positions = $positions->fetchAll();
        
        // Get results for each position
        foreach ($positions as $position) {
            $candidates = $pdo->prepare("
                SELECT c.*, 
                       COALESCE(vote_counts.vote_count, 0) as vote_count
                FROM candidates c
                LEFT JOIN (
                    SELECT v.candidate_id, COUNT(*) as vote_count 
                    FROM votes v
                    INNER JOIN candidates c2 ON v.candidate_id = c2.id
                    WHERE v.election_id = ? 
                    AND c2.position_id = ?
                    GROUP BY v.candidate_id
                ) vote_counts ON c.id = vote_counts.candidate_id
                WHERE c.position_id = ? AND c.active = 1
                ORDER BY vote_count DESC, c.name ASC
            ");
            $candidates->execute([$electionId, $position['id'], $position['id']]);
            $candidates = $candidates->fetchAll();
            
            $results[$position['id']] = [
                'position' => $position,
                'candidates' => $candidates
            ];
        }
        
        // Get election statistics
        $totalVotes = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?");
        $totalVotes->execute([$electionId]);
        $electionStats['total_votes'] = $totalVotes->fetchColumn();
        
        $totalVoters = $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
        $electionStats['voter_turnout'] = $totalVoters > 0 ? round(($electionStats['total_votes'] / $totalVoters) * 100, 1) : 0;
        
        $uniqueVoters = $pdo->prepare("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = ?");
        $uniqueVoters->execute([$electionId]);
        $electionStats['unique_voters'] = $uniqueVoters->fetchColumn();
    }
}

// Function to get winner badge
function getWinnerBadge($candidate, $candidates) {
    if (empty($candidates)) return '';
    
    $maxVotes = max(array_column($candidates, 'vote_count'));
    if ($candidate['vote_count'] == $maxVotes && $maxVotes > 0) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
            </svg>
            Winner
        </span>';
    }
    return '';
}

// Function to get vote percentage
function getVotePercentage($candidate, $totalVotes) {
    if ($totalVotes == 0) return '0%';
    return round(($candidate['vote_count'] / $totalVotes) * 100, 1) . '%';
}

// Export Functions
function exportCSV($pdo, $electionId, $election) {
    $filename = 'election_results_' . $electionId . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Header
    fputcsv($output, ['Election Results']);
    fputcsv($output, ['Election Title', $election['title']]);
    fputcsv($output, ['Description', $election['description']]);
    fputcsv($output, ['Start Date', $election['start_date']]);
    fputcsv($output, ['End Date', $election['end_date']]);
    fputcsv($output, ['Status', $election['status']]);
    fputcsv($output, []);
    
    // Results header
    fputcsv($output, ['Position', 'Candidate Name', 'Platform', 'Votes', 'Percentage']);
    
    // Get results
    $positions = $pdo->prepare("
        SELECT p.* FROM positions p 
        INNER JOIN election_positions ep ON p.id = ep.position_id 
        WHERE ep.election_id = ? 
        ORDER BY p.name
    ");
    $positions->execute([$electionId]);
    
    while ($position = $positions->fetch(PDO::FETCH_ASSOC)) {
        $candidates = $pdo->prepare("
            SELECT c.*, COALESCE(vote_counts.vote_count, 0) as vote_count
            FROM candidates c
            LEFT JOIN (
                SELECT v.candidate_id, COUNT(*) as vote_count 
                FROM votes v
                INNER JOIN candidates c2 ON v.candidate_id = c2.id
                WHERE v.election_id = ?
                AND c2.position_id = ?
                GROUP BY v.candidate_id
            ) vote_counts ON c.id = vote_counts.candidate_id
            WHERE c.position_id = ?
            ORDER BY vote_count DESC, c.name ASC
        ");
        $candidates->execute([$electionId, $position['id'], $position['id']]);
        
        $totalVotes = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?");
        $totalVotes->execute([$electionId]);
        $totalVotes = $totalVotes->fetchColumn();
        
        while ($candidate = $candidates->fetch(PDO::FETCH_ASSOC)) {
            $percentage = $totalVotes > 0 ? round(($candidate['vote_count'] / $totalVotes) * 100, 2) : 0;
            fputcsv($output, [
                $position['name'],
                $candidate['name'],
                $candidate['platform'] ?? '',
                $candidate['vote_count'],
                $percentage . '%'
            ]);
        }
    }
    
    fclose($output);
}

function exportPDF($pdo, $electionId, $election) {
    $filename = 'election_results_' . $electionId . '_' . date('Y-m-d_H-i-s');
    
    // Check if we want to download directly or show preview
    $directDownload = isset($_GET['download']) && $_GET['download'] === 'true';
    
    if ($directDownload) {
        // Force download as HTML file that can be opened and printed to PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        header('Pragma: no-cache');
        header('Expires: 0');
    } else {
        // Show preview page
        header('Content-Type: text/html; charset=utf-8');
    }
    
    // Generate comprehensive election report
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Election Results - ' . htmlspecialchars($election['title']) . '</title>
        <style>
            @page { 
                size: A4; 
                margin: 1cm; 
            }
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.4; 
                margin: 0;
                padding: 20px;
                color: #333;
            }
            .header { 
                text-align: center; 
                border-bottom: 3px solid #2c3e50; 
                padding-bottom: 20px; 
                margin-bottom: 30px; 
            }
            .header h1 { 
                color: #2c3e50; 
                margin: 0; 
                font-size: 28px; 
                font-weight: bold; 
            }
            .header .subtitle { 
                color: #7f8c8d; 
                font-size: 16px; 
                margin-top: 5px; 
            }
            
            .election-info { 
                background: #f8f9fa; 
                border: 1px solid #dee2e6; 
                border-radius: 8px; 
                padding: 20px; 
                margin-bottom: 30px; 
            }
            .election-info h2 { 
                color: #495057; 
                margin-top: 0; 
                border-bottom: 2px solid #6c757d; 
                padding-bottom: 10px; 
            }
            .info-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 15px; 
            }
            .info-item { 
                display: flex; 
            }
            .info-label { 
                font-weight: bold; 
                min-width: 120px; 
                color: #495057; 
            }
            .info-value { 
                color: #212529; 
            }
            
            .position-section { 
                margin-bottom: 40px; 
                page-break-inside: avoid; 
            }
            .position-title { 
                background: #e9ecef; 
                color: #495057; 
                padding: 15px 20px; 
                margin: 0; 
                font-size: 20px; 
                font-weight: bold; 
                border-radius: 8px 8px 0 0; 
            }
            
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 0; 
                background: white; 
                border-radius: 0 0 8px 8px; 
                overflow: hidden; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            }
            thead { 
                background: #6c757d; 
                color: white; 
            }
            th, td { 
                padding: 12px 15px; 
                text-align: left; 
                border-bottom: 1px solid #dee2e6; 
            }
            th { 
                font-weight: 600; 
                font-size: 14px; 
                text-transform: uppercase; 
                letter-spacing: 0.5px; 
            }
            tr:nth-child(even) { 
                background: #f8f9fa; 
            }
            tr:hover { 
                background: #e9ecef; 
            }
            
            .vote-count { 
                font-weight: bold; 
                color: #28a745; 
                font-size: 16px; 
            }
            .percentage { 
                color: #6c757d; 
                font-style: italic; 
            }
            
            .no-candidates { 
                text-align: center; 
                padding: 40px; 
                color: #6c757d; 
                font-style: italic; 
                background: #f8f9fa; 
                border-radius: 0 0 8px 8px; 
            }
            
            .controls { 
                position: fixed; 
                top: 20px; 
                right: 20px; 
                background: white; 
                padding: 15px; 
                border-radius: 8px; 
                box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
                z-index: 1000; 
            }
            .btn { 
                display: inline-block; 
                padding: 10px 20px; 
                margin: 0 5px; 
                text-decoration: none; 
                border-radius: 5px; 
                font-weight: bold; 
                cursor: pointer; 
                border: none; 
                font-size: 14px; 
            }
            .btn-primary { 
                background: #007bff; 
                color: white; 
            }
            .btn-secondary { 
                background: #6c757d; 
                color: white; 
            }
            .btn-success { 
                background: #28a745; 
                color: white; 
            }
            
            @media print { 
                .controls { display: none; }
                body { padding: 0; }
                .position-section { page-break-inside: avoid; }
            }
        </style>
        <script>
            function printToPDF() {
                window.print();
            }
            function downloadHTML() {
                window.location.href = "?election_id=' . $electionId . '&export=pdf&download=true";
            }
        </script>
    </head>
    <body>';
    
    if (!$directDownload) {
        echo '<div class="controls">
            <button onclick="printToPDF()" class="btn btn-primary">🖨️ Print to PDF</button>
            <button onclick="downloadHTML()" class="btn btn-success">💾 Download HTML</button>
            <a href="view_results.php?election_id=' . $electionId . '" class="btn btn-secondary">← Back</a>
        </div>';
    }
    
    echo '<div class="header">
        <h1>ELECTION RESULTS REPORT</h1>
        <div class="subtitle">Official Results Document</div>
        <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
    </div>';
    
    echo '<div class="election-info">
        <h2>📊 Election Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Title:</span>
                <span class="info-value">' . htmlspecialchars($election['title']) . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Status:</span>
                <span class="info-value">' . ucfirst($election['status']) . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Start Date:</span>
                <span class="info-value">' . date('M j, Y g:i A', strtotime($election['start_date'])) . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">End Date:</span>
                <span class="info-value">' . date('M j, Y g:i A', strtotime($election['end_date'])) . '</span>
            </div>
        </div>';
    
    if (!empty($election['description'])) {
        echo '<div style="margin-top: 15px;">
            <span class="info-label">Description:</span><br>
            <span class="info-value">' . htmlspecialchars($election['description']) . '</span>
        </div>';
    }
    
    echo '</div>';
    
    // Get and display results by position
    $positions = $pdo->prepare("
        SELECT p.* FROM positions p 
        INNER JOIN election_positions ep ON p.id = ep.position_id 
        WHERE ep.election_id = ? 
        ORDER BY p.name
    ");
    $positions->execute([$electionId]);
    
    $hasPositions = false;
    while ($position = $positions->fetch(PDO::FETCH_ASSOC)) {
        $hasPositions = true;
        echo '<div class="position-section">
            <h3 class="position-title">🏆 ' . htmlspecialchars($position['name']) . '</h3>';
        
        $candidates = $pdo->prepare("
            SELECT c.*, COALESCE(vote_counts.vote_count, 0) as vote_count
            FROM candidates c
            LEFT JOIN (
                SELECT v.candidate_id, COUNT(*) as vote_count 
                FROM votes v
                INNER JOIN candidates c2 ON v.candidate_id = c2.id
                WHERE v.election_id = ?
                AND c2.position_id = ?
                GROUP BY v.candidate_id
            ) vote_counts ON c.id = vote_counts.candidate_id
            WHERE c.position_id = ?
            ORDER BY vote_count DESC, c.name ASC
        ");
        $candidates->execute([$electionId, $position['id'], $position['id']]);
        
        $candidatesData = $candidates->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($candidatesData)) {
            echo '<div class="no-candidates">No candidates registered for this position.</div>';
        } else {
            $totalVotes = array_sum(array_column($candidatesData, 'vote_count'));
            
            echo '<table>
                <thead>
                    <tr>
                        <th>👤 Candidate Name</th>
                        <th>📋 Platform</th>
                        <th>🗳️ Votes Received</th>
                        <th>📊 Percentage</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($candidatesData as $candidate) {
                $percentage = $totalVotes > 0 ? round(($candidate['vote_count'] / $totalVotes) * 100, 2) : 0;
                echo '<tr>
                    <td><strong>' . htmlspecialchars($candidate['name']) . '</strong></td>
                    <td>' . htmlspecialchars($candidate['platform'] ?? 'No platform provided') . '</td>
                    <td class="vote-count">' . number_format($candidate['vote_count']) . '</td>
                    <td class="percentage">' . $percentage . '%</td>
                </tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    if (!$hasPositions) {
        echo '<div class="no-candidates">No positions found for this election.</div>';
    }
    
    echo '<div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 12px;">
        This report was automatically generated by SmartVote Digital Voting Platform<br>
        Report ID: ' . $filename . ' | Generated: ' . date('Y-m-d H:i:s') . '
    </div>';
    
    echo '</body></html>';
}

function exportJSON($pdo, $electionId, $election) {
    $filename = 'election_results_' . $electionId . '_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $results = [
        'election' => $election,
        'positions' => []
    ];
    
    // Get results
    $positions = $pdo->prepare("
        SELECT p.* FROM positions p 
        INNER JOIN election_positions ep ON p.id = ep.position_id 
        WHERE ep.election_id = ? 
        ORDER BY p.name
    ");
    $positions->execute([$electionId]);
    
    while ($position = $positions->fetch(PDO::FETCH_ASSOC)) {
        $candidates = $pdo->prepare("
            SELECT c.*, COALESCE(vote_counts.vote_count, 0) as vote_count
            FROM candidates c
            LEFT JOIN (
                SELECT v.candidate_id, COUNT(*) as vote_count 
                FROM votes v
                INNER JOIN candidates c2 ON v.candidate_id = c2.id
                WHERE v.election_id = ?
                AND c2.position_id = ?
                GROUP BY v.candidate_id
            ) vote_counts ON c.id = vote_counts.candidate_id
            WHERE c.position_id = ?
            ORDER BY vote_count DESC, c.name ASC
        ");
        $candidates->execute([$electionId, $position['id'], $position['id']]);
        
        $totalVotes = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?");
        $totalVotes->execute([$electionId]);
        $totalVotes = $totalVotes->fetchColumn();
        
        $positionData = [
            'position' => $position,
            'candidates' => [],
            'total_votes' => (int)$totalVotes
        ];
        
        while ($candidate = $candidates->fetch(PDO::FETCH_ASSOC)) {
            $percentage = $totalVotes > 0 ? round(($candidate['vote_count'] / $totalVotes) * 100, 2) : 0;
            // Ensure platform field exists
            $candidate['platform'] = $candidate['platform'] ?? '';
            $positionData['candidates'][] = [
                'candidate' => $candidate,
                'votes' => (int)$candidate['vote_count'],
                'percentage' => $percentage
            ];
        }
        
        $results['positions'][] = $positionData;
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exportExcel($pdo, $electionId, $election) {
    // For Excel, we'll use CSV format with .xlsx extension
    // This provides basic Excel compatibility
    exportCSV($pdo, $electionId, $election);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - SmartVote</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#0ea5e9'
                    }
                }
            }
        }
  </script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
      <!-- Header -->
      <div class="mb-4 lg:mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">View Results</h1>
                <p class="text-sm lg:text-base text-slate-600 mt-1">View and analyze election results.</p>
      </div>

            <!-- Election Selection -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
          </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-slate-800">Select Election</h2>
                </div>

                <form method="get" class="flex items-center space-x-4">
                    <div class="flex-1">
                        <select name="election_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent" onchange="this.form.submit()">
                            <option value="">Select an election to view results</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo (int)$election['id']; ?>" 
                                        <?php echo ($selectedElection && $selectedElection['id'] == $election['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($election['title']); ?> 
                                    (<?php echo date('M j, Y', strtotime($election['created_at'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($selectedElection): ?>
                <!-- Election Overview -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($selectedElection['title']); ?></h2>
                            <p class="text-slate-600"><?php echo htmlspecialchars($selectedElection['description']); ?></p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-slate-500">Election Period</div>
                            <div class="font-medium">
                                <?php echo date('M j, Y g:i A', strtotime($selectedElection['start_date'])); ?>
                            </div>
                            <div class="text-sm text-slate-500">to</div>
                            <div class="font-medium">
                                <?php echo date('M j, Y g:i A', strtotime($selectedElection['end_date'])); ?>
                            </div>
                        </div>
        </div>

                    <!-- Election Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-blue-50 rounded-lg p-4">
          <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div>
                                    <p class="text-sm font-medium text-blue-600">Total Votes</p>
                                    <p class="text-2xl font-bold text-blue-800"><?php echo number_format($electionStats['total_votes']); ?></p>
            </div>
          </div>
        </div>

                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-600">Unique Voters</p>
                                    <p class="text-2xl font-bold text-green-800"><?php echo number_format($electionStats['unique_voters']); ?></p>
                                </div>
                            </div>
          </div>

                        <div class="bg-purple-50 rounded-lg p-4">
              <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                </div>
                <div>
                                    <p class="text-sm font-medium text-purple-600">Voter Turnout</p>
                                    <p class="text-2xl font-bold text-purple-800"><?php echo $electionStats['voter_turnout']; ?>%</p>
                                </div>
                            </div>
                        </div>
                </div>
              </div>

                <!-- Results by Position -->
                <?php foreach ($results as $positionId => $positionData): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                            <h3 class="text-xl font-semibold text-slate-800"><?php echo htmlspecialchars($positionData['position']['name']); ?></h3>
                            <p class="text-slate-600"><?php echo htmlspecialchars($positionData['position']['description'] ?? 'No description'); ?></p>
                        </div>

                        <div class="p-6">
                            <?php if (empty($positionData['candidates'])): ?>
                                <p class="text-slate-500 text-center py-8">No candidates for this position.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php 
                                    $totalVotes = array_sum(array_column($positionData['candidates'], 'vote_count'));
                                    $maxVotes = max(array_column($positionData['candidates'], 'vote_count'));
                                    ?>
                                    
                                    <?php foreach ($positionData['candidates'] as $candidate): ?>
                                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                                            <div class="flex items-center space-x-4">
                                                <?php if ($candidate['photo_path'] && file_exists($candidate['photo_path'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($candidate['photo_path']); ?>" 
                                                         alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                                         class="w-12 h-12 rounded-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-slate-200 rounded-full flex items-center justify-center">
                                                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div>
                                                    <div class="flex items-center space-x-2">
                                                        <h4 class="text-lg font-medium text-slate-900"><?php echo htmlspecialchars($candidate['name']); ?></h4>
                                                        <?php echo getWinnerBadge($candidate, $positionData['candidates']); ?>
                                                    </div>
                                                    <?php if ($candidate['description']): ?>
                                                        <p class="text-sm text-slate-600"><?php echo htmlspecialchars($candidate['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="text-right">
                                                <div class="text-2xl font-bold text-slate-900"><?php echo number_format($candidate['vote_count']); ?></div>
                                                <div class="text-sm text-slate-600">votes (<?php echo getVotePercentage($candidate, $totalVotes); ?>)</div>
                                                
                                                <?php if ($totalVotes > 0): ?>
                                                    <div class="w-32 bg-slate-200 rounded-full h-2 mt-2">
                                                        <div class="bg-blue-600 h-2 rounded-full" 
                                                             style="width: <?php echo ($candidate['vote_count'] / $totalVotes) * 100; ?>%"></div>
                </div>
              <?php endif; ?>
                                            </div>
            </div>
            <?php endforeach; ?>
          </div>
                            <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

                <!-- Export Options -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">Export Results</h3>
                            <p class="text-slate-600">Download results in various formats</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                </svg>
                                Print Results
                            </button>
                            
                            <a href="?election_id=<?php echo $selectedElection['id']; ?>&export=pdf" 
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export PDF
                            </a>
                            
                            <a href="?election_id=<?php echo $selectedElection['id']; ?>&export=csv" 
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export CSV
                            </a>
                            
                            <a href="?election_id=<?php echo $selectedElection['id']; ?>&export=excel" 
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Export Excel
                            </a>
                            
                            <a href="?election_id=<?php echo $selectedElection['id']; ?>&export=json" 
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                                Export JSON
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Election Selected -->
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">No Election Selected</h3>
                    <p class="text-slate-600">Please select an election from the dropdown above to view results.</p>
                </div>
            <?php endif; ?>
    </main>
</body>
</html>