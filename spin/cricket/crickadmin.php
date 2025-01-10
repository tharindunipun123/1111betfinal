<?php
session_start();
require_once 'db_connection.php';

// Check if $conn is set
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check your configuration.");
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        // Replace the 'update_multipliers' case in your switch statement with:

case 'update_multipliers':
    header('Content-Type: application/json');
    
    try {
        // Validate required fields
        if (!isset($_POST['match_id'])) {
            throw new Exception('Match ID is required');
        }

        // Validate all multipliers are present and positive
        $required_multipliers = [
            'team1_win_multiplier',
            'team2_win_multiplier',
            'draw_multiplier',
            'full_target_multiplier_yes',
            'full_target_multiplier_no',
            'six_over_target_multiplier_yes',
            'six_over_target_multiplier_no'
        ];

        foreach ($required_multipliers as $multiplier) {
            if (!isset($_POST[$multiplier]) || !is_numeric($_POST[$multiplier]) || $_POST[$multiplier] <= 0) {
                throw new Exception("Invalid value for $multiplier");
            }
        }

        $match_id = $_POST['match_id'];

        // Check if match exists and is not completed
        $check_stmt = $conn->prepare("SELECT status FROM cricket_matches WHERE id = ? FOR UPDATE");
        $check_stmt->bind_param("i", $match_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $match = $result->fetch_assoc();

        if (!$match) {
            throw new Exception('Match not found');
        }
        if ($match['status'] === 'completed') {
            throw new Exception('Cannot update multipliers for completed match');
        }

        // Start transaction
        $conn->begin_transaction();

        // Update multipliers
        $stmt = $conn->prepare("
            UPDATE cricket_matches 
            SET team1_win_multiplier = ?,
                team2_win_multiplier = ?,
                draw_multiplier = ?,
                full_target_multiplier_yes = ?,
                full_target_multiplier_no = ?,
                six_over_target_multiplier_yes = ?,
                six_over_target_multiplier_no = ?
            WHERE id = ? AND status != 'completed'
        ");

        $stmt->bind_param(
            "dddddddi",
            $_POST['team1_win_multiplier'],
            $_POST['team2_win_multiplier'],
            $_POST['draw_multiplier'],
            $_POST['full_target_multiplier_yes'],
            $_POST['full_target_multiplier_no'],
            $_POST['six_over_target_multiplier_yes'],
            $_POST['six_over_target_multiplier_no'],
            $match_id
        );

        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception('No changes were made to the multipliers');
        }

        // Get updated match data for notification
        $fetch_stmt = $conn->prepare("
            SELECT m.*, 
                (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1) as team1,
                (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1, 1) as team2
            FROM cricket_matches m
            WHERE m.id = ?
        ");
        $fetch_stmt->bind_param("i", $match_id);
        $fetch_stmt->execute();
        $updated_match = $fetch_stmt->get_result()->fetch_assoc();

        // Commit transaction
        $conn->commit();

        // Notify Node.js server
        notify_node_server('matchUpdate', $updated_match);

        echo json_encode([
            'success' => true,
            'message' => 'Multipliers updated successfully',
            'match' => $updated_match
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        error_log("Error updating multipliers: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;

            case 'toggle_lock':
                $match_id = $_POST['match_id'];
                $lock_type = $_POST['lock_type'];
                $is_locked = $_POST['is_locked'];
            
                try {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    // Determine which column to update
                    switch ($lock_type) {
                        case 'match':
                            $column = 'is_locked';
                            break;
                        case 'full_target':
                            $column = 'full_target_locked';
                            break;
                        case 'six_over_target':
                            $column = 'six_over_target_locked';
                            break;
                        default:
                            throw new Exception('Invalid lock type');
                    }
            
                    // Update the lock status
                    $stmt = $conn->prepare("UPDATE cricket_matches SET $column = ? WHERE id = ?");
                    $stmt->bind_param("ii", $is_locked, $match_id);
                    $stmt->execute();
            
                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Match not found or no changes made');
                    }
            
                    $conn->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;  // Only one exit

            

        case 'get_matches':
            $result = $conn->query("
                SELECT m.*, GROUP_CONCAT(mt.team_name) as teams
                FROM cricket_matches m
                LEFT JOIN match_teams mt ON m.id = mt.match_id
                GROUP BY m.id
                ORDER BY m.match_time DESC
            ");
            $matches = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($matches);
            exit;

        // Add the new update_teams case here
        case 'update_teams':
            $match_id = $_POST['match_id'];
            $teams = $_POST['teams'];
            
            $conn->begin_transaction();
            try {
                // Check if match exists and is not completed
                $check_stmt = $conn->prepare("SELECT status FROM cricket_matches WHERE id = ?");
                $check_stmt->bind_param("i", $match_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $match = $result->fetch_assoc();
        
                if (!$match) {
                    throw new Exception('Match not found');
                }
                if ($match['status'] === 'completed') {
                    throw new Exception('Cannot update teams for completed match');
                }
        
                // Delete existing teams
                $stmt = $conn->prepare("DELETE FROM match_teams WHERE match_id = ?");
                $stmt->bind_param("i", $match_id);
                $stmt->execute();
                
                // Insert new teams
                $stmt = $conn->prepare("INSERT INTO match_teams (match_id, team_name) VALUES (?, ?)");
                $unique_teams = array_unique(array_filter($teams, 'trim'));
                
                if (count($unique_teams) < 2) {
                    throw new Exception('Minimum two unique teams required');
                }
        
                foreach ($unique_teams as $team) {
                    if (!empty(trim($team))) {
                        $stmt->bind_param("is", $match_id, $team);
                        $stmt->execute();
                    }
                }
                
                $conn->commit();
                
                // Fetch updated match data for notification
                $stmt = $conn->prepare("
                    SELECT m.*, 
                           (SELECT GROUP_CONCAT(DISTINCT mt.team_name) 
                            FROM match_teams mt 
                            WHERE mt.match_id = m.id) as teams
                    FROM cricket_matches m
                    WHERE m.id = ?
                ");
                $stmt->bind_param("i", $match_id);
                $stmt->execute();
                $updated_match = $stmt->get_result()->fetch_assoc();
                
                // Notify Node.js server
                notify_node_server('matchUpdate', $updated_match);
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_match'])) {
        $conn->begin_transaction();

        try {
            // Insert match basic info
            $stmt = $conn->prepare("
                INSERT INTO cricket_matches (
                    match_time,
                    facebook_live_link,
                    team1_win_multiplier,
                    team2_win_multiplier,
                    draw_multiplier,
                    full_target_multiplier_yes,
                    full_target_multiplier_no,
                    six_over_target_multiplier_yes,
                    six_over_target_multiplier_no
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssddddddd",
                $_POST['match_time'],
                $_POST['facebook_live_link'],
                $_POST['team1_win_multiplier'],
                $_POST['team2_win_multiplier'],
                $_POST['draw_multiplier'],
                $_POST['full_target_multiplier_yes'],
                $_POST['full_target_multiplier_no'],
                $_POST['six_over_target_multiplier_yes'],
                $_POST['six_over_target_multiplier_no']
            );
            $stmt->execute();
            
            $match_id = $conn->insert_id;

            // Insert teams
            $stmt = $conn->prepare("INSERT INTO match_teams (match_id, team_name) VALUES (?, ?)");
            foreach ($_POST['teams'] as $team) {
                if (!empty(trim($team))) {
                    $stmt->bind_param("is", $match_id, $team);
                    $stmt->execute();
                }
            }

            $conn->commit();

            // Fetch new match data
            $stmt = $conn->prepare("
                SELECT m.*, GROUP_CONCAT(mt.team_name) as teams 
                FROM cricket_matches m
                LEFT JOIN match_teams mt ON m.id = mt.match_id
                WHERE m.id = ?
                GROUP BY m.id
            ");
            $stmt->bind_param("i", $match_id);
            $stmt->execute();
            $new_match = $stmt->get_result()->fetch_assoc();

            // Notify Node.js server
            notify_node_server('newMatch', $new_match);

        } catch (Exception $e) {
            $conn->rollback();
            die("Error creating match: " . $e->getMessage());
        }
    }

    if (isset($_POST['end_match'])) {
        header('Content-Type: application/json');
        
        $match_id = $_POST['match_id'] ?? null;
        $result = $_POST['result'] ?? null;
        $full_target_result = $_POST['full_target_result'] ?? null;
        $six_over_target_result = $_POST['six_over_target_result'] ?? null;
    
        if (!$match_id || !$result || !$full_target_result || !$six_over_target_result) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
    
        try {
            // Start transaction
            $conn->begin_transaction();
    
            // First check if match exists and is not already completed
            $check_stmt = $conn->prepare("
                SELECT m.*, 
                       (SELECT GROUP_CONCAT(team_name) FROM match_teams WHERE match_id = m.id) as teams
                FROM cricket_matches m 
                WHERE m.id = ? 
                FOR UPDATE
            ");
            $check_stmt->bind_param("i", $match_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $match = $check_result->fetch_assoc();
    
            if (!$match) {
                throw new Exception('Match not found');
            }
            if ($match['status'] === 'completed') {
                throw new Exception('Match is already completed');
            }
    
            // Validate the result matches the teams
            $teams = explode(',', $match['teams']);
            if ($result !== 'draw' && !in_array($result, ['team1_win', 'team2_win'])) {
                throw new Exception('Invalid match result');
            }
    
            // Update match status atomically
            $update_stmt = $conn->prepare("
                UPDATE cricket_matches 
                SET status = 'completed',
                    result = ?,
                    full_target_result = ?,
                    six_over_target_result = ?,
                    completed_at = NOW()
                WHERE id = ? AND status != 'completed'
            ");
            $update_stmt->bind_param("sssi", $result, $full_target_result, $six_over_target_result, $match_id);
            $update_stmt->execute();
    
            if ($update_stmt->affected_rows === 0) {
                throw new Exception('Failed to update match status');
            }
    
            // Process bets with optimistic locking
            $bets_stmt = $conn->prepare("
                SELECT cb.*, u.wallet as current_wallet, u.id as user_id,
                       COUNT(*) OVER (PARTITION BY cb.user_id) as bet_count
                FROM cricket_bets cb 
                JOIN users u ON cb.user_id = u.id
                WHERE cb.match_id = ? AND cb.status = 'pending'
                ORDER BY cb.user_id
                FOR UPDATE
            ");
            $bets_stmt->bind_param("i", $match_id);
            $bets_stmt->execute();
            $bets = $bets_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
            // Group bets by user for atomic wallet updates
            $user_winnings = array();
            $bet_updates = array();
            $processed_bets = 0;
    
            foreach ($bets as $bet) {
                $is_winner = false;
    
                if (strpos($bet['bet_type'], 'team') === 0 || $bet['bet_type'] === 'draw') {
                    $is_winner = $bet['bet_type'] === $result;
                } elseif (strpos($bet['bet_type'], 'full_target_') === 0) {
                    $bet_choice = substr($bet['bet_type'], -3);
                    $is_winner = $bet_choice === $full_target_result;
                } elseif (strpos($bet['bet_type'], 'six_over_target_') === 0) {
                    $bet_choice = substr($bet['bet_type'], -3);
                    $is_winner = $bet_choice === $six_over_target_result;
                }
    
                $user_id = $bet['user_id'];
                if (!isset($user_winnings[$user_id])) {
                    $user_winnings[$user_id] = [
                        'total_winnings' => 0,
                        'current_wallet' => $bet['current_wallet'],
                        'bet_count' => 0,
                        'won_count' => 0
                    ];
                }
    
                $user_winnings[$user_id]['bet_count']++;
    
                if ($is_winner) {
                    // Calculate winnings using precise decimal arithmetic
                    $bet_amount = floatval($bet['amount']);
                    $multiplier = floatval($bet['multiplier']);
                    $winnings = round($bet_amount * $multiplier, 2);
                    
                    $user_winnings[$user_id]['total_winnings'] += $winnings;
                    $user_winnings[$user_id]['won_count']++;
                    
                    $bet_updates[] = [
                        'id' => $bet['id'],
                        'status' => 'won',
                        'winnings' => $winnings
                    ];
                    
                    error_log("Processing winning bet {$bet['id']} for user {$user_id}. Winnings: {$winnings}");
                } else {
                    $bet_updates[] = [
                        'id' => $bet['id'],
                        'status' => 'lost',
                        'winnings' => 0
                    ];
                }
    
                $processed_bets++;
            }
    
            // Process all wallet updates atomically
            foreach ($user_winnings as $user_id => $data) {
                if ($data['total_winnings'] > 0) {
                    // Update user wallet with optimistic locking
                    $wallet_stmt = $conn->prepare("
                        UPDATE users 
                        SET 
                            wallet = wallet + ?,
                            total_winnings = COALESCE(total_winnings, 0) + ?,
                            total_bets = COALESCE(total_bets, 0) + ?,
                            won_bets = COALESCE(won_bets, 0) + ?
                        WHERE id = ? AND wallet = ?
                    ");
                    $wallet_stmt->bind_param("ddiiid", 
                        $data['total_winnings'],
                        $data['total_winnings'],
                        $data['bet_count'],
                        $data['won_count'],
                        $user_id,
                        $data['current_wallet']
                    );
                    $wallet_stmt->execute();
    
                    if ($wallet_stmt->affected_rows === 0) {
                        throw new Exception("Failed to update wallet for user {$user_id}. Wallet may have changed.");
                    }
    
                    error_log("Updated wallet for user {$user_id}. Total winnings: {$data['total_winnings']}");
                } else {
                    // Update user stats even if they didn't win
                    $stats_stmt = $conn->prepare("
                        UPDATE users 
                        SET total_bets = COALESCE(total_bets, 0) + ?
                        WHERE id = ?
                    ");
                    $stats_stmt->bind_param("ii", $data['bet_count'], $user_id);
                    $stats_stmt->execute();
                }
            }
    
            // Update all bet statuses
            foreach ($bet_updates as $update) {
                $bet_stmt = $conn->prepare("
                    UPDATE cricket_bets 
                    SET status = ?, 
                        winnings = ?,
                        processed_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                $bet_stmt->bind_param("sdi", 
                    $update['status'], 
                    $update['winnings'], 
                    $update['id']
                );
                $bet_stmt->execute();
    
                if ($bet_stmt->affected_rows === 0) {
                    throw new Exception("Failed to update bet status for bet {$update['id']}");
                }
            }
    
            // Commit transaction
            $conn->commit();
    
            // Get final match data for notification
            $final_match_stmt = $conn->prepare("
                SELECT m.*, 
                    (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1) as team1,
                    (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1, 1) as team2
                FROM cricket_matches m
                WHERE m.id = ?
            ");
            $final_match_stmt->bind_param("i", $match_id);
            $final_match_stmt->execute();
            $final_match = $final_match_stmt->get_result()->fetch_assoc();
    
            // Notify Node.js server
            notify_node_server('matchEnded', [
                'matchId' => $match_id,
                'result' => $result,
                'fullTargetResult' => $full_target_result,
                'sixOverTargetResult' => $six_over_target_result,
                'match' => $final_match,
                'processedBets' => $processed_bets,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    
            echo json_encode([
                'success' => true,
                'message' => 'Match ended successfully',
                'processedBets' => $processed_bets,
                'match' => $final_match
            ]);
    
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            error_log("Error processing match $match_id: " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Function to notify Node.js server
function notify_node_server($event, $data) {
    $ch = curl_init('http://localhost:3008/notify');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event' => $event, 'data' => $data]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// Fetch all matches
// Modify the matches fetch query
$result = $conn->query("
    SELECT m.*,
           GROUP_CONCAT(DISTINCT mt.team_name ORDER BY mt.id LIMIT 2) as teams
    FROM cricket_matches m
    LEFT JOIN match_teams mt ON m.id = mt.match_id
    GROUP BY m.id
    ORDER BY 
        CASE 
            WHEN m.status != 'completed' THEN 1 
            ELSE 2 
        END,
        m.match_time DESC
");
$matches = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Betting Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Cricket Betting Admin Panel</h1>
        
        <!-- Create Match Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Create New Match</h2>
            <form method="POST" class="space-y-4">
                <div id="teams-container" class="space-y-2">
                    <input type="text" name="teams[]" placeholder="Team 1" class="w-full p-2 border rounded" required>
                    <input type="text" name="teams[]" placeholder="Team 2" class="w-full p-2 border rounded" required>
                </div>
                <button type="button" onclick="addTeamField()" class="bg-green-500 text-white px-4 py-2 rounded">
                    Add Team
                </button>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="datetime-local" name="match_time" class="w-full p-2 border rounded" required>
                    <input type="url" name="facebook_live_link" placeholder="Facebook Live Link" class="w-full p-2 border rounded">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Team Win Multipliers</label>
                        <input type="number" step="0.01" name="team1_win_multiplier" placeholder="Team 1 Win Multiplier" class="w-full p-2 border rounded" required>
                        <input type="number" step="0.01" name="team2_win_multiplier" placeholder="Team 2 Win Multiplier" class="w-full p-2 border rounded" required>
                        <input type="number" step="0.01" name="draw_multiplier" placeholder="Draw Multiplier" class="w-full p-2 border rounded" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Full Target Multipliers</label>
                        <input type="number" step="0.01" name="full_target_multiplier_yes" placeholder="Yes Multiplier" class="w-full p-2 border rounded" required>
                        <input type="number" step="0.01" name="full_target_multiplier_no" placeholder="No Multiplier" class="w-full p-2 border rounded" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Six Over Target Multipliers</label>
                        <input type="number" step="0.01" name="six_over_target_multiplier_yes" placeholder="Yes Multiplier" class="w-full p-2 border rounded" required>
                        <input type="number" step="0.01" name="six_over_target_multiplier_no" placeholder="No Multiplier" class="w-full p-2 border rounded" required>
                    </div>
                </div>

                <button type="submit" name="create_match" class="bg-blue-500 text-white px-6 py-2 rounded">
                    Create Match
                </button>
            </form>
        </div>

        <!-- Matches List -->
        <div id="matches-container" class="space-y-6">
            <?php foreach ($matches as $match): 
                $teams = explode(',', $match['teams']);
            ?>
                <div id="match-<?= $match['id'] ?>" class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4"><?= implode(' vs ', $teams) ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <p>Time: <?= $match['match_time'] ?></p>
                        <p class="status">Status: <?= $match['status'] ?></p>
                    </div>

                    <!-- Teams Management -->
                    <div class="mb-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-medium mb-3">Manage Teams</h4>
                        <form class="teams-form space-y-2">
                            <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                            <div class="teams-inputs space-y-2">
                                <?php foreach ($teams as $team): ?>
                                    <div class="flex gap-2">
                                        <input type="text" name="teams[]" value="<?= htmlspecialchars($team) ?>" class="flex-1 p-2 border rounded" required>
                                        <button type="button" onclick="removeTeam(this)" class="bg-red-500 text-white px-3 py-2 rounded">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="addTeamToMatch(this)" class="bg-green-500 text-white px-4 py-2 rounded">Add Team</button>
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save Teams</button>
                            </div>
                        </form>
                    </div>

                    <!-- Multipliers Management -->
                    <div class="mb-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-medium mb-3">Update Multipliers</h4>
                        <form class="multipliers-form grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                            
                            <!-- Team Win Multipliers -->
                            <div class="space-y-2">
                                <h5 class="font-medium">Team Win Multipliers</h5>
                                <?php foreach ($teams as $index => $team): ?>
                                    <div>
                                        <label class="block text-sm"><?= htmlspecialchars($team) ?> Win</label>
                                        <input type="number" 
                                               step="0.01" 
                                               name="team<?= $index + 1 ?>_win_multiplier" 
                                               value="<?= $match["team" . ($index + 1) . "_win_multiplier"] ?>" 
                                               class="w-full p-2 border rounded">
                                    </div>
                                <?php endforeach; ?>
                                <div>
                                    <label class="block text-sm">Draw</label>
                                    <input type="number" 
                                           step="0.01" 
                                           name="draw_multiplier" 
                                           value="<?= $match['draw_multiplier'] ?>" 
                                           class="w-full p-2 border rounded">
                                </div>
                            </div>

                            <!-- Full Target Multipliers -->
                            <div class="space-y-2">
                                <h5 class="font-medium">Full Target Multipliers</h5>
                                <div>
                                    <label class="block text-sm">Yes</label>
                                    <input type="number" 
                                           step="0.01" 
                                           name="full_target_multiplier_yes" 
                                           value="<?= $match['full_target_multiplier_yes'] ?>" 
                                           class="w-full p-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm">No</label>
                                    <input type="number" 
                                           step="0.01" 
                                           name="full_target_multiplier_no" 
                                           value="<?= $match['full_target_multiplier_no'] ?>" 
                                           class="w-full p-2 border rounded">
                                </div>
                            </div>

                            <!-- Six Over Target Multipliers -->
                            <div class="space-y-2">
                                <h5 class="font-medium">Six Over Target Multipliers</h5>
                                <div>
                                    <label class="block text-sm">Yes</label>
                                    <input type="number" 
                                           step="0.01" 
                                           name="six_over_target_multiplier_yes" 
                                           value="<?= $match['six_over_target_multiplier_yes'] ?>" 
                                           class="w-full p-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm">No</label>
                                    <input type="number" 
                                           step="0.01" 
                                           name="six_over_target_multiplier_no" 
                                           value="<?= $match['six_over_target_multiplier_no'] ?>" 
                                           class="w-full p-2 border rounded">
                                </div>
                            </div>

                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded md:col-span-3">
                                Update Multipliers
                            </button>
                        </form>
                    </div>

                    <!-- Betting Options Locks -->
                    <div class="mb-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-medium mb-3">Betting Options Control</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Match Lock -->
                            <form class="lock-form">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input type="hidden" name="lock_type" value="match">
                                <input type="hidden" name="is_locked" value="<?= $match['is_locked'] ? '0' : '1' ?>">
                                <button type="submit" class="w-full bg-yellow-500 text-white px-4 py-2 rounded">
                                    <?= $match['is_locked'] ? 'Unlock' : 'Lock' ?> Match Betting
                                </button>
                            </form>

                            <!-- Full Target Lock -->
                            <form class="lock-form">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input type="hidden" name="lock_type" value="full_target">
                                <input type="hidden" name="is_locked" value="<?= $match['full_target_locked'] ? '0' : '1' ?>">
                                <button type="submit" class="w-full bg-yellow-500 text-white px-4 py-2 rounded">
                                    <?= $match['full_target_locked'] ? 'Unlock' : 'Lock' ?> Full Target
                                </button>
                            </form>

                            <!-- Six Over Target Lock -->
                            <form class="lock-form">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input type="hidden" name="lock_type" value="six_over_target">
                                <input type="hidden" name="is_locked" value="<?= $match['six_over_target_locked'] ? '0' : '1' ?>">
                                <button type="submit" class="w-full bg-yellow-500 text-white px-4 py-2 rounded">
                                    <?= $match['six_over_target_locked'] ? 'Unlock' : 'Lock' ?> Six Over Target
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- End Match Form -->
                    <?php if ($match['status'] !== 'completed'): ?>
                        <div class="p-4 bg-gray-50 rounded">
                            <h4 class="font-medium mb-3">End Match</h4>
                            <form class="end-match-form space-y-4" method="POST">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                
                                <div>
                                    <label class="block text-sm font-medium mb-1">Match Result</label>
                                    <select name="result" class="w-full p-2 border rounded" required>
                                        <option value="">Select Result</option>
                                        <?php foreach ($teams as $index => $team): ?>
                                            <option value="team<?= $index + 1 ?>_win">
                                                <?= htmlspecialchars($team) ?> Wins
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="draw">Draw</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Full Target Result</label>
                                    <select name="full_target_result" class="w-full p-2 border rounded" required>
                                        <option value="">Select Result</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Six Over Target Result</label>
                                    <select name="six_over_target_result" class="w-full p-2 border rounded" required>
                                        <option value="">Select Result</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                                <button type="submit" 
                                        name="end_match" 
                                        class="bg-red-500 text-white px-4 py-2 rounded"
                                        onclick="return confirm('Are you sure you want to end this match? This action cannot be undone.')">
                                    End Match
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
   $(document).ready(function() {
    // Global submission tracking
    let isSubmitting = false;
    let formProcessingTimeout;

    // Toast notification system
    const showToast = (message, type = 'success') => {
        const toast = $(`
            <div class="fixed top-4 right-4 max-w-md px-6 py-3 rounded shadow-lg transition-all duration-300 transform translate-y-[-100%] opacity-0 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white">
                ${message}
            </div>
        `).appendTo('body');

        setTimeout(() => {
            toast.css({
                transform: 'translateY(0)',
                opacity: 1
            });
        }, 100);

        setTimeout(() => {
            toast.css({
                transform: 'translateY(-100%)',
                opacity: 0
            });
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // Form submission prevention
    const preventDoubleSubmission = (form) => {
        if (isSubmitting) return false;
        isSubmitting = true;

        // Disable all submit buttons in the form
        form.find('button[type="submit"]').prop('disabled', true)
            .append('<span class="ml-2 inline-block animate-spin">↻</span>');

        // Set a timeout to re-enable submission if the request takes too long
        formProcessingTimeout = setTimeout(() => {
            resetSubmissionStatus();
            showToast('Request timeout. Please try again.', 'error');
        }, 30000); // 30 second timeout

        return true;
    };

    // Reset submission status
    const resetSubmissionStatus = () => {
        isSubmitting = false;
        clearTimeout(formProcessingTimeout);
        $('button[type="submit"]').prop('disabled', false)
            .find('.animate-spin').remove();
    };

    // Generic form submission handler
    const handleFormSubmission = (form, options = {}) => {
        const {
            url = window.location.href,
            confirmMessage,
            successMessage = 'Operation completed successfully',
            errorMessage = 'Operation failed',
            beforeSubmit,
            afterSuccess,
            redirectUrl
        } = options;

        if (confirmMessage && !confirm(confirmMessage)) {
            return;
        }

        if (beforeSubmit && !beforeSubmit(form)) {
            return;
        }

        if (!preventDoubleSubmission(form)) {
            return;
        }

        const formData = form.is('form[enctype="multipart/form-data"]') ? 
            new FormData(form[0]) : form.serialize();

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: form.is('form[enctype="multipart/form-data"]') ? false : true,
            contentType: form.is('form[enctype="multipart/form-data"]') ? false : 'application/x-www-form-urlencoded',
            success: function(response) {
                if (response.success || response === 'success') {
                    showToast(successMessage);
                    if (afterSuccess) {
                        afterSuccess(response);
                    }
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        location.reload();
                    }
                } else {
                    resetSubmissionStatus();
                    showToast(response.message || errorMessage, 'error');
                }
            },
            error: function(xhr) {
                resetSubmissionStatus();
                showToast(xhr.responseJSON?.message || errorMessage, 'error');
            }
        });
    };

    // Create match form handler
    $('form[name="create_match"]').submit(function(e) {
        e.preventDefault();
        handleFormSubmission($(this), {
            successMessage: 'Match created successfully!',
            errorMessage: 'Error creating match',
            beforeSubmit: (form) => {
                const teams = form.find('input[name="teams[]"]')
                    .map((_, el) => $(el).val().trim())
                    .get()
                    .filter(Boolean);
                
                if (teams.length < 2) {
                    showToast('Minimum two teams are required', 'error');
                    return false;
                }
                return true;
            }
        });
    });

    //Remove the duplicate end-match handler at the bottom of your script
//And replace the end match form handler inside the $(document).ready with this:

// End match form handler
$('.end-match-form').submit(function(e) {
    e.preventDefault();
    const form = $(this);
    
    // Validate form data
    const result = form.find('select[name="result"]').val();
    const fullTargetResult = form.find('select[name="full_target_result"]').val();
    const sixOverTargetResult = form.find('select[name="six_over_target_result"]').val();
    
    if (!result || !fullTargetResult || !sixOverTargetResult) {
        showToast('Please select all match results', 'error');
        return false;
    }

    if (!confirm('Are you sure you want to end this match? This action cannot be undone.')) {
        return false;
    }

    if (!preventDoubleSubmission(form)) {
        return false;
    }

    // Add name="end_match" to the serialized data
    const formData = new FormData(form[0]);
    formData.append('end_match', '1');

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                // Handle both string and JSON responses
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    showToast('Match ended successfully');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    resetSubmissionStatus();
                    showToast(result.message || 'Error ending match', 'error');
                }
            } catch (e) {
                // If response is not JSON
                if (response.includes('success')) {
                    showToast('Match ended successfully');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    resetSubmissionStatus();
                    showToast('Error ending match', 'error');
                }
            }
        },
        error: function(xhr) {
            resetSubmissionStatus();
            let errorMessage = 'Error ending match';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMessage = response.message || errorMessage;
            } catch (e) {
                console.error('Error parsing error response:', e);
            }
            showToast(errorMessage, 'error');
        },
        complete: function() {
            // Ensure form is reset even if there's an error
            resetSubmissionStatus();
        }
    });
});

    // Multipliers form handler
    $('.multipliers-form').submit(function(e) {
        e.preventDefault();
        handleFormSubmission($(this), {
            url: '?action=update_multipliers',
            successMessage: 'Multipliers updated successfully',
            errorMessage: 'Error updating multipliers',
            beforeSubmit: (form) => {
                const inputs = form.find('input[type="number"]');
                let valid = true;
                inputs.each(function() {
                    const val = parseFloat($(this).val());
                    if (isNaN(val) || val <= 0) {
                        showToast('All multipliers must be positive numbers', 'error');
                        valid = false;
                        return false;
                    }
                });
                return valid;
            }
        });
    });

    // Lock form handler
    $('.lock-form').submit(function(e) {
        e.preventDefault();
        const isLocking = $(this).find('input[name="is_locked"]').val() === '1';
        const lockType = $(this).find('input[name="lock_type"]').val();
        
        handleFormSubmission($(this), {
            url: '?action=toggle_lock',
            successMessage: `${lockType} betting ${isLocking ? 'locked' : 'unlocked'} successfully`,
            errorMessage: 'Error toggling lock status'
        });
    });

    // Teams form handler
    $('.teams-form').submit(function(e) {
        e.preventDefault();
        handleFormSubmission($(this), {
            url: '?action=update_teams',
            successMessage: 'Teams updated successfully',
            errorMessage: 'Error updating teams',
            beforeSubmit: (form) => {
                const teamInputs = form.find('input[name="teams[]"]');
                if (teamInputs.length < 2) {
                    showToast('Minimum two teams are required', 'error');
                    return false;
                }
                
                const teams = new Set(teamInputs.map((_, el) => $(el).val().trim()).get());
                if (teams.size < 2) {
                    showToast('Teams must be unique', 'error');
                    return false;
                }
                return true;
            }
        });
    });

    

    // Team management helper functions
    window.addTeamField = function() {
        const container = document.getElementById('teams-container');
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'teams[]';
        input.placeholder = 'Team Name';
        input.className = 'w-full p-2 border rounded mt-2';
        input.required = true;
        container.appendChild(input);
    };

    window.addTeamToMatch = function(button) {
        const container = button.closest('form').querySelector('.teams-inputs');
        const div = document.createElement('div');
        div.className = 'flex gap-2';
        div.innerHTML = `
            <input type="text" name="teams[]" class="flex-1 p-2 border rounded" required>
            <button type="button" onclick="removeTeam(this)" class="bg-red-500 text-white px-3 py-2 rounded">Remove</button>
        `;
        container.appendChild(div);
    };

    window.removeTeam = function(button) {
        const container = button.closest('.teams-inputs');
        const teamDiv = button.closest('.flex');
        if (container.children.length > 2) {
            teamDiv.remove();
        } else {
            showToast('Minimum two teams are required', 'error');
        }
    };

    // Real-time multiplier validation
    $('input[type="number"]').on('input', function() {
        const value = parseFloat($(this).val());
        if (value <= 0) {
            $(this).addClass('border-red-500');
        } else {
            $(this).removeClass('border-red-500');
        }
    });

    // Automatically close notifications after a delay
    setTimeout(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success') || urlParams.has('error')) {
            // Clean the URL without refreshing the page
            window.history.replaceState({}, '', window.location.pathname);
        }
    }, 3000);

    // Reset form on escape key
    $(document).keydown(function(e) {
        if (e.key === 'Escape') {
            $('form').each(function() {
                this.reset();
            });
            $('.border-red-500').removeClass('border-red-500');
        }
    });

    // Save form data to localStorage
    $('form :input').on('input', function() {
        const form = $(this).closest('form');
        const formId = form.attr('id') || form.attr('name');
        if (formId) {
            const formData = form.serialize();
            localStorage.setItem(`form_${formId}`, formData);
        }
    });

    // Restore form data from localStorage
    $('form').each(function() {
        const formId = $(this).attr('id') || $(this).attr('name');
        if (formId) {
            const savedData = localStorage.getItem(`form_${formId}`);
            if (savedData) {
                const formData = new URLSearchParams(savedData);
                for (const [key, value] of formData) {
                    $(`[name="${key}"]`).val(value);
                }
            }
        }
    });
});
</script>
</body>
</html>