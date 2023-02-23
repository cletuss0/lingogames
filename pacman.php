<?php
require 'db.php';
header("Set-Cookie: cross-site-cookie=whatever; SameSite=None; Secure");
session_set_cookie_params(0, '/', '', true, true);
session_start();

if (isset($_POST['new_score'])) {
  update_jackpot_and_give_jackpot_to_player($conn);  
}

function update_jackpot_and_give_jackpot_to_player($conn) {
  $game_id = 'pacman';
  $jackpotAmount = retrieve_jackpot_amount($conn);
  $score = get_score($conn);
  $new_score = $_POST['new_score'];
  $winner = $_SESSION["wallet"];

  // Update the jackpot amount
  $total_jackpot = $jackpotAmount;
  $jackpotAmount = $jackpotAmount * $_SESSION['amount']/2;


  // Check if the player's score is higher than the current jackpot score
  if ($new_score > $score) {
    // Update the jackpot amount
    $sql = "UPDATE jackpots SET winner=?, time=NOW() WHERE game_id=? AND winner='SaferTheBoss'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $winner, $game_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Give the jackpot to the player
    $wallet = $_SESSION['wallet'];
    $sql = "UPDATE players SET lingo=lingo+? WHERE wallet=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $jackpotAmount, $wallet);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $new_jackpot = $total_jackpot - $jackpotAmount;
    if ($new_jackpot > 50) {
      create_jackpot($conn, $game_id, $new_score, $new_jackpot);
    }else{create_jackpot($conn, $game_id, $new_score, 50);}

    echo 'new_high_score';
  } else {
    echo 'lost';
  }
}

function create_jackpot($conn, $game_id, $score, $jackpot) {
  $sql = "INSERT INTO jackpots (game_id, score, amount) VALUES (?, ?, ?)";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "sdd", $game_id, $score, $jackpot);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
}

// Handle the AJAX request
if (isset($_POST['action']) && $_POST['action'] === 'update-gold-and-lingo') {
  $goldAmount = get_player_gold($conn);
  $lingoAmount = get_player_lingo($conn);
  $jackpotAmount = retrieve_jackpot_amount($conn);
  $score = get_score($conn);

  $response = array(
      'gold' => $goldAmount,
      'lingo' => $lingoAmount,
      'jackpot' => $jackpotAmount,
      'score' => $score
  );
  header('Content-Type: application/json');
  echo json_encode($response);
  exit;
}

function get_player_gold($conn)
{
  $stmt = mysqli_prepare($conn, "SELECT gold FROM players WHERE wallet = ?");
  mysqli_stmt_bind_param($stmt, "s", $wallet);
  $wallet = $_SESSION['wallet'];
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $goldAmount);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);

  return $goldAmount ?? 0;
}

function get_score($conn)
{
  $stmt = mysqli_prepare($conn, "SELECT score FROM jackpots WHERE game_id = ? AND winner='SaferTheBoss'");
  mysqli_stmt_bind_param($stmt, "s", $game_id);
  $game_id = 'pacman';
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $score);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);

  return $score ?? 0;
}

function get_player_lingo($conn)
{
  $stmt = mysqli_prepare($conn, "SELECT lingo FROM players WHERE wallet = ?");
  mysqli_stmt_bind_param($stmt, "s", $wallet);
  $wallet = $_SESSION['wallet'];
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $lingoAmount);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);

  return $lingoAmount ?? 0;
}

function retrieve_jackpot_amount($conn)
{
    $stmt = mysqli_prepare($conn, "SELECT amount FROM jackpots WHERE game_id = ? AND winner='SaferTheBoss'");
    mysqli_stmt_bind_param($stmt, "s", $game_id);
    $game_id = 'pacman';
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $jackpotAmount);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $jackpotAmount ?? 0;
}


