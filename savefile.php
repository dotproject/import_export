<?php

/******************************
 * First half of the file copied from index.php - checking permissions, login, etc.
 * consider using fileviewer.php (modified accordingly).
 */

// TODO Change this!!!
require "../../includes/config.php";
require "../../classes/ui.class.php";

session_name( 'dotproject' );
if (get_cfg_var( 'session.auto_start' ) > 0) {
        session_write_close();
}
session_start();

// check if session has previously been initialised
// if no ask for logging and do redirect
if (!isset( $_SESSION['AppUI'] ) || isset($_GET['logout'])) {
    $_SESSION['AppUI'] = new CAppUI();
  $AppUI =& $_SESSION['AppUI'];
  $AppUI->setConfig( $dPconfig );
  $AppUI->checkStyle();
       
  require_once( $AppUI->getSystemClass( 'dp' ) );
  require_once( "../../includes/db_connect.php" );
  require_once( "../../includes/main_functions.php" );
  require_once( "../../misc/debug.php" );

if ($AppUI->doLogin()) $AppUI->loadPrefs( 0 );
        // check if the user is trying to log in
        if (isset($_POST['login'])) {
                $username = dPgetParam( $_POST, 'username', '' );
                $password = dPgetParam( $_POST, 'password', '' );
                $redirect = dPgetParam( $_REQUEST, 'redirect', '' );
                $ok = $AppUI->login( $username, $password );
                if (!$ok) {
                        //display login failed message 
                        $uistyle = $AppUI->getPref( 'UISTYLE' ) ? $AppUI->getPref( 'UISTYLE' ) : $AppUI->cfg['host_style'];
                        $AppUI->setMsg( 'Login Failed' );
                        require "../../style/$uistyle/login.php";
                        session_unset();
                        exit;
                }
                header ( "Location: fileviewer.php?$redirect" );
                exit;
        }       

        $uistyle = $AppUI->getPref( 'UISTYLE' ) ? $AppUI->getPref( 'UISTYLE' ) : $AppUI->cfg['host_style'];
        // check if we are logged in
        if ($AppUI->doLogin()) {
            $AppUI->setUserLocale();
                @include_once( "../../locales/$AppUI->user_locale/locales.php" );
                @include_once( "../../locales/core.php" );
                setlocale( LC_TIME, $AppUI->user_locale );
                
                $redirect = @$_SERVER['QUERY_STRING'];
                if (strpos( $redirect, 'logout' ) !== false) $redirect = '';    
                if (isset( $locale_char_set )) header("Content-type: text/html;charset=$locale_char_set");
                require "../../style/$uistyle/login.php";
                session_unset();
                session_destroy();
                exit;
        }       
}
$AppUI =& $_SESSION['AppUI'];

require "{$AppUI->cfg['root_dir']}/includes/db_connect.php";

include "{$AppUI->cfg['root_dir']}/includes/main_functions.php";
include "{$AppUI->cfg['root_dir']}/includes/permissions.php";

$canRead = !getDenyRead( 'backup' );
if (!$canRead) {
        $AppUI->redirect( "m=public&a=access_denied" );
}

// ----------------------------------------------------------
// Backup part starting ...


$file = dPgetParam($_POST, 'sql_file', 'backup'); //'backup.sql';
$file_type = dPgetParam($_POST, 'file_type', '0');
$module = dPgetParam($_POST, 'module', 'all');
$item = dPgetParam($_POST, 'item', '-1');

//Functions

function valuesList($table, $row)
{
  $sql = "INSERT INTO `$table` (";
  $sql .= headerList($row);
  $sql .= ") VALUES(";
  $sql .= bodyList($row);
  $sql .= ");";
  
  return $sql . "\n";
}

function bodyList($row)
{
  global $file_type;

  if ($file_type/2 == 0)
    $q = "'";
  else
    $q = "\"";

  $sql = "";
  foreach($row as $col)
    $sql .= (($col == null)?"NULL":($q . str_replace($q, "\$q", $col) . $q)) . ",";
    // Substitute the entire line for csv if necessary.
  $sql = substr($sql, 0, -1); // remove last comma

  return $sql;
}

function headerList($row)
{
  global $file_type;
  if (empty($row))
    return;

  if ($file_type/2 == 0)
    $q = "`";
  else
    $q = "";

  $sql = "";
  foreach ($row as $key=>$col)
    $sql .= "$q$key$q,";
  $sql = substr($sql, 0, -1);

  return $sql;
}

