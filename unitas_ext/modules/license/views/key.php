<?php
/**
 * Этот файл является частью программы "CRM Руководитель" - конструктор CRM систем для бизнеса
 * https://www.rukovoditel.net.ru/
 * 
 * CRM Руководитель - это свободное программное обеспечение, 
 * распространяемое на условиях GNU GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Автор и правообладатель программы: Харчишина Ольга Александровна (RU), Харчишин Сергей Васильевич (RU).
 * Государственная регистрация программы для ЭВМ: 2023664624
 * https://fips.ru/EGD/3b18c104-1db7-4f2d-83fb-2d38e1474ca3
 */
?>

<h3 class="page-title"><?php echo TEXT_EXT_LICENSE ?></h3>

<p><?php echo TEXT_EXT_LICENSE_INFO ?></p>

<?php if(!defined('CFG_PLUGIN_EXT_LICENSE_KEY')): ?>

<?php echo form_tag('key',url_for('ext/license/key','action=save'),array('class'=>'form-horizontal'))?>
<div class="form-body">  
  <div class="form-group">
  	<label class="col-md-3 control-label" ><?php echo TEXT_EXT_ENTER_LICENSE_KEY . ' <b>' . str_replace('www.','',$_SERVER['HTTP_HOST']) . '</b>' ?></label>
    <div class="col-md-9">	
  	  <?php echo textarea_tag('product_key','',array('class'=>'form-control input-xlarge')); ?>
      <?php echo tooltip_text(TEXT_EXT_LICENSE_KEY_INFO) ?>
    </div>			
  </div>
</div>  
<?php echo submit_tag(TEXT_BUTTON_SAVE) ?>
</form>

<?php elseif(!license::check_key()): ?>

<div class="alert alert-danger"><?php echo TEXT_EXT_PRODUCT_KEY_NOT_CORRECT . ' ' . str_replace('www.','',$_SERVER['HTTP_HOST']) ?></div>

<?php echo form_tag('key',url_for('ext/license/key','action=update'),array('class'=>'form-horizontal'))?>
<div class="form-body">  
  <div class="form-group">
  	<label class="col-md-3 control-label" ><?php echo TEXT_EXT_ENTER_LICENSE_KEY . ' <b>' . str_replace('www.','',$_SERVER['HTTP_HOST']) . '</b>' ?></label>
    <div class="col-md-9">	
  	  <?php echo textarea_tag('product_key',CFG_PLUGIN_EXT_LICENSE_KEY,array('class'=>'form-control input-xlarge')); ?>
      <?php echo tooltip_text(TEXT_EXT_LICENSE_KEY_INFO) ?>
    </div>			
  </div>
</div>  
<?php echo submit_tag(TEXT_BUTTON_SAVE) ?>
</form>

<?php else: ?>
<p class="alert alert-success"><i class="fa fa-check"></i> <?php echo TEXT_EXT_LICENSE_ACTIVE. ' <b>' . str_replace('www.','',$_SERVER['HTTP_HOST']) . '</b>' ?></p>
<?php endif ?>

 
