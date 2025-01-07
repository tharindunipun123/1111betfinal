<?php
session_start();
require_once 'db_connection.php';

// Check if $conn is set
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check your configuration.");
}

// WebSocket server URL
$websocket_url = 'ws://localhost:3008';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'update_multipliers':
            $match_id = $_POST['match_id'];
            $team1_win_multiplier = $_POST['team1_win_multiplier'];
            $team2_win_multiplier = $_POST['team2_win_multiplier'];
            $draw_multiplier = $_POST['draw_multiplier'];

            $stmt = $conn->prepare("UPDATE cricket_matches SET team1_win_multiplier = ?, team2_win_multiplier = ?, draw_multiplier = ? WHERE id = ?");
            $stmt->bind_param("dddi", $team1_win_multiplier, $team2_win_multiplier, $draw_multiplier, $match_id);
            $result = $stmt->execute();

            if ($result) {
                // Fetch updated match data
                $stmt = $conn->prepare("SELECT * FROM cricket_matches WHERE id = ?");
                $stmt->bind_param("i", $match_id);
                $stmt->execute();
                $updated_match = $stmt->get_result()->fetch_assoc();

                // Notify clients about the match update via WebSocket
                $ch = curl_init('http://localhost:3008/notify');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event' => 'matchUpdate', 'data' => $updated_match]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }

            echo json_encode(['success' => $result]);
            exit;
            case 'lock_match':
                $match_id = $_POST['match_id'];
                $is_locked = $_POST['is_locked'];
    
                $stmt = $conn->prepare("UPDATE cricket_matches SET is_locked = ? WHERE id = ?");
                $stmt->bind_param("ii", $is_locked, $match_id);
                $result = $stmt->execute();
    
                if ($result) {
                    // Fetch updated match data
                    $stmt = $conn->prepare("SELECT * FROM cricket_matches WHERE id = ?");
                    $stmt->bind_param("i", $match_id);
                    $stmt->execute();
                    $updated_match = $stmt->get_result()->fetch_assoc();
    
                    // Notify clients about the match update via WebSocket
                    $ch = curl_init('http://localhost:3008/notify');
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event' => 'matchUpdate', 'data' => $updated_match]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }
    
                echo json_encode(['success' => $result]);
                exit;
    
            case 'get_matches':
                $result = $conn->query("SELECT * FROM cricket_matches ORDER BY match_time DESC");
                $matches = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($matches);
                exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_match'])) {
        $team1 = $_POST['team1'];
        $team2 = $_POST['team2'];
        $match_time = $_POST['match_time'];
        $facebook_live_link = $_POST['facebook_live_link'];
        $team1_win_multiplier = $_POST['team1_win_multiplier'];
        $team2_win_multiplier = $_POST['team2_win_multiplier'];
        $draw_multiplier = $_POST['draw_multiplier'];

        $stmt = $conn->prepare("INSERT INTO cricket_matches (team1, team2, match_time, facebook_live_link, team1_win_multiplier, team2_win_multiplier, draw_multiplier) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssddd", $team1, $team2, $match_time, $facebook_live_link, $team1_win_multiplier, $team2_win_multiplier, $draw_multiplier);
        $stmt->execute();

        // Notify clients about the new match via WebSocket
        $new_match_id = $conn->insert_id;
        $stmt = $conn->prepare("SELECT * FROM cricket_matches WHERE id = ?");
        $stmt->bind_param("i", $new_match_id);
        $stmt->execute();
        $new_match = $stmt->get_result()->fetch_assoc();

        $ch = curl_init('http://localhost:3008/notify');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event' => 'newMatch', 'data' => $new_match]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    } elseif (isset($_POST['end_match'])) {
        $match_id = $_POST['match_id'];
        $result = $_POST['result'];

        $conn->begin_transaction();

        try {
            // Update match status
            $stmt = $conn->prepare("UPDATE cricket_matches SET status = 'completed', result = ? WHERE id = ?");
            $stmt->bind_param("si", $result, $match_id);
            $stmt->execute();

            // Process bets
            $stmt = $conn->prepare("SELECT * FROM cricket_bets WHERE match_id = ?");
            $stmt->bind_param("i", $match_id);
            $stmt->execute();
            $bets_result = $stmt->get_result();
            $bets = $bets_result->fetch_all(MYSQLI_ASSOC);

            foreach ($bets as $bet) {
                if ($bet['bet_type'] === $result) {
                    $winnings = $bet['amount'] * $bet['multiplier'];
                    $stmt = $conn->prepare("UPDATE users SET wallet = wallet + ? WHERE id = ?");
                    $stmt->bind_param("di", $winnings, $bet['user_id']);
                    $stmt->execute();
                    
                    $stmt = $conn->prepare("UPDATE cricket_bets SET status = 'won', winnings = ? WHERE id = ?");
                    $stmt->bind_param("di", $winnings, $bet['id']);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("UPDATE cricket_bets SET status = 'lost' WHERE id = ?");
                    $stmt->bind_param("i", $bet['id']);
                    $stmt->execute();
                }
            }

            $conn->commit();

            // Notify clients about the match end via WebSocket
            $ch = curl_init('http://localhost:3008/notify');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event' => 'matchEnded', 'data' => ['matchId' => $match_id, 'result' => $result]]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            $conn->rollback();
            die("Error processing match end: " . $e->getMessage());
        }
    }
}

