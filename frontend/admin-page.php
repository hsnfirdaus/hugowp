<div class="wrap">
	<h2>Import Hugo</h2>
	<div class="narrow">
		<?php
		if(isset($_FILES['import'])){
			require __DIR__.'/../importer.class.php';
			try {
				$importer = new Importer();
				$import = $importer->doImport($_FILES['import']['tmp_name']);
				echo '<p>Post Imported :'.$import['post_inserted'].'</p>';
				echo '<p>Post Failed Imported :'.$import['post_failed'].'</p>';
				echo '<p>Image Imported :'.$import['image_inserted'].'</p>';
				print_r($import['errors']);
			} catch (\Exception $e){
				echo 'Failed! '.$e->getMessage();
			}
		}else{
		?>
		<p>This plugins will import all of your hugo static content to wordpress.</p>
		<p>Please select your compressed (.zip) file, that include two folder (contents and static) folder from hugo site. Currently only support markdown content.</p>
		<form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form">
			<p><label for="upload">Select your files:</label></p>
			<p><input type="file" id="upload" name="import" size="25" accept=".zip,.rar,.7zip"></p>
			<p><input type="submit" name="submit" id="submit" class="button button-primary" value="Unggah berkas dan impor"></p>
		</form>
		<?php } ?>
	</div>
</div>