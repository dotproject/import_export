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

function valuesList($table, $row)
{
  $sql = "INSERT INTO $table(";

  foreach ($row as $key=>$col)
    $sql .= "`$key`, ";
  $sql = substr($sql, 0, -2);

  $sql .= ") VALUES(";
  foreach($row as $col)
    $sql .= (($col == null)?"NULL":("'" . str_replace("'", "\'", $col) . "'")) . ", ";

  $sql = substr($sql, 0, -2); // remove last comma
  $sql .= ");";
  
  return $sql . "\n";
}

function tableInsert($table, $keyCol=1, $keyVal=1)
{
  $sql = "";
  
  $list = db_loadList("SELECT * FROM $table WHERE $keyCol='$keyVal'");
  foreach ($list as $row)
    $sql .= valuesList($table, $row);

  return $sql;
}

function dumpAll()
{
  global $dPconfig;
  $alltables = mysql_list_tables($dPconfig['dbname']);
  echo $dPconfig['dbname'];
  while ($row = mysql_fetch_row($alltables))
  {
    $fields = mysql_list_fields($dPconfig['dbname'], $row[0]);
    $columns = mysql_num_fields($fields);

    // all data from table
    $result = db_loadList("SELECT * FROM $row[0]");
    foreach ($result as $tablerow)
    {
      $output .= 'INSERT INTO `'.$row[0].'` (';
      foreach ($tablerow as $key=>$value)
        $output .= '`$key`,';

      $output = substr($output,0,-1); // remove last comma
      $output .= ') VALUES (';
      foreach ($tablerow as $value)
      {
        // remove all enters from the field-string. MySql stamement must be on one line
        $value = str_replace("\r\n",'\n',$value);
        // replace ' with \'
        $value = str_replace("'","\'",$value);
        $output .= "'$value'";
      }
      $output = substr($output,0,-1); // remove last comma
      $output .= ');' . "\n";
    } // while
    $output .= "\n";
  }

  return $output;
}

function dumpTasks($project=-1)
{
  $output = "";
  $sql = "SELECT * FROM tasks";
  if ($project != -1)
    $sql .= " WHERE task_project=$project";

  $tasks = db_loadList($sql);
    foreach ($tasks as $task)
    {
      $output .= valuesList("tasks", $task);

      $output .= tableInsert("task_dependencies", "dependencies_task_id", $task['task_id']);
      $output .= tableInsert("task_log", "task_log_task", $task['task_id']);
    }

  return $output;
}

function dumpForums($project=-1, $forum=-1)
{
  $output = "";
  $sql = "SELECT * FROM forums";
  if ($forum != -1)
    $sql .= " WHERE forum_project='$row[project_id]'";

    $forums = db_loadList($sql);
    foreach ($forums as $forum)
    {
      $output .= valuesList("forums", $forum);

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
  if ($module == "all")
    return dump();
  else if ($module == "Projects")
    return dumpProjects($item);
  else if ($module == "Tasks")
    return dumpTasks();
  else
    return tableInsert($module);
}


$file = dPgetParam($_POST, 'sql_file', 'backup'); //'backup.sql';
$file_type = dPgetParam($_POST, 'file_type', '0');
$module = dPgetParam($_POST, 'module', 'all');
$item = dPgetParam($_POST, 'item', '-1');

if ($module == "all")
  $output = dumpAll();
else
  $output = dump($module, $item, $file_type);


  if ($file_type == '1')
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
//TODO uncomment after done testing.
//  header('Content-Disposition: inline; filename="' . $file . '"');
//  header('Content-Type: ' . $mime_type);
  echo $output;
?>
