<?php

/**
 * AddInterviewerIntervieweeIDs Script - CSJ
 *
 * This script inserts the ID numbers for the interviewer and interviewee in the CSJ,
 * so that it can be used as a random effect.
 *
 * @author Michael Bonney <michael@mbonney.uk>
 * @copyright cc-by-sa
 */

//
// Database connection
// Edit the lines below as needed to point to the database containing a table with the
// name `CWPC Dataset`. Another table called 'JSOC' should exist, based on the SQL
// script provided.
//
$hostname = '127.0.0.1';
$database = 'ModelDatasets';        // <-- Put your database name here
$username = 'CSJ';                  // <-- Put your database username here
$password = '/g8AO6HoapiwC*]z';     // <-- Put your database password here
$charset  = 'utf8mb4';

//
// WARNING: Do not edit below this line unless familiar with coding.
//

echo "//\n";
echo "// Executing AddInterviewerIntervieweeIDs - CSJ\n";
echo "//\n\n";

// Create DSN
 $dsn = "mysql:host=$hostname;dbname=$database;charset=$charset";

 // Prepare options array for PDO
 $options = [
     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES   => false,
 ];

 // Attempt connection and catch any errors
 try {
   $pdo = new PDO($dsn, $username, $password, $options);
 } catch (\PDOException $e) {
   // Error was caught; throw this.
   echo "[ERROR] [MYSQL] The MySQL connection failed with the following error:\n";
   echo "[ERROR] [MYSQL] " . $e->getMessage() . " (" . $e->getCode() . ")";
   exit();
 }

// Report successful connection to CLI
echo "[INFO] [MYSQL] MySQL connection established.\n";
echo "[INFO] [MYSQL] Retrieving appropriate rows...\n";

// Create query to get TalkIDs
$dialogues = $pdo->query('SELECT * FROM `Dialogues`', PDO::FETCH_ASSOC);

// Check if rows could be retrieved and if not, error
if($dialogues->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved from Dialogues. Does the table exist?";
  exit();
}

echo "[INFO] [ROWS] Dialogues retrieved successfully. Row count: " . $dialogues->rowCount() . "\n";

$dialogues = $dialogues->fetchAll();

echo "[INFO] [ROWS] Retrieving from CSJ Dataset...\n";

$dataset = $pdo->query('SELECT `ID`, `TalkID`, `ClauseID` FROM `CSJ Dataset`');

// Check if rows could be retrieved and if not, error
if($dataset->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved from CSJ Dataset. Does the table exist?";
  exit();
}

foreach($dataset AS $key => $row) {
  $arrayKey = array_search($row['TalkID'], array_column($dialogues, 'Talk_ID'));

  if($arrayKey === false) {
    echo "[ERROR] Could not find dialogue for row " . $row['TalkID'] . '. Exiting...';
    exit();
  }

  $identifiers = $dialogues[$arrayKey];

  // Get end character of ClauseID - this represents the speaker (L always interviewer,
  // R always interviewee - cf. p114 of kokken124 - 日本語話し言葉コーパスの構築法)
  if(substr($row['ClauseID'], -1, 1) == 'L') {
    // Speaker is the interviewer, interlocutor is the interviewee
    $speaker = $identifiers['Interviewer_ID'];
    $interlocutor = $identifiers['Interviewee_ID'];
  } else {
    // Speaker is the interviewee, interlocutor is the interviewer
    $speaker = $identifiers['Interviewee_ID'];
    $interlocutor = $identifiers['Interviewer_ID'];
  }

  $rowsForTalkQuery = $pdo->prepare('UPDATE `CSJ Dataset` SET `SpeakerID` = ?, `InterlocutorID` = ? WHERE `ID` = ?');
  $rowsForTalkQuery->execute([
    $speaker,
    $interlocutor,
    $row['ID']
  ]);

  echo "[INFO] Successfully set row " . $row['ID'] . "'s data (" . $speaker . ", " . $interlocutor . ")\n";
}

echo "[INFO] [ROWS] Completed successfully.\n";

?>
