<?php if( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'tgl_twitter/classes/twitteroauth.php';

class Tgl_twitter_mcp
{
	private $data = array();
	
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->site_id = $this->EE->config->item('site_id');
		$this->base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter';
		    
		// load table lib for control panel
		$this->EE->load->library('table');
		$this->EE->load->helper('form');
		
		$this->EE->cp->load_package_css('tgl_twitter');
		
		// Set page title
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('tgl_twitter_module_name'));
		
	}
	/**
	 * Module CP index function
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	public function index()
	{
		$this->EE->load->model('tgl_twitter_model');
		
		$this->data['form_action'] = AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter'.AMP.'method=submit_settings';
		$this->data['settings'] = $this->EE->tgl_twitter_model->get_settings();
										
		return $this->EE->load->view('index', $this->data, TRUE);
	}
	
	/**
	 * Called after new settings have been submitted
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	public function submit_settings()
	{
		
		$this->EE->load->model('tgl_twitter_model');
		
		//loops through the post and adds all settings (deletes old settings first)
		$success = $this->EE->tgl_twitter_model->insert_new_settings();
		
		$settings = $this->EE->tgl_twitter_model->get_settings();
		
		if($success && isset($settings['pin']) && ! isset($settings['access_token'], $settings['access_token_secret'])){
			
			//if a pin has been submitted, we want to generate the access tokens for the app
			if($this->generate_access_tokens($settings)){
				$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('Success! You are now Authenticated.'));
			}else{
				
				//if the pin was not able to be created, delete the submitted pin and send the user back to the authenticate page.
				/*
					TODO : we could use some better UX here.  Ideally sending the user back to this page happens after they create a request token, 
								 and sending them back because of an invaid acess token authentication is somewhat confusing
				*/
				$this->EE->tgl_twitter_model->delete_setting('pin');
				$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('Error authenticating with Twitter. Please verify Pin and re-submit'));
				$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter'.AMP.'method=register_with_twitter');
			}
			
		}else{
			
			//else : sumission before pin as been submitted, or after all settings have been submitted
			
			if(! $success){
			  $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('Error saving settings.'));
			}else{
			  $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('Success!'));
			}
			
		}
		
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter');

	}
	
	/**
	 * Called after a user clicks "Register"
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	public function register_with_twitter()
	{
		
		$this->EE->load->model('tgl_twitter_model');
		$settings = $this->EE->tgl_twitter_model->get_settings();
		
		$oauth = new TwitterOAuth($settings['consumer_key'], $settings['consumer_secret']);
		$request = $oauth->getRequestToken();
		
		if($request != FALSE){
			
			$requestToken = $request['oauth_token'];
			$requestTokenSecret = $request['oauth_token_secret'];
			
			//save auth tokens into the db
			$success = $this->EE->tgl_twitter_model->insert_secret_token($requestToken,$requestTokenSecret);

			if($success){
				
				// get Twitter generated registration URL and load the authenticate view
				$this->data['register_url'] = $oauth->getAuthorizeURL($request);
				$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('Success!'));
				return $this->EE->load->view('authenticate', $this->data, TRUE);
			}
			else
			{
				$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('There was an error saving request tokens.'));
				$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter');
			}
			
		}else{
			
			//else : we were not able to create request tokens, probably becase the consumer key/secret were correct.  lets erase those keys
			//			 from the settings and send the user back to square one of the process.
			
			$this->EE->tgl_twitter_model->delete_all_settings();
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('There was an error generating request tokens. Please verify and re-submit your Consumer Key and Secret.'));
			$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter');
			
		}
					
	}
	
	/**
	 * Used to generate the access tokens from Twitter.  This is the last step in the authentication process
	 *
	 * @param string $settings 
	 * @return boolean : depending if we were able to generate the tokens and save them to the DB or NOT
	 * @author Bryant Hughes
	 */
	private function generate_access_tokens($settings)
	{
		$this->EE->load->model('tgl_twitter_model');
		
		//Retrieve our previously generated request token & secret
		$requestToken = $settings['request_token'];
		$requestTokenSecret = $settings['request_token_secret'];
		
		$oauth = new TwitterOAuth('consumer_key', 'consumer_secret', $requestToken, $requestTokenSecret);
		
		// Generate access token by providing PIN for Twitter
		$request = $oauth->getAccessToken(NULL, $settings['pin']);
		
		if($request != FALSE)
		{
			$access_token = $request['oauth_token'];
			$access_token_secret = $request['oauth_token_secret'];

			// Save our access token/secret
			return $this->EE->tgl_twitter_model->insert_access_token($access_token, $access_token_secret);
		}
		else
		{
			return FALSE;
		}
		
	}
	
	/**
	 * function that kills all settings in the DB and starts us over at square one.
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	public function erase_settings()
	{
		$this->EE->load->model('tgl_twitter_model');
		$this->EE->tgl_twitter_model->delete_all_settings();
		$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('Authentication Settings Erased.'));
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tgl_twitter');
	}
		
}

/* End of File: mcp.module.php */