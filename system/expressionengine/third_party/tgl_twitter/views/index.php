<?php

	/*
  This view displays all of the module wide settings that can be 
  edited by the admin.
	*/

	echo form_open($form_action, '', '');

	$this->table->set_template($cp_table_template);
	$this->table->set_heading('TGL Twitter Settings', '');
			
	//consumer keys
	$consumer_key = isset($settings['consumer_key']) ? $settings['consumer_key'] : '';
	$consumer_secret = isset($settings['consumer_secret']) ? $settings['consumer_secret'] : '';
	
	$consumer_key_input_data = array('name' => 'consumer_key','value' => $consumer_key ,'maxlength' => '100' ,'style' => 'width:50%');
	$consumer_secret_input_data = array('name' => 'consumer_secret','value' => $consumer_secret ,'maxlength' => '100' ,'style' => 'width:50%');
	
	$this->table->add_row('<strong>Consumer Key</strong>', form_input($consumer_key_input_data));
	$this->table->add_row('<strong>Consumer Secret</strong>', form_input($consumer_secret_input_data));
	
	
	//request tokens
	$request_token = isset($settings['request_token']) ? $settings['request_token'] : FALSE;
	$request_token_secret = isset($settings['request_token_secret']) ? $settings['request_token_secret'] : FALSE;
	
	if($request_token != FALSE && $request_token_secret != FALSE && ! empty($consumer_key) && ! empty($consumer_secret)){
		echo form_hidden('request_token', $request_token);
		echo form_hidden('request_token_secret', $request_token_secret);
		
		//pin
		$pin = isset($settings['pin']) ? $settings['pin'] : '';
		
		$pin_input_data = array('name' => 'pin','value' => $pin ,'maxlength' => '10' ,'style' => 'width:50%');
		$this->table->add_row('<strong>Pin</strong>', form_input($pin_input_data));
	}
	
	if(isset($settings['consumer_key'], $settings['consumer_secret']) && !empty($settings['consumer_key']) && !empty($settings['consumer_key']) && ! isset($settings['pin'])){
		$this->table->add_row("If you are submitting your PIN number, do not click \"Generate New Request Token\" (Click Update, instead)","<p><a id='generate_request_token' href='".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter'.AMP."method=register_with_twitter'>Generate new Request Token</a></p>");
	}
	
	//access tokens
	$access_token = isset($settings['access_token']) ? $settings['access_token'] : FALSE;
	$access_token_secret = isset($settings['access_token_secret']) ? $settings['access_token_secret'] : FALSE;
	
	if($access_token != FALSE && $access_token_secret != FALSE){
		echo form_hidden('access_token', $access_token);
		echo form_hidden('access_token_secret', $access_token_secret);
	}
	
	if(isset($settings['consumer_key'], $settings['consumer_secret'], $settings['request_token'], $settings['request_token_secret'], $settings['pin'], $settings['access_token'], $settings['access_token_secret']))
	{
		echo '<h3>You have successfully authenticated.</h3>';
		$this->table->add_row("","<p><a id='generate_request_token' href='".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter'.AMP."method=erase_settings'>Erase Authentication Settings</a></p>");
		echo $this->table->generate();
		
	}else{
		
		echo $this->table->generate();
		echo form_submit(array('name' => 'Submit', 'id' => 'submit', 'value' => 'Update', 'class' => 'submit'));
	
	}
	
	

	
	
	
	
	
	
	