function tableInsert($table, $keyCol=1, $keyVal=1)
{
  global $file_type;
  $out = "";

  $list = db_loadList("SELECT * FROM $table WHERE $keyCol='$keyVal'");
  if ($file_type == 2)
  {
    $out = headerList($list[0]) . "\n";
    foreach($list as $row)
      $out .= bodyList($row) . "\n";
  }
  else if ($file_type / 2 == 0)
  {
    $out .= "INSERT INTO `$table` (" . headerList($list[0]) . ") VALUES "; 
    foreach ($list as $row)
      $out .= "(".bodyList($row)."), ";
    $out = substr($out, 0, -2);
    //valuesList($table, $row);
  }
  return $out;
}

function dumpAll()
{
  global $dPconfig;
  $alltables = mysql_list_tables($dPconfig['dbname']);

  while ($row = mysql_fetch_row($alltables))
    $output .= tableInsert($row[0]) . "\n";

  return $output;
}

function dumpTasks($project=-1, $task=-1)
{
  $output = "";
  $sql = "SELECT * FROM tasks";
  if ($project != -1)
  {
    $sql .= " WHERE task_project=$project";
    $output .= "#task_project#\n";
  }
  else if ($task != -1)
    $sql .= " WHERE task_id=$task";

  // Used for dynamic ID setting.
  $tasks = db_loadList($sql);
  foreach ($tasks as $task)
  {
    $output .= valuesList("tasks", $task);

    $output .= "#dependencies_task_id#\n";
    $output .= tableInsert("task_dependencies", "dependencies_task_id", $task['task_id']);
    $output .= "#task_log_task#\n";
    $output .= tableInsert("task_log", "task_log_task", $task['task_id']);
  }

  return $output;
}

function dumpForums($project=-1, $forum=-1)
{
  $output = "";
  $sql = "SELECT * FROM forums";
  if ($forum != -1)
  {
    $sql .= " WHERE forum_project='$row[project_id]'";
    $output .= "#forum_project#\n";
  }

  $forums = db_loadList($sql);
  foreach ($forums as $forum)
  {
    $output .= valuesList("forums", $forum);

    $output .= "#message_forum#\n";
    $output .= tableInsert("forum_messages", "message_forum", $forum['forum_id']);
    // Doesn't make sense - users/forums don't exist
    // $output .= tableInsert("forum_watch", "watch_forum", $forum['forum_id']);
  }

  return $output;
}

function dumpProject($project=-1)
{
  $output = "";
  $sql = "SELECT * FROM projects";
  if ($project != -1)
    $sql .= " WHERE project_id=$project";

  $rows = db_loadList($sql);
  foreach ($rows as $row)
  {
    //TODO: if parent company doesn't exist, create it "INSERT INTO companies WHERE company_id='$row[project_company]'"
    //TODO: Check if helpdesk and other modules exists, and insert their tables as well.
    $output .= valuesList("projects", $row);
    $output .= dumpTasks($row['project_id']);
    $output .= dumpForums($row['project_id'], -1);
    $output .= tableInsert("files", "file_project", $row['project_id']);
    $output .= tableInsert("events", "event_project", $row['project_id']);
  }

  return $output;
}

function dump($module, $item, $type)
{
  if ($type == 2)
    return tableInsert($module);
  if ($module == "all")
    return dump();
  else if ($module == "projects")
    return dumpProject($item);
  else if ($module == "tasks")
    return dumpTasks(-1, $item);
  else
    return tableInsert($module);
}

$testing = false;

if ($module == "all")
  $output = dumpAll();
else
  $output = dump($module, $item, $file_type);


if ($file_type == '2')
{
  $file .= '.csv';
  $mime_type = "text/csv";
}
else if ($file_type == '1')
{
  include('zip.lib.php');
  $zip = new zipfile;
  $zip->addFile($output,"$file.sql");
  $output = $zip->file();

  $file .= '.zip';
  $mime_type = 'application/x-zip';
}
else
{
  $file .= '.sql';
  $mime_type = 'text/sql';
}

if (!$testing)
{
  header('Content-Disposition: inline; filename="' . $file . '"');
  header('Content-Type: ' . $mime_type);
}
else
{
  print_r($_POST);
  $output = "\n\n" . $output;
  $output = str_replace("\n", "<br />", $output);
}

echo $output;
?>
