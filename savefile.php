<?php

/******************************
 * First half of the file copied from index.php - checking permissions, login, etc.
 * consider using fileviewer.php (modified accordingly).
 */

// TODO Change this!!!
require_once "../../includes/config.php";
require_once "../../classes/ui.class.php";
require_once "../../includes/main_functions.php";
require_once "../../includes/db_adodb.php";

session_name( 'dotproject' );
if (get_cfg_var( 'session.auto_start' ) > 0) {
        session_write_close();
}
session_start();

  require_once( $dPconfig['root_dir'] . '/includes/db_connect.php' );
  require_once( "../../misc/debug.php" );
// check if session has previously been initialised
// if no ask for logging and do redirect
if (!isset( $_SESSION['AppUI'] ) || isset($_GET['logout'])) {
    $_SESSION['AppUI'] = new CAppUI();
  $AppUI =& $_SESSION['AppUI'];
  //$AppUI->setConfig( $dPconfig );
  $AppUI->checkStyle();
  require_once( $AppUI->getSystemClass( 'dp' ) );
  

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
 //               header ( "Location: fileviewer.php?$redirect" );
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

include "{$dPconfig['root_dir']}/includes/permissions.php";

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
  foreach($row as $key=>$col)
    if (!is_int($key))
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
    if (!is_int($key))
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
//    $out .= "INSERT INTO `$table` (" . headerList($list[0]) . ") VALUES "; 
    foreach ($list as $row)
//      $out .= "(".bodyList($row)."), ";
//    $out = substr($out, 0, -2);
    $out = valuesList($table, $row);
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

function dumpContacts()
{
        global $AppUI;
        $sql = "SELECT * FROM contacts";
        $contacts = db_loadList( $sql );

        // include PEAR vCard class
        require_once( $AppUI->getLibraryClass( 'PEAR/Contact_Vcard_Build' ) );

        $output = '';
        foreach($contacts as $contact)
        {
        // instantiate a builder object
        // (defaults to version 3.0)
        $vcard = new Contact_Vcard_Build();
        $vcard->setFormattedName($contact['contact_first_name'].' '.$contact['contact_last_name']);
        $vcard->setName($contact['contact_last_name'], $contact['contact_first_name'], $contact['contact_type'],
                $contact['contact_title'], '');
        $vcard->setSource($dPconfig['company_name'].' '.$dPconfig['page_title'].': '.$dPconfig['site_domain']);
        $vcard->setBirthday($contact['contact_birthday']);
       $contact['contact_notes'] = str_replace("\r", " ", $contact['contact_notes'] );
        $vcard->setNote($contact['contact_notes']);
        $vcard->addOrganization($contact['contact_company']);
        $vcard->addTelephone($contact['contact_phone']);
        $vcard->addParam('TYPE', 'PF');
        $vcard->addTelephone($contact['contact_phone2']);
        $vcard->addTelephone($contact['contact_mobile']);
        $vcard->addParam('TYPE', 'car');
        $vcard->addEmail($contact['contact_email']);
        //$vcard->addParam('TYPE', 'WORK');
        $vcard->addParam('TYPE', 'PF');
        $vcard->addEmail($contact['contact_email2']);
        //$vcard->addParam('TYPE', 'HOME');
        $vcard->addAddress('', $contact['contact_address2'], $contact['contact_address1'],
                $contact['contact_city'], $contact['contact_state'], $contact['contact_zip'], $contact['contact_country']);

        $output .= $vcard->fetch();
        $output .= "\n\n";
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

function dumpCompanies($company)
{
$output = "";
  $sql = "SELECT * FROM companies";
  if ($company != -1)
    $sql .= " WHERE company_id=$company";

  $rows = db_loadList($sql);
  foreach ($rows as $row)
    $output .= valuesList("companies", $row); 

  $output .= "#project_company#\n";
  $sql = "SELECT * FROM projects";
  if ($company != -1)
    $sql .= " WHERE project_company=$company";

  $rows = db_loadList($sql);
  foreach ($rows as $row)
  {
    $output .= dumpProject($row['project_id']);
  }


  return $output;
}

function dump($module, $item, $type)
{
  if ($type == 2)
    return tableInsert($module);
  if ($type == 3 && $module == 'contacts')
    return dumpContacts();
  if ($module == "all")
    return dump();
  else if ($module == "projects")
    return dumpProject($item);
  else if ($module == "tasks")
    return dumpTasks(-1, $item);
  else if ($module == "companies")
    return dumpCompanies($item);
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
else if ($file_type == '3')
{
  $file .= '.vcf';
  $mime_type = 'text/x-vcard';
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