// Fetch all matches
$result = $conn->query("SELECT * FROM cricket_matches ORDER BY match_time DESC");
$matches = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Betting Admin Panel</title>
    <style>
          body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            overflow: hidden;
            padding: 0 20px;
        }
        h1, h2 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        input[type="text"], input[type="url"], input[type="datetime-local"], input[type="number"], select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }
        button {
            display: inline-block;
            background: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #555;
        }
        .match {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cricket Betting Admin Panel</h1>
        
        <h2>Create New Match</h2>
        <form method="POST">
            <input type="text" name="team1" placeholder="Team 1" required>
            <input type="text" name="team2" placeholder="Team 2" required>
            <input type="datetime-local" name="match_time" required>
            <input type="url" name="facebook_live_link" placeholder="Facebook Live Link">
            <input type="number" step="0.01" name="team1_win_multiplier" placeholder="Team 1 Win Multiplier" required>
            <input type="number" step="0.01" name="team2_win_multiplier" placeholder="Team 2 Win Multiplier" required>
            <input type="number" step="0.01" name="draw_multiplier" placeholder="Draw Multiplier" required>
            <button type="submit" name="create_match">Create Match</button>
        </form>

        <h2>Matches</h2>
        <button id="toggle-completed">Toggle Completed Matches</button>
        <div id="matches-container">
            <?php foreach ($matches as $match): ?>
                <div id="match-<?= $match['id'] ?>" class="match <?= $match['status'] === 'completed' ? 'completed' : '' ?>">
                    <h3><?= htmlspecialchars($match['team1']) ?> vs <?= htmlspecialchars($match['team2']) ?></h3>
                    <p>Time: <?= $match['match_time'] ?></p>
                    <p class="status">Status: <?= $match['status'] ?></p>
                    <p class="result">Result: <?= $match['result'] ?></p>
                    
                    <form class="update-multipliers-form">
                        <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                        <input type="number" step="0.01" name="team1_win_multiplier" value="<?= $match['team1_win_multiplier'] ?>" required>
                        <input type="number" step="0.01" name="team2_win_multiplier" value="<?= $match['team2_win_multiplier'] ?>" required>
                        <input type="number" step="0.01" name="draw_multiplier" value="<?= $match['draw_multiplier'] ?>" required>
                        <button type="submit">Update Multipliers</button>
                    </form>

                    <form class="lock-match-form">
                        <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                        <input type="hidden" name="is_locked" value="<?= $match['is_locked'] ? '0' : '1' ?>">
                        <button type="submit"><?= $match['is_locked'] ? 'Unlock' : 'Lock' ?> Match</button>
                    </form>

                    <?php if ($match['status'] !== 'completed'): ?>
                        <form class="end-match-form" method="POST">
                            <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                            <select name="result" required>
                                <option value="">Select Result</option>
                                <option value="<?= $match['team1'] ?>_win"><?= htmlspecialchars($match['team1']) ?> Wins</option>
                                <option value="<?= $match['team2'] ?>_win"><?= htmlspecialchars($match['team2']) ?> Wins</option>
                                <option value="draw">Draw</option>
                            </select>
                            <button type="submit" name="end_match">End Match</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
      const socket = new WebSocket('<?= $websocket_url ?>');

      socket.onopen = function(e) {
      console.log("WebSocket connection established");
                };

   socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    if (data.event === 'matchUpdate') {
        updateMatchDisplay(data.data);
    } else if (data.event === 'newMatch') {
        addNewMatch(data.data);
        }
       };

        function updateMatchDisplay(match) {
            const matchElement = $(`#match-${match.id}`);
            if (matchElement.length) {
                matchElement.find('.status').text(`Status: ${match.status}`);
                matchElement.find('.result').text(`Result: ${match.result}`);
                matchElement.find('[name="team1_win_multiplier"]').val(match.team1_win_multiplier);
                matchElement.find('[name="team2_win_multiplier"]').val(match.team2_win_multiplier);
                matchElement.find('[name="draw_multiplier"]').val(match.draw_multiplier);
                const lockButton = matchElement.find('.lock-match-form button');
                lockButton.text(match.is_locked ? 'Unlock Match' : 'Lock Match');
                lockButton.prev('input[name="is_locked"]').val(match.is_locked ? '0' : '1');
            }
        }

        function addNewMatch(match) {
            const newMatchHtml = `
                <div id="match-${match.id}" class="match">
                    <h3>${match.team1} vs ${match.team2}</h3>
                    <p>Time: ${match.match_time}</p>
                    <p class="status">Status: ${match.status}</p>
                    <p class="result">Result: ${match.result}</p>
                    
                    <form class="update-multipliers-form">
                        <input type="hidden" name="match_id" value="${match.id}">
                        <input type="number" step="0.01" name="team1_win_multiplier" value="${match.team1_win_multiplier}" required>
                        <input type="number" step="0.01" name="team2_win_multiplier" value="${match.team2_win_multiplier}" required>
                        <input type="number" step="0.01" name="draw_multiplier" value="${match.draw_multiplier}" required>
                        <button type="submit">Update Multipliers</button>
                    </form>

                    <form class="lock-match-form">
                        <input type="hidden" name="match_id" value="${match.id}">
                        <input type="hidden" name="is_locked" value="${match.is_locked ? '0' : '1'}">
                        <button type="submit">${match.is_locked ? 'Unlock' : 'Lock'} Match</button>
                    </form>

                    <form class="end-match-form" method="POST">
                        <input type="hidden" name="match_id" value="${match.id}">
                        <select name="result" required>
                            <option value="">Select Result</option>
                            <option value="${match.team1}_win">${match.team1} Wins</option>
                            <option value="${match.team2}_win">${match.team2} Wins</option>
                            <option value="draw">Draw</option>
                        </select>
                        <button type="submit" name="end_match">End Match</button>
                    </form>
                </div>
            `;
            $('#matches-container').prepend(newMatchHtml);
        }

        $('.update-multipliers-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                url: '?action=update_multipliers',
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        alert('Multipliers updated successfully');
                    } else {
                        alert('Error updating multipliers');
                    }
                }
            });
        });

        $('.lock-match-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                url: '?action=lock_match',
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        var button = form.find('button');
                        var newText = button.text() === 'Lock Match' ? 'Unlock Match' : 'Lock Match';
                        button.text(newText);
                        form.find('input[name="is_locked"]').val(newText === 'Lock Match' ? '0' : '1');
                        alert('Match ' + (newText === 'Lock Match' ? 'unlocked' : 'locked') + ' successfully');
                    } else {
                        alert('Error changing match lock status');
                    }
                }
            });
        });

        $('.end-match-form').submit(function(e) {
            if (!confirm('Are you sure you want to end this match? This action cannot be undone.')) {
                e.preventDefault();
            }
        });

        $('#toggle-completed').click(function() {
            $('.match.completed').toggle();
        });

        function refreshMatches() {
            $.ajax({
                url: '?action=get_matches',
                type: 'GET',
                success: function(matches) {
                    var container = $('#matches-container');
                    container.empty();
                    matches.forEach(function(match) {
                        var matchHtml = `
                            <div id="match-${match.id}" class="match ${match.status === 'completed' ? 'completed' : ''}">
                                <h3>${match.team1} vs ${match.team2}</h3>
                                <p>Time: ${match.match_time}</p>
                                <p class="status">Status: ${match.status}</p>
                                <p class="result">Result: ${match.result}</p>
                                
                                <form class="update-multipliers-form">
                                    <input type="hidden" name="match_id" value="${match.id}">
                                    <input type="number" step="0.01" name="win_multiplier" value="${match.win_multiplier}" required>
                                    <input type="number" step="0.01" name="loss_multiplier" value="${match.loss_multiplier}" required>
                                    <input type="number" step="0.01" name="draw_multiplier" value="${match.draw_multiplier}" required>
                                    <button type="submit">Update Multipliers</button>
                                </form>

                                <form class="lock-match-form">
                                    <input type="hidden" name="match_id" value="${match.id}">
                                    <input type="hidden" name="is_locked" value="${match.is_locked ? '0' : '1'}">
                                    <button type="submit">${match.is_locked ? 'Unlock' : 'Lock'} Match</button>
                                </form>

                                ${match.status !== 'completed' ? `
                                    <form class="end-match-form" method="POST">
                                        <input type="hidden" name="match_id" value="${match.id}">
                                        <select name="result" required>
                                            <option value="">Select Result</option>
                                            <option value="team1_win">${match.team1} Wins</option>
                                            <option value="team2_win">${match.team2} Wins</option>
                                            <option value="draw">Draw</option>
                                        </select>
                                        <button type="submit" name="end_match">End Match</button>
                                    </form>
                                ` : ''}
                            </div>
                        `;
                        container.append(matchHtml);
                    });
                    
                    // Reattach event handlers
                    $('.update-multipliers-form').submit(function(e) {
                        // ... (same as before)
                    });
                    $('.lock-match-form').submit(function(e) {
                        // ... (same as before)
                    });
                    $('.end-match-form').submit(function(e) {
                        // ... (same as before)
                    });
                }
            });
        }

        // Refresh matches every 5 minutes
        setInterval(refreshMatches, 300000);
    });

    </script>
</body>
</html>