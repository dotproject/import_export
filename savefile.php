<?php
if (!$canRead)
	$AppUI->redirect( "m=public&a=access_denied" );

// ----------------------------------------------------------
// Backup part starting ...
$separator = ',';
$file = dPgetParam($_POST, 'sql_file', 'backup'); 
$file_type = dPgetParam($_POST, 'file_type', 'sql');
$zipped = dPgetParam($_POST, 'zipped', false);
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
  global $file_type, $separator;

  if ($file_type == 'sql')
	{
  	$separator = ',';
	  $q = "'";
	}
  else
    $q = '"';

  $sql = "";
  foreach($row as $key=>$col)
    if (!is_int($key))
    $sql .= (($col == null)?'NULL':($q . str_replace($q, "\$q", $col) . $q)) . $separator;
    // Substitute the entire line for csv if necessary.
  $sql = substr($sql, 0, -1); // remove last comma

  return $sql;
}

function headerList($row)
{
  global $file_type, $separator;
  if (empty($row))
    return;

  if ($file_type == 'sql')
	{
    $q = "`";
		$separator = ',';
	}
  else
    $q = '';

  $out = '';
  foreach ($row as $key=>$col)
    if (!is_int($key))
      $out .= "$q$key$q$separator";

  return substr($out, 0, -1);
}

function xmlList($row)
{
	$out = '
	<item ';
	foreach($row as $key => $col)
		if (!is_int($key))
		{
//			if (strpos($key, '_') > -1)
//				$key = substr($key, strpos($key, '_')+1); 
			$out .=  $key.'="'.htmlspecialchars($col).'" ';
		}
	$out .= '/>';

	return $out;
}

function tableInsert($table, $keyCol=1, $keyVal=1)
{
  global $file_type;
  $out = "";

  $list = db_loadList("SELECT * FROM $table WHERE $keyCol='$keyVal'");
  if ($file_type == 'csv')
  {
    $out = headerList($list[0]) . "\n";
    foreach($list as $row)
      $out .= bodyList($row) . "\n";
  }
  else if ($file_type == 'sql')
  {
    foreach ($list as $row)
    	$out = valuesList($table, $row);
  }
	else if ($file_type == 'xml')
	{
		$out = "<$table>";
		if (is_array($list))
			foreach($list as $row)
				$out .= xmlList($row);
		$out .= "
</$table>";
	}

  return $out;
}

function dumpAll()
{
  global $dPconfig;
  $alltables = mysql_list_tables($dPconfig['dbname']);

  while ($row = mysql_fetch_row($alltables))
    $output .= tableInsert($row[0]) . '
';

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
    $output .= dumpProject($row['project_id']);

  return $output;
}

function dump($module, $item, $type)
{
  if ($type == 'vcf' && $module == 'contacts')
    return dumpContacts();
  if ($module == "all")
    return dumpAll();
  else if ($module == "projects")
    return dumpProject($item);
  else if ($module == "tasks")
    return dumpTasks(-1, $item);
  else if ($module == "companies")
    return dumpCompanies($item);
  else
    return tableInsert($module);
}

//if ($module == "all")
//  $output = dumpAll();
//else
$output = dump($module, $item, $file_type);
if ($file_type == 'xml')
	$output = '<xml>' . $output . '</xml>';

$file .= '.' . $file_type;

$mimes = array(
'csv' => 'text/csv',
'vcf' => 'text/x-vcard',
'sql' => 'text/sql',
'xml' => 'text/xml'); // application/xslt+xml
$mime_type = $mimes[$file_type];

if ($zipped)
{
  include('zip.lib.php');
  $zip = new zipfile;
  $zip->addFile($output,$file);
  $output = $zip->file();

  $file .= '.zip';
  $mime_type = 'application/x-zip';
}

$testing = false;
if (!$testing)
{
  header('Content-Disposition: inline; filename="' . $file . '"');
  header('Content-Type: ' . $mime_type);
}
else
{
	echo '<code>';
	print_r($_POST);
  $output .= '</code>';
}

echo $output;
?>
