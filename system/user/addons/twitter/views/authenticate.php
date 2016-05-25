<div class="box">

	<div class="tbl-ctrls">
	
		<? if(isset($register_url)): ?>

		<p>
			<strong>Your request token has been saved.</strong>
		</p>
		<p>
			Please read the entire directions before proceeding
		</p>
		<p>
			First, click <a target="_blank" href="<?=$register_url?>">here</a> to be taken to Twitter, where you can authenticate this site.  Copy the Pin number Twitter provides.  
			Then, come back to this page and click <a href="<?=ee('CP/URL', 'addons/settings/twitter/')->compile()?>">here</a> to be taken back to the settings form, where you will submit your Pin.
		</p>

		<? else: ?>

		<p>
			<strong>There was an error generating your request tokens</strong>
		</p>

		<? endif; ?>

	</div>
</div>

