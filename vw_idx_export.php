<?php

  global $AppUI, $canRead, $canEdit, $m, $cfg;

  if (!$canRead)
    $AppUI->redirect("m=public&a=access_denied");

$sql = "
SELECT 
        mod_name, 
        mod_directory, 
        permissions_item_label, 
        permissions_item_field, 
        permissions_item_table 
FROM 
        modules 
WHERE 
        mod_active=1 
        and mod_name in ('Forums', 'Tasks', 'Projects', 'Files', 'Events', 'Companies', 'Contacts')
";
// Above line to be replaced with 'and mod_backupable=1'.... or something like that

$modules_list = db_loadList( $sql);
$pgos = array();
$select_list = array();
$join_list = array();
$modules = array();
$count = 0;
foreach ($modules_list as $module){
        if(isset($module['permissions_item_field']) && isset($module['permissions_item_table']) && isset($module['permissions_item_label'])){
                $label = "t$count";
                //associates mod dirs with tables;
                $pgos[$module['mod_directory']] = array('table'=>$module['permissions_item_table'], 'field' => $module['permissions_item_label'], 'label' => $label);
                //sql selects
                $select_list[] = "\t$label.".$module['permissions_item_field']." as $label".$module['permissions_item_field'].", $label.".$module['permissions_item_label']." as $label".$module['permissions_item_label']."";
                //sql joins
                $join_list[] = "\tLEFT JOIN ".$module['permissions_item_table']." $label ON $label.".$module['permissions_item_field']." = p.permission_item and p.permission_grant_on = '".$module['mod_directory']."'";
                $count++;

        $modules[$module['mod_directory']] = $module['mod_name'];
        }
}
$modules['contacts'] = 'contacts';

$selects = implode(",\n", $select_list);
$joins = implode("\n", $join_list);

$modules = arrayMerge( array( 'all'=>'all' ), $modules); //$AppUI->getActiveModules( 'modules' ));


?>

<script language="javascript">
var tables = new Array;
<?php
        foreach ($pgos as $key=>$value){
                echo "tables['$key'] = '".$value['table']."';\n";
        }
?>

function popPermItem() 
{
        var f = document.frm;
        var pgo = f.module.options[f.module.selectedIndex].value;

        if (!(pgo in tables)) 
        {
                alert( '<?php echo $AppUI->_('No list associated with this Module.', UI_OUTPUT_JS); ?>' );
                return;
        }
        window.open('./index.php?m=public&a=selector&dialog=1&callback=setPermItem&table=' + tables[pgo], 'selector', 'left=50,top=50,height=250,width=400,resizable')
}

// Callback function for the generic selector
function setPermItem( key, val ) {
        var f = document.frm;
        if (val != '') {
                f.item.value = key;
                f.item_name.value = val;
        } else {
                f.item.value = '-1';
                f.item_name.value = 'all';
        }
}
</script>

<form name="frm" action="?m=<?php echo $m; ?>&a=savefile&suppressHeaders=1" method="post">
  <input type="hidden" name="item" value="-1" />
  <table>
  <tr><td rowspan="5">
    Hierarchy:<br />
    Company<br />
    Project<br />
    Task<br />
    Contacts<br />
    File<br />
  </tr>
  <tr>
    <td>
  <?php 
    echo $AppUI->_('Module') . ":</td><td>" . arraySelect($modules, 'module', '', 'all') . "</td></tr><tr><td>";
    echo $AppUI->_('Item');?>:
    </td>
    <td>
      <input type="text" name="item_name" class="text" size="30" value="all" disabled>
      <input type="button" name="" class="text" value="..." onclick="popPermItem();">
    </td>
  </tr>
  <tr>
    <td><?php echo $AppUI->_("Filename (without extension):"); ?></td>
    <td><input type="field" name="sql_file" size="20" /></td>
  </tr>
  <tr>
    <td><?php echo $AppUI->_("File Type"); ?></td>
    <td>
      <?php 
        $fileType = array('sql' => $AppUI->_('sql'),
                          'csv' => $AppUI->_('csv'),
													'xml' => $AppUI->_('xml'),
                          'vcf' => $AppUI->_('vcf'),
													'msproject' => $AppUI->_('Microsoft Project file'));
        echo arraySelect($fileType, 'file_type', '', 'sql');
      ?>
			<input type="checkbox" name="zipped" value="1" />zipped
    </td>
  </tr>
  </table>
<br />
<input type="reset" value="<?php echo $AppUI->_('clear');?>" class="button" />
<input type="submit" value="<?php echo $AppUI->_('Export Data');?>" class="button" />
</form>
