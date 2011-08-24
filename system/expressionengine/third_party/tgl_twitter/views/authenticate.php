	
<?php
	
	if(isset($register_url)){
		
		
		echo "<div id='authenticate_copy_container'>
			<p><strong>Your request token has been saved.</strong></p>
			<p>Please read the entire directions before proceeding</p>";
			
		echo "<p>First, click <a target='_blank' href='".$register_url."'>here</a> to be taken to Twitter, where you can authenticate this site.  Copy the Pin number Twitter provides.  Then, come back to this page and click <a href='".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter'."'>here</a> to be taken back to the settings form, where you will submit your Pin.</p>";
	
	}else{
		
		echo "<div id='authenticate_copy_container'>
			<p><strong>There was an error generating your request tokens</strong></p>";
	}
	
?>

