<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-popular" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
	<div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
      </div>
	  <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-opayinvoice" class="form-horizontal">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_mid; ?></label>
            <div class="col-sm-10">
              <input type="text" name="opayinvoice_mid" value="<?php echo $opayinvoice_mid; ?>" placeholder="<?php echo $entry_mid; ?>" id="input-name" class="form-control" />
              <?php if ($error_mid) { ?>
              <div class="text-danger"><?php echo $error_mid; ?></div>
              <?php } ?>
            </div>
          </div>
		  <div class="form-group">
            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_hashkey; ?></label>
            <div class="col-sm-10">
              <input type="text" name="opayinvoice_hashkey" value="<?php echo $opayinvoice_hashkey; ?>" placeholder="<?php echo $entry_hashkey; ?>" id="input-name" class="form-control" />
              <?php if ($error_hashkey) { ?>
              <div class="text-danger"><?php echo $error_hashkey; ?></div>
              <?php } ?>
            </div>
          </div>
		  <div class="form-group">
            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_hashiv; ?></label>
            <div class="col-sm-10">
              <input type="text" name="opayinvoice_hashiv" value="<?php echo $opayinvoice_hashiv; ?>" placeholder="<?php echo $entry_hashiv; ?>" id="input-name" class="form-control" />
              <?php if ($error_hashiv) { ?>
              <div class="text-danger"><?php echo $error_hashiv; ?></div>
              <?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_invoice_url; ?></label>
            <div class="col-sm-10">
              <input type="text" name="opayinvoice_url" value="<?php echo $opayinvoice_url; ?>" placeholder="<?php echo $entry_invoice_url; ?>" id="input-name" class="form-control" />
              <?php if ($error_invoice_url) { ?>
              <div class="text-danger"><?php echo $error_invoice_url; ?></div>
              <?php } ?>
            </div>
          </div>
		  <div class="form-group">
            <label class="col-sm-2 control-label" for="input-opayinvoice-autoissue"><?php echo $entry_autoissue; ?></label>
            <div class="col-sm-10">
              <select name="opayinvoice_autoissue" id="input-opayinvoice-autoissue" class="form-control">
				<?php foreach ($opayinvoice_autoissues as $opayi_autoissue) { ?>
					<?php if ($opayi_autoissue['value'] == $opayinvoice_autoissue) { ?>
						<option value="<?php echo $opayi_autoissue['value']; ?>" selected="selected"><?php echo $opayi_autoissue['text']; ?></option>
					<?php } else { ?>
						<option value="<?php echo $opayi_autoissue['value']; ?>"><?php echo $opayi_autoissue['text']; ?></option>
					<?php } ?>
				<?php } ?>
			  </select>
			  <div><?php echo $text_autoissue; ?></div>
            </div>
          </div>
		  <div class="form-group">
            <label class="col-sm-2 control-label" for="input-opayinvoice-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10">
              <select name="opayinvoice_status" id="input-opayinvoice-status" class="form-control">
				<?php foreach ($opayinvoice_statuses as $opayl_status) { ?>
					<?php if ($opayl_status['value'] == $opayinvoice_status) { ?>
						<option value="<?php echo $opayl_status['value']; ?>" selected="selected"><?php echo $opayl_status['text']; ?></option>
					<?php } else { ?>
						<option value="<?php echo $opayl_status['value']; ?>"><?php echo $opayl_status['text']; ?></option>
					<?php } ?>
				<?php } ?>
			  </select>
            </div>
          </div>
		 
		 
		 
		 </form>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>