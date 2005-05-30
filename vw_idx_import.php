<?php

  global $AppUI, $canRead, $canEdit, $m;

  if (!$canRead)
    $AppUI->redirect("m=public&a=access_denied");
  
  if (dPgetParam($_POST, "submit"))
  {
    // $_FILES exists since php 4.1.0
		$filename = $_FILES['upload_file']['tmp_name'];
		$fileext = substr($filename, -4);
    $file = fopen($filename, "r");
    $filedata = fread($file, $_FILES['upload_file']['size']);
    fclose($file);

		if ($fileext == '.sql');
		{
			$sql = explode(';', $filedata);
			foreach($sql as $insert)
		    db_exec($insert);
			$error = db_error();
		}

    if (isset($error))
 	    echo $AppUI->_('Failure') . $error;
    else
 	    echo $AppUI->_('Success');
	 }

?>

<form enctype="multipart/form-data" action="index.php?m=backup" method="post">
  <input type="file" name="upload_file" />
  <input type="hidden" name="MAX_FILE_SIZE" value="8388608" />
  <input type="submit" name="submit" value="<?php echo $AppUI->_("Import Data"); ?>" />
</form>
