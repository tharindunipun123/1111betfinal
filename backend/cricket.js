const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
const cors = require('cors');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

app.use(express.json());
app.use(cors());

// Database connection pool
const db = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'spin_wheel_db',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Socket connection
io.on('connection', (socket) => {
  console.log('New client connected');

  socket.on('disconnect', () => {
    console.log('Client disconnected');
  });
});

// Get all matches
// Update the matches endpoint query
app.get('/matches', async (req, res) => {
  try {
    const [rows] = await db.query(`
      SELECT m.*, mt.team_name as team1, 
        (SELECT team_name 
         FROM match_teams 
         WHERE match_id = m.id 
         AND id > mt.id 
         LIMIT 1) as team2
      FROM cricket_matches m
      LEFT JOIN match_teams mt ON m.id = mt.match_id
      WHERE mt.id IN (
        SELECT MIN(id) 
        FROM match_teams 
        GROUP BY match_id
      )
      ORDER BY m.match_time DESC
    `);
    
    // Format the data
    const formattedRows = rows.map(row => ({
      ...row,
      team1: row.team1 || 'Team 1',  // Provide default values
      team2: row.team2 || 'Team 2'
    }));

    res.json(formattedRows);
  } catch (error) {
    console.error('Error fetching matches:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// Place a bet
app.post('/place-bet', async (req, res) => {
  const { userId, matchId, betType, amount } = req.body;
  
  try {
    // Get match details and check locks
    const [matchRows] = await db.query(`
      SELECT 
        is_locked, 
        full_target_locked,
        six_over_target_locked,
        team1, team2,
        CASE
          WHEN ? IN ('team1_win', 'team2_win', 'draw') THEN CONCAT(?, '_multiplier')
          WHEN ? IN ('full_target_yes', 'full_target_no') THEN CONCAT('full_target_multiplier_', SUBSTRING(?, 12))
          WHEN ? IN ('six_over_target_yes', 'six_over_target_no') THEN CONCAT('six_over_target_multiplier_', SUBSTRING(?, 16))
        END as multiplier_col
      FROM cricket_matches 
      WHERE id = ?
    `, [betType, betType, betType, betType, betType, betType, matchId]);
    
    if (matchRows.length === 0) {
      return res.status(400).json({ message: 'Match not found' });
    }

    const match = matchRows[0];

    // Check if betting is locked for the specific bet type
    if ((betType.includes('team') || betType === 'draw') && match.is_locked) {
      return res.status(400).json({ message: 'Match betting is locked' });
    }
    if (betType.includes('full_target') && match.full_target_locked) {
      return res.status(400).json({ message: 'Full target betting is locked' });
    }
    if (betType.includes('six_over_target') && match.six_over_target_locked) {
      return res.status(400).json({ message: 'Six over target betting is locked' });
    }

    // Get the multiplier value
    const [multiplierResult] = await db.query(
      `SELECT ${match.multiplier_col} as multiplier FROM cricket_matches WHERE id = ?`,
      [matchId]
    );
    const multiplier = multiplierResult[0].multiplier;

    // Check user's wallet balance
    const [userRows] = await db.query('SELECT wallet FROM users WHERE id = ?', [userId]);
    if (userRows.length === 0 || userRows[0].wallet < amount) {
      return res.status(400).json({ message: 'Insufficient funds' });
    }

    // Start transaction
    await db.query('START TRANSACTION');

    // Deduct amount from user's wallet
    await db.query('UPDATE users SET wallet = wallet - ? WHERE id = ?', [amount, userId]);

    // Place the bet
    await db.query('INSERT INTO cricket_bets (user_id, match_id, bet_type, amount, multiplier) VALUES (?, ?, ?, ?, ?)',
      [userId, matchId, betType, amount, multiplier]);

    // Commit transaction
    await db.query('COMMIT');

    res.json({ message: 'Bet placed successfully' });
  } catch (error) {
    await db.query('ROLLBACK');
    console.error('Error placing bet:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

app.get('/wallet', async (req, res) => {
  const { user_id } = req.query;
  try {
    const [users] = await db.query('SELECT wallet FROM users WHERE id = ?', [user_id]);
    if (users.length === 0) {
      return res.status(404).json({ message: 'User not found' });
    }
    res.json({ wallet: users[0].wallet });
  } catch (error) {
    console.error('Error fetching wallet balance:', error);
    res.status(500).json({ message: 'Error fetching wallet balance' });
  }
});

app.get('/bet-history', async (req, res) => {
  const { user_id } = req.query;
  try {
    const [bets] = await db.query(`
      SELECT 
        cb.*,
        m.match_time,
        m.result as match_result,
        m.full_target_result,
        m.six_over_target_result,
        (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1) as team1,
        (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1, 1) as team2
      FROM cricket_bets cb
      JOIN cricket_matches m ON cb.match_id = m.id
      WHERE cb.user_id = ?
      ORDER BY m.match_time DESC
    `, [user_id]);
    
    const betHistory = bets.map(bet => ({
      id: bet.id,
      match_details: `${bet.team1} vs ${bet.team2} (${new Date(bet.match_time).toLocaleString()})`,
      bet_type: bet.bet_type,
      amount: parseFloat(bet.amount).toFixed(2),
      status: bet.status,
      winnings: bet.status === 'won' ? parseFloat(bet.winnings).toFixed(2) : '0.00',
      team1: bet.team1,
      team2: bet.team2,
      full_target_result: bet.full_target_result,
      six_over_target_result: bet.six_over_target_result
    }));

    res.json(betHistory);
  } catch (error) {
    console.error('Error fetching bet history:', error);
    res.status(500).json({ message: 'Error fetching bet history' });
  }
});

// app.post('/end-match', async (req, res) => {
//   const { matchId, result, fullTargetResult, sixOverTargetResult } = req.body;

//   try {
//     await db.query('START TRANSACTION');

//     // Update match status and results
//     await db.query(`
//       UPDATE cricket_matches 
//       SET status = "completed", 
//           result = ?,
//           full_target_result = ?,
//           six_over_target_result = ?
//       WHERE id = ?
//     `, [result, fullTargetResult, sixOverTargetResult, matchId]);

//     // Get all bets for this match with their original multipliers
//     const [bets] = await db.query(`
//       SELECT cb.*, 
//              cb.multiplier as original_multiplier,
//              cb.amount as bet_amount,
//              cb.user_id as better_id
//       FROM cricket_bets cb 
//       WHERE cb.match_id = ? AND cb.status = 'pending'
//     `, [matchId]);

//     for (const bet of bets) {
//       let isWinner = false;

//       // Determine if bet is winner based on bet type
//       if (bet.bet_type.includes('team') || bet.bet_type === 'draw') {
//         isWinner = bet.bet_type === result;
//       } else if (bet.bet_type.includes('full_target')) {
//         isWinner = (bet.bet_type === 'full_target_yes' && fullTargetResult === 'yes') ||
//                   (bet.bet_type === 'full_target_no' && fullTargetResult === 'no');
//       } else if (bet.bet_type.includes('six_over_target')) {
//         isWinner = (bet.bet_type === 'six_over_target_yes' && sixOverTargetResult === 'yes') ||
//                   (bet.bet_type === 'six_over_target_no' && sixOverTargetResult === 'no');
//       }

//       if (isWinner) {
//         // Calculate winnings including original bet amount
//         const winnings = parseFloat(bet.bet_amount) * parseFloat(bet.original_multiplier);
        
//         // Update user wallet - add winnings
//         await db.query('UPDATE users SET wallet = wallet + ? WHERE id = ?', 
//           [winnings, bet.better_id]);
        
//         // Update bet status and winnings
//         await db.query('UPDATE cricket_bets SET status = "won", winnings = ? WHERE id = ?', 
//           [winnings, bet.id]);
        
//         console.log(`User ${bet.better_id} won ${winnings} on bet ${bet.id}`);
//       } else {
//         // Update bet status to lost
//         await db.query('UPDATE cricket_bets SET status = "lost", winnings = 0 WHERE id = ?', 
//           [bet.id]);
//       }
//     }

//     await db.query('COMMIT');

//     // Get updated match data for notification
//     const [updatedMatch] = await db.query(`
//       SELECT m.*, 
//         (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1) as team1,
//         (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1, 1) as team2
//       FROM cricket_matches m
//       WHERE m.id = ?
//     `, [matchId]);

//     // Notify clients about the match end
//     io.emit('matchEnded', { 
//       matchId, 
//       result,
//       fullTargetResult,
//       sixOverTargetResult,
//       match: updatedMatch[0]
//     });

//     res.json({ 
//       success: true, 
//       message: 'Match ended and bets processed successfully'
//     });
//   } catch (error) {
//     await db.query('ROLLBACK');
//     console.error('Error ending match:', error);
//     res.status(500).json({ 
//       success: false, 
//       message: 'Internal server error',
//       error: error.message 
//     });
//   }
// });

app.post('/end-match', async (req, res) => {
  const { matchId, result, fullTargetResult, sixOverTargetResult } = req.body;

  try {
    await db.query('START TRANSACTION');

    // Update match status and results
    await db.query(`
      UPDATE cricket_matches 
      SET status = "completed", 
          result = ?,
          full_target_result = ?,
          six_over_target_result = ?
      WHERE id = ? AND status != 'completed'
    `, [result, fullTargetResult, sixOverTargetResult, matchId]);

    // Get all pending bets with their details
    const [bets] = await db.query(`
      SELECT cb.*, 
             cb.multiplier as original_multiplier,
             cb.amount as bet_amount,
             cb.user_id as better_id,
             u.wallet as current_wallet
      FROM cricket_bets cb 
      JOIN users u ON cb.user_id = u.id
      WHERE cb.match_id = ? AND cb.status = 'pending'
    `, [matchId]);

    for (const bet of bets) {
      let isWinner = false;

      // Determine if bet is winner based on bet type
      if (bet.bet_type.includes('team') || bet.bet_type === 'draw') {
        isWinner = bet.bet_type === result;
      } else if (bet.bet_type.includes('full_target')) {
        isWinner = (bet.bet_type === 'full_target_yes' && fullTargetResult === 'yes') ||
                  (bet.bet_type === 'full_target_no' && fullTargetResult === 'no');
      } else if (bet.bet_type.includes('six_over_target')) {
        isWinner = (bet.bet_type === 'six_over_target_yes' && sixOverTargetResult === 'yes') ||
                  (bet.bet_type === 'six_over_target_no' && sixOverTargetResult === 'no');
      }

      if (isWinner) {
        // Calculate winnings using precise decimal arithmetic
        const betAmount = parseFloat(bet.bet_amount);
        const multiplier = parseFloat(bet.original_multiplier);
        const winnings = Math.round((betAmount * multiplier) * 100) / 100;
        
        // Update user wallet with optimistic locking
        const [updateResult] = await db.query(
          'UPDATE users SET wallet = wallet + ? WHERE id = ? AND wallet = ?', 
          [winnings, bet.better_id, bet.current_wallet]
        );

        if (updateResult.affectedRows === 0) {
          throw new Error(`Wallet update failed for user ${bet.better_id}`);
        }
        
        // Update bet status and winnings
        await db.query(
          'UPDATE cricket_bets SET status = "won", winnings = ? WHERE id = ? AND status = "pending"', 
          [winnings, bet.id]
        );
        
        console.log(`User ${bet.better_id} won ${winnings} on bet ${bet.id}`);
      } else {
        // Update bet status to lost
        await db.query(
          'UPDATE cricket_bets SET status = "lost", winnings = 0 WHERE id = ? AND status = "pending"', 
          [bet.id]
        );
      }
    }

    // Get updated match data for notification
    const [updatedMatch] = await db.query(`
      SELECT m.*, 
        (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1) as team1,
        (SELECT team_name FROM match_teams WHERE match_id = m.id ORDER BY id LIMIT 1, 1) as team2
      FROM cricket_matches m
      WHERE m.id = ?
    `, [matchId]);

    await db.query('COMMIT');

    // Notify clients about the match end
    io.emit('matchEnded', { 
      matchId, 
      result,
      fullTargetResult,
      sixOverTargetResult,
      match: updatedMatch[0]
    });

    res.json({ 
      success: true, 
      message: 'Match ended and bets processed successfully'
    });
  } catch (error) {
    await db.query('ROLLBACK');
    console.error('Error ending match:', error);
    res.status(500).json({ 
      success: false, 
      message: 'Internal server error',
      error: error.message 
    });
  }
});

// Admin: Lock/Unlock betting options
app.post('/toggle-lock', async (req, res) => {
  const { matchId, lockType, isLocked } = req.body;

  try {
    let updateQuery;
    switch (lockType) {
      case 'match':
        updateQuery = 'UPDATE cricket_matches SET is_locked = ? WHERE id = ?';
        break;
      case 'full_target':
        updateQuery = 'UPDATE cricket_matches SET full_target_locked = ? WHERE id = ?';
        break;
      case 'six_over_target':
        updateQuery = 'UPDATE cricket_matches SET six_over_target_locked = ? WHERE id = ?';
        break;
      default:
        return res.status(400).json({ message: 'Invalid lock type' });
    }

    await db.query(updateQuery, [isLocked, matchId]);
    
    // Fetch updated match data
    const [updatedMatch] = await db.query(`
      SELECT * FROM cricket_matches WHERE id = ?
    `, [matchId]);
    
    // Notify clients about the match update
    io.emit('matchUpdate', updatedMatch[0]);

    res.json({ message: `${lockType} ${isLocked ? 'locked' : 'unlocked'} successfully` });
  } catch (error) {
    console.error('Error toggling lock:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// Admin: Update multipliers
app.post('/update-multipliers', async (req, res) => {
  const { 
    matchId, 
    team1Multiplier,
    team2Multiplier,
    drawMultiplier,
    fullTargetYesMultiplier,
    fullTargetNoMultiplier,
    sixOverTargetYesMultiplier,
    sixOverTargetNoMultiplier
  } = req.body;

  try {
    await db.query(`
      UPDATE cricket_matches 
      SET team1_win_multiplier = ?,
          team2_win_multiplier = ?,
          draw_multiplier = ?,
          full_target_multiplier_yes = ?,
          full_target_multiplier_no = ?,
          six_over_target_multiplier_yes = ?,
          six_over_target_multiplier_no = ?
      WHERE id = ?
    `, [
      team1Multiplier,
      team2Multiplier,
      drawMultiplier,
      fullTargetYesMultiplier,
      fullTargetNoMultiplier,
      sixOverTargetYesMultiplier,
      sixOverTargetNoMultiplier,
      matchId
    ]);

    // Fetch updated match data
    const [updatedMatch] = await db.query(`SELECT * FROM cricket_matches WHERE id = ?`, [matchId]);
    
    // Notify clients about the match update
    io.emit('matchUpdate', updatedMatch[0]);

    res.json({ message: 'Multipliers updated successfully' });
  } catch (error) {
    console.error('Error updating multipliers:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

const PORT = process.env.PORT || 3030;
server.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});