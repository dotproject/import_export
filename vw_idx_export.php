<?php

  global $AppUI, $canRead, $canEdit, $m, $cfg;

  if (!$canRead)
    $AppUI->redirect("m=public&a=access_denied");

  echo "Test: <br />";

  $output = "";

  $rows = db_loadList("SELECT * FROM projects"); // WHERE project_id=selected_id
  foreach ($rows as $row)
  {
    $sql = "INSERT INTO project values(";
    foreach ($row as $col)
      $sql .= (($col == null)?"NULL":$col) . ", ";

    $sql = substr($sql,0,-1); // remove last comma
    $sql .= ");";
    $output .= $sql . "\n";
    echo $sql;
  }

  if (dPgetParam($_POST, "submit") == "Export Data")
    echo $sql;
/*  $fields = mysql_list_fields("dotproject", "projects");
  $columns = mysql_num_fields($fields);
  
  for ($i = 0; $i < $columns-1; $i++)
    echo mysql_field_name($fields, $i) . ", ";

  echo mysql_field_name($fields, $i);
  //foreach($fields as $field)
  //  echo $field;
*/
?>


<form action="modules/<?php echo $m; ?>/savefile.php" method="post">
  <?php echo $AppUI->_("Filename (without extension):"); ?>
  <input type="field" name="sql_file" size="20" /><br />
  <select name="compress">
    <option value="1" checked="checked"><?php echo $AppUI->_("Compressed .ZIP file"); ?></option>
    <option value="0"><?php echo $AppUI->_("Plain text file"); ?></option>
  </select><br />
  <input type="submit" name="submit" value="<?php echo $AppUI->_("Export Data"); ?>" />
</form>
