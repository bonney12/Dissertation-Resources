<?php

/**
 * AssignOccupationCategory Script - CWPC
 *
 * This script interprets the occupation from the CWPC dataset and replaces it with a numerical value.
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
echo "// Executing AssignOccupationCategory - CWPC\n";
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
$jsoc = $pdo->query('SELECT * FROM `JSOC`', PDO::FETCH_ASSOC);

// Check if rows could be retrieved and if not, error
if($jsoc->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved from JSOC. Does the table exist?";
  exit();
}

echo "[INFO] [ROWS] Occupational categories retrieved successfully. Row count: " . $jsoc->rowCount() . "\n";

$jsoc = $jsoc->fetchAll();

echo "[INFO] [ROWS] Retrieving occupations from CWPC Dataset...\n";

$dataset = $pdo->query('SELECT `ID`, `Speaker_Occupation`, `Interlocutor_Occupation` FROM `CWPC Dataset`');

// Check if rows could be retrieved and if not, error
if($dataset->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved from CWPC Dataset. Does the table exist?";
  exit();
}

foreach($dataset AS $result) {
  $id = $result['ID'];

  $occupations = [
    'speaker' => $result['Speaker_Occupation'],
    'interlocutor' => $result['Interlocutor_Occupation']
  ];

  $newOccupations = [
    'speaker' => [],
    'interlocutor' => []
  ];

  foreach($occupations AS $key => $occupation) {

    $occupationDetailsKey = array_search($occupation, array_column($jsoc, 'Role'));

    if(!is_numeric($occupationDetailsKey)) {
      echo "[ERROR] [ROWS] Row " . $id . " did not have a discoverable occupation (" . $occupation . "). Exiting...\n";
      exit();
    }

    $identifiedOccupation = $jsoc[$occupationDetailsKey];

    $newOccupations[$key] = [
      'major' => $identifiedOccupation['MajorCategory'],
      'minor' => $identifiedOccupation['MinorCategory'],
      'num'   => $identifiedOccupation['AssignedNumber']
    ];

  }

  echo "[INFO] [ROWS] Updating row...\n";

  $rowsForTalkQuery = $pdo->prepare('UPDATE `CWPC Dataset` SET `Speaker_Occupation_MajorCategory` = ?, `Speaker_Occupation_MinorCategory` = ?, `Speaker_Occupation_AssignedNumber` = ?, `Interlocutor_Occupation_MajorCategory` = ?, `Interlocutor_Occupation_MinorCategory` = ?, `Interlocutor_Occupation_AssignedNumber` = ? WHERE `ID` = ?');
  $rowsForTalkQuery->execute([
    $newOccupations['speaker']['major'],
    $newOccupations['speaker']['minor'],
    $newOccupations['speaker']['num'],
    $newOccupations['interlocutor']['major'],
    $newOccupations['interlocutor']['minor'],
    $newOccupations['interlocutor']['num'],
    $id
  ]);

  echo "[INFO] [ROWS] Row updated successfully.\n";
}

echo "[INFO] [ROWS] Completed successfully.\n";

?>
