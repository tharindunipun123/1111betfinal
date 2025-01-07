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
        case 'update_multipliers':
            $match_id = $_POST['match_id'];
            $team1_win_multiplier = $_POST['team1_win_multiplier'];
            $team2_win_multiplier = $_POST['team2_win_multiplier'];
            $draw_multiplier = $_POST['draw_multiplier'];
            $full_target_multiplier_yes = $_POST['full_target_multiplier_yes'];
            $full_target_multiplier_no = $_POST['full_target_multiplier_no'];
            $six_over_target_multiplier_yes = $_POST['six_over_target_multiplier_yes'];
            $six_over_target_multiplier_no = $_POST['six_over_target_multiplier_no'];

            // Your existing update multipliers code...
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
        $match_id = $_POST['match_id'];
    
        // Start transaction
        $conn->begin_transaction();
    
        try {
            // First check if match is not already completed
            $check_stmt = $conn->prepare("SELECT status FROM cricket_matches WHERE id = ?");
            $check_stmt->bind_param("i", $match_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $match = $result->fetch_assoc();
    
            if (!$match || $match['status'] === 'completed') {
                throw new Exception('Match not found or already completed');
            }
    
            $result = $_POST['result'];
            $full_target_result = $_POST['full_target_result'];
            $six_over_target_result = $_POST['six_over_target_result'];
    
            // Update match status atomically
            $update_stmt = $conn->prepare("
                UPDATE cricket_matches 
                SET status = 'completed',
                    result = ?,
                    full_target_result = ?,
                    six_over_target_result = ?
                WHERE id = ? AND status != 'completed'
            ");
            $update_stmt->bind_param("sssi", $result, $full_target_result, $six_over_target_result, $match_id);
            $update_stmt->execute();
    
            // Process only pending bets
            $bets_stmt = $conn->prepare("
                SELECT * FROM cricket_bets 
                WHERE match_id = ? AND status = 'pending'
            ");
            $bets_stmt->bind_param("i", $match_id);
            $bets_stmt->execute();
            $bets = $bets_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
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
    
                if ($is_winner) {
                    // Calculate winnings using precise float values
                    $bet_amount = floatval($bet['amount']);
                    $multiplier = floatval($bet['multiplier']);
                    $winnings = $bet_amount * $multiplier;
    
                    // Update user wallet with a single atomic query
                    $wallet_stmt = $conn->prepare("
                        UPDATE users 
                        SET wallet = wallet + ? 
                        WHERE id = ?
                    ");
                    $wallet_stmt->bind_param("di", $winnings, $bet['user_id']);
                    $wallet_stmt->execute();
    
                    if ($wallet_stmt->affected_rows === 0) {
                        throw new Exception('Failed to update user wallet');
                    }
    
                    // Update bet status and winnings
                    $bet_stmt = $conn->prepare("
                        UPDATE cricket_bets 
                        SET status = 'won', 
                            winnings = ? 
                        WHERE id = ? AND status = 'pending'
                    ");
                    $bet_stmt->bind_param("di", $winnings, $bet['id']);
                    $bet_stmt->execute();
                } else {
                    // Mark bet as lost
                    $bet_stmt = $conn->prepare("
                        UPDATE cricket_bets 
                        SET status = 'lost', 
                            winnings = 0 
                        WHERE id = ? AND status = 'pending'
                    ");
                    $bet_stmt->bind_param("i", $bet['id']);
                    $bet_stmt->execute();
                }
            }
    
            $conn->commit();
    
            // Success logging
            error_log("Match $match_id completed successfully. Result: $result");
            error_log("Processed " . count($bets) . " bets");
    
            // Notify Node.js server
            notify_node_server('matchEnded', [
                'matchId' => $match_id,
                'result' => $result,
                'fullTargetResult' => $full_target_result,
                'sixOverTargetResult' => $six_over_target_result
            ]);
    
            // Redirect back with success message
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=match_ended");
            exit;
    
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error processing match $match_id: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($e->getMessage()));
            exit;
        }
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
    let isRefreshing = false;

    // Handle create match form
    $('form[name="create_match"]').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                alert('Match created successfully!');
                // Clear form
                e.target.reset();
                // Refresh page to show new match
                location.reload();
            },
            error: function() {
                alert('Error creating match');
            }
        });
    });

    // Handle multipliers form submission
    $('.multipliers-form').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        
        $.ajax({
            url: '?action=update_multipliers',
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    alert('Multipliers updated successfully');
                    location.reload();
                } else {
                    alert('Error updating multipliers');
                }
            }
        });
    });

    // Handle lock form submission
    $('.lock-form').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        
        $.ajax({
            url: '?action=toggle_lock',
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error toggling lock status');
                }
            }
        });
    });

    // Handle teams form submission
    $('.teams-form').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        
        if (form.find('input[name="teams[]"]').length < 2) {
            alert('Minimum two teams are required');
            return;
        }

        $.ajax({
            url: '?action=update_teams',
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error updating teams');
                }
            }
        });
    });

    // End match form submission
    $('.end-match-form').submit(function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to end this match? This action cannot be undone.')) {
            return;
        }

        const form = $(this);
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                alert('Match ended successfully');
                location.reload();
            },
            error: function() {
                alert('Error ending match');
            }
        });
    });

    // Helper functions for team management
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
            alert('Minimum two teams are required');
        }
    };
});

// End match form submission
$('.end-match-form').submit(function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitButton = form.find('button[type="submit"]');
    
    if (submitButton.prop('disabled')) {
        return false;
    }
    
    if (!confirm('Are you sure you want to end this match? This action cannot be undone.')) {
        return false;
    }
    
    // Disable the submit button
    submitButton.prop('disabled', true);
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: form.serialize(),
        success: function(response) {
            alert('Match ended successfully');
            location.reload();
        },
        error: function() {
            alert('Error ending match');
            submitButton.prop('disabled', false);
        }
    });
});
</script>
</body>
</html>