if (isset($_POST['text'])) {
    $_SESSION['wallet'] = filter_var($_POST['text'], FILTER_SANITIZE_STRING);
    $wallet = $_SESSION['wallet'];

    $stmt = mysqli_prepare($conn, "INSERT INTO players (wallet) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $wallet); 
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    retrieve_jackpot_amount($conn);

    $stmt2 = mysqli_prepare($conn, "SELECT gold, lingo FROM players WHERE wallet=?");
    mysqli_stmt_bind_param($stmt2, "s", $wallet);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_bind_result($stmt2, $goldAmount, $lingoAmount);
    mysqli_stmt_fetch($stmt2);
    mysqli_stmt_close($stmt2);
    $_SESSION['goldAmount'] = $goldAmount;
    $_SESSION['lingoAmount'] = $lingoAmount;
}

if (isset($_POST['updategold']) || isset($_POST['updatelingo'])) {
  $column = isset($_POST['updategold']) ? 'gold' : 'lingo';
  $wallet = filter_input(INPUT_POST, 'wallet', FILTER_SANITIZE_STRING);
  $newValue = filter_input(INPUT_POST, 'update'.$column, FILTER_VALIDATE_INT);
  
  if ($wallet === null || $newValue === false) {
    $response = array('success' => false, 'message' => 'Invalid input');
    echo json_encode($response);
    exit;
  }

  $table = 'players';
  $column = mysqli_real_escape_string($conn, $column);
  $table = mysqli_real_escape_string($conn, $table);

  $sql = "UPDATE $table SET $column = $column + ? WHERE wallet=?";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "ds", $newValue, $wallet);

  if (mysqli_stmt_execute($stmt)) {
    $response = array('success' => true, 'message' => ucfirst($column).' updated in database successfully');
    echo '<script>startNewGame();</script>';
  } else {
    $response = array('success' => false, 'message' => mysqli_error($conn));
  }
  
  mysqli_stmt_close($stmt);
  echo json_encode($response);
  exit;
}

// Update gold or lingo amount
if (isset($_POST['goldamount']) || isset($_POST['lingoamount'])) {
  if ($_POST['goldamount'] = '' ) {
    $lingo = $_POST['lingoamount'];
    $gold = 0;
  }else {$gold = $_POST['goldamount'];
    $lingo = 0;}
  
  $column = isset($_POST['goldamount']) ? 'gold' : 'lingo';
  $wallet = filter_input(INPUT_POST, 'wallet', FILTER_SANITIZE_STRING);
  $amount = filter_input(INPUT_POST, $column.'amount', FILTER_VALIDATE_FLOAT);
  
  if ($wallet === null || $amount === false) {
    echo "Error: invalid input";
    exit;
  }
  
  $stmt = mysqli_prepare($conn, "SELECT $column FROM players WHERE wallet=?");
  mysqli_stmt_bind_param($stmt, "s", $wallet);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_assoc($result);
  $balance = $row[$column];
  
  if ($amount >= 0.10 && $amount <= 2 && $amount <= $balance) {
    $_SESSION['amount'] = $amount;
    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "UPDATE jackpots SET amount = amount + ? WHERE game_id='pacman' AND winner='SaferTheBoss'");
    mysqli_stmt_bind_param($stmt, "d", $amount);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, "UPDATE players SET $column = $column - ? WHERE wallet=?");
    mysqli_stmt_bind_param($stmt, "ds", $amount, $wallet);
    mysqli_stmt_execute($stmt);

    $sql = "INSERT INTO bets (amount, lingo, gold, wallet) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ddds", $amount, $lingo, $gold, $wallet);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);

    echo "SUCCESS";
} else {
    echo "You don't have enough money";
}
  
  mysqli_close($conn);
  exit;
}

?>


<html>
<head>
    <link rel="shortcut icon" href="#"/>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/ethereum/web3.js/dist/web3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.8.2/dist/web3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    <script src="https://cdn.ethers.io/lib/ethers-5.0.umd.min.js" type="application/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.3.0/dist/web3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash"></script>
    <script>const web3 = new Web3(Web3.givenProvider || 'http://localhost:8545');</script>
</head>

<body>
    <link rel="stylesheet" href="pacman.css">
<div id="shim">shim for font face</div>

<h1>Lingo Games Pacman</h1>
<div class="col">
    <button id='loginButton' class="metamask">Login with MetaMask</button>
    <button id="deposite_lingo">Deposite $Lingo</button>
    <button id="deposite_gold">Deposite $Gold</button>
        <h1 id='userWallet' class='text-lg text-gray-600 my-2'></h1>
</div>
<div id="money" class="container">
    <div class="row">
      <div class="col">
        <button id="new-game-button">Play with $Gold</button>
      </div>
      <div class="col">
        <button id="new-game-button2">Play with $Lingo</button>
      </div>
      <div class="col">
        <button id="new-game-button3">Play for free</button>
      </div>
      <div class="col">
        <div class="range">
          <input type="range" class="range-input" min="0.10" max="2.00" step="0.01" value="1.05">
        </div>  
      </div>
      <div class="col">
        <div class="range">
          <h1 id="bet-value">$1.05</h1>
        </div>  
      </div>
    </div>
  </div>  
<div id="pacman"></div>
<h1 id="pausegame">Press P to pause</h1>
<h1 id="jackpot"></h1>
<h1 id="gold"></h1>
<h1 id="lingo"></h1>
<h1 id="score"></h1>
<h1 id="token-balance"></h1>
<script type="module" src="pacman.js"></script>
</body>
</html>
