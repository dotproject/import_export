<?php

  global $AppUI, $canRead, $canEdit, $m;

  if (!$canRead)
    $AppUI->redirect("m=public&a=access_denied");
  
  if (dPgetParam($_POST, "submit"))
  {
    // $_FILES exists since php 4.1.0
    $file = fopen($_FILES['sql_file']['tmp_name'], "r");
    $sql = fread($file, $_FILES['sql_file']['size']);
    fclose($file);

    db_exec($sql);
    if (db_error())
      echo $AppUI->_("Failure: ") . db_error();
    else
      echo $AppUI->_("Success!");
  }

?>

<form enctype="multipart/form-data" action="index.php?m=backup" method="post">
  <input type="file" name="sql_file" />
  <input type="hidden" name="MAX_FILE_SIZE" value="8388608" />
  <input type="submit" name="submit" value="<?php echo $AppUI->_("Import Data"); ?>" />
</form>
