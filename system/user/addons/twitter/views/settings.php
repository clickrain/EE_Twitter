<div class="box">

	<div class="tbl-ctrls">

		<fieldset class="tbl-search right">

			<? if(isset($settings['consumer_key'], $settings['consumer_secret'], $settings['request_token'], $settings['request_token_secret'], $settings['pin'], $settings['access_token'], $settings['access_token_secret'])): ?>
			<a class="btn tn action" href="<?=ee('CP/URL')->make('addons/settings/twitter/erase_settings')?>"><?=lang('twitter_settings_erase')?></a>
			<? endif; ?>

		</fieldset>

	</div>

	<?php $this->embed('ee:_shared/form')?>

</div>