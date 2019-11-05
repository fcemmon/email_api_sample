<?php
  try {
    $flags = array(
      PDO::ATTR_PERSISTENT => FALSE,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
    );
    
    $dsn = 'mysql:host=localhost;dbname=tcrm_beta';
    // $username = 'tcrm_admin';
    // $password = 'TR@nBq@)Ju8s';
    $username = 'root';
    $password = 'mysql';
    $db = new PDO($dsn, $username, $password, $flags);
  } catch(exception $e) {
    $db->rollBack();
    http_response_code(500);
    error_log("DB Connect Failed: ".$e->getMessage());
    exit(1);
  }

  function db_verify_last_operation() {
    global $db;
    
    $e = $db->errorInfo();

    if ($e[0] != '00000' || $e[1]) {
      return array(
        "state" => $e[0],
        "code" => $e[1],
        "db_error" => $e[2]
      );
    } else {
      return true;
    }
  }
?>