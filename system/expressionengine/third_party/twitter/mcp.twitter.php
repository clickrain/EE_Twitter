<?php if( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'twitter/classes/twitteroauth.php';

class Twitter_mcp
{
	private $data = array();
 
	public function __construct()
	{
		ee()->load->model('twitter_model');
	}

	/**
	 * Module CP index function
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	public function index()
	{
		// check if we have a result
		$rules = array(
		 	'consumer_key'         => 'required|minLength[3]',
		 	'consumer_secret'      => 'required|minLength[3]',
		);
		$result = ee('Validation')->make($rules)->validate($_POST);

		if($result->isValid())
		{
			// do save
			$save = $this->submit_settings();

			// handle errors
			if($save == 'pin_success')
			{
				$message = lang('Success! You are now Authenticated.');
				ee('CP/Alert')->makeBanner('success-message')->asSuccess()->withTitle(lang('Form saved'))->addToBody($message)->defer();
			}
			else if($save == 'pin_error')
			{
				$message = lang('Error authenticating with Twitter. Please verify Pin and re-submit');
				ee('CP/Alert')->makeBanner('error-message')->asError()->withTitle(lang('Error'))->addToBody($message)->defer();
			}
			else if($save == TRUE)
			{
				$message = lang('Success!');
				ee('CP/Alert')->makeBanner('success-message')->asSuccess()->withTitle(lang('Form saved'))->addToBody($message)->defer();
			}
			else
			{
				$message = lang('Error saving settings.');
				ee('CP/Alert')->makeBanner('error-message')->asError()->withTitle(lang('Error'))->addToBody($message)->defer();
			}

			ee()->functions->redirect(ee('CP/URL', 'addons/settings/twitter/')->compile());
			exit;
		}
		else
		{
			// get the settings
			$settings = ee()->twitter_model->get_settings();

			$consumer_key         = (isset($settings['consumer_key'])) ? $settings['consumer_key'] : '';
			$consumer_secret      = (isset($settings['consumer_secret'])) ? $settings['consumer_secret'] : '';
			$pin 		          = (isset($settings['pin'])) ? $settings['pin'] : '';
			$request_token   	  = (isset($settings['request_token'])) ? $settings['request_token'] : FALSE;
			$request_token_secret = (isset($settings['request_token_secret'])) ? $settings['request_token_secret'] : FALSE;
			$access_token   	  = (isset($settings['access_token'])) ? $settings['access_token'] : FALSE;
			$access_token_secret  = (isset($settings['access_token_secret'])) ? $settings['access_token_secret'] : FALSE;


			// consumer key
			$fields[] = array
			(
				'title' => 'twitter_settings_consumer_key',
				'fields' => array(
					'consumer_key' => array(
						'type'     => 'text',
						'value'    => $consumer_key,
						'required' => TRUE
					)
				),
			);

			// consumer secret
			$fields[] = array
			(
				'title' => 'twitter_settings_consumer_secret',
				'fields' => array(
					'consumer_secret' => array(
						'type'     => 'text',
						'value'    => $consumer_secret,
						'required' => TRUE
					)
				),
			);

			// add the pin fields
			if($request_token != FALSE && $request_token_secret != FALSE && ! empty($consumer_key) && ! empty($consumer_secret))
			{

				$fields[] = array
				(
					'title'  => 'twitter_settings_pin',
					'fields' => array(
						'request_token' => array(
							'type'     => 'hidden',
							'value'    => $request_token,
						),
						'request_token_secret' => array(
							'type'     => 'hidden',
							'value'    => $request_token_secret,
						),
						'pin' => array(
							'type'     => 'text',
							'value'    => $pin,
						),
					),
				);
			}

			// add the button
			if(isset($settings['consumer_key'], $settings['consumer_secret']) && !empty($settings['consumer_key']) && !empty($settings['consumer_key']) && ! isset($settings['pin']))
			{
				$fields[] = array
				(
					'title'  => 'twitter_settings_generate',
					'fields' => array(
						'generate_settings' => array(
							'type'          => 'html',
							'content'       => '<a class="btn tn action" href="'.ee('CP/URL')->make('addons/settings/twitter/register_with_twitter').'">'.lang('twitter_settings_generate').'</a>',
						)
					)
				);
			}

			// add the access token fields
			if($access_token != FALSE && $access_token_secret != FALSE)
			{
				$fields[] = array
				(
					'fields' => array(
						'access_token' => array(
							'type'     => 'hidden',
							'value'    => $access_token,
						),
						'access_token_secret' => array(
							'type'     => 'hidden',
							'value'    => $access_token_secret,
						),
					),
				);
			}

			$form = array($fields);

			// final view variables we need to render the form
			$vars = array('sections' => $form);
			$vars += array
			(
				'base_url' 			    => ee('CP/URL', 'addons/settings/twitter'),
				'cp_page_title' 		=> lang('twitter_module_name'),
				'save_btn_text' 		=> 'btn_save_form',
				'save_btn_text_working' => 'btn_saving',
				'settings' 				=> $settings,
			);	

			// add the error to the form
			if($_POST)
				$vars['errors'] = $result;

			return array
			(
			  	'body'       => ee('View')->make('twitter:settings')->render($vars),
			  	'breadcrumb' => array(ee('CP/URL', 'addons/settings/twitter/')->compile() => lang('twitter_module_name')),
				'heading'    => lang('settings')
			);
		}
	}

	/**
	 * Called after new settings have been submitted
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	private function submit_settings()
	{
		//loops through the post and adds all settings (deletes old settings first)
		$success  = ee()->twitter_model->insert_new_settings();
		$settings = ee()->twitter_model->get_settings();

		if($success && isset($settings['pin']) && ! isset($settings['access_token'], $settings['access_token_secret'])){

			//if a pin has been submitted, we want to generate the access tokens for the app
			if($this->generate_access_tokens($settings))
			{
				return 'pin_success';
			}
			else
			{
				//if the pin was not able to be created, delete the submitted pin and send the user back to the authenticate page.
				ee()->twitter_model->delete_setting('pin');

				return 'pin_error';
			}

		}
		else
		{
			//else : sumission before pin as been submitted, or after all settings have been submitted
			if(!$success)
				return FALSE;

			return TRUE;
		}
	}

	/**
	 * Called after a user clicks "Register"
	 *
	 * @return void
	 * @author Bryant Hughes
	 */
	public function register_with_twitter()
	{
		$settings = ee()->twitter_model->get_settings();

		$oauth = new TwitterEETwitter_OAuth($settings['consumer_key'], $settings['consumer_secret']);
		$request = $oauth->getRequestToken();

		if($request != FALSE)
		{
			$requestToken = $request['oauth_token'];
			$requestTokenSecret = $request['oauth_token_secret'];

			//save auth tokens into the db
			$success = ee()->twitter_model->insert_secret_token($requestToken, $requestTokenSecret);

			if($success)
			{
				$message = lang('Success! You are now Authenticated.');
				ee('CP/Alert')->makeBanner('success-message')->asSuccess()->withTitle(lang('Succesfully authenticated'))->addToBody($message)->now();

				// final view variables we need to render the form
				$vars = array
				(
					'base_url' 		=> ee('CP/URL', 'addons/settings/twitter'),
					'cp_page_title' => lang('twitter_module_name'),
					'register_url'  => $oauth->getAuthorizeURL($request)
				);	
				return array
				(
				  	'body'       => ee('View')->make('twitter:authenticate')->render($vars),
				  	'breadcrumb' => array(ee('CP/URL', 'addons/settings/twitter/')->compile() => lang('twitter_module_name')),
					'heading'    => lang('settings')
				);
			}
			else
			{
				$message = lang('There was an error saving request tokens.');
				ee('CP/Alert')->makeBanner('error-message')->asError()->withTitle(lang('Error'))->addToBody($message)->defer();

				ee()->functions->redirect(ee('CP/URL', 'addons/settings/twitter/')->compile());
				exit;
			}

		}
		else
		{
			$message = lang('There was an error generating request tokens. Please verify and re-submit your Consumer Key and Secret.');
			ee('CP/Alert')->makeBanner('error-message')->asError()->withTitle(lang('Error'))->addToBody($message)->defer();

			ee()->functions->redirect(ee('CP/URL', 'addons/settings/twitter/')->compile());
			exit;
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
		ee()->load->model('twitter_model');

		//Retrieve our previously generated request token & secret
		$requestToken = $settings['request_token'];
		$requestTokenSecret = $settings['request_token_secret'];

		$oauth = new TwitterEETwitter_OAuth('consumer_key', 'consumer_secret', $requestToken, $requestTokenSecret);

		// Generate access token by providing PIN for Twitter
		$request = $oauth->getAccessToken(NULL, $settings['pin']);

		if($request != FALSE)
		{
			$access_token = $request['oauth_token'];
			$access_token_secret = $request['oauth_token_secret'];

			// Save our access token/secret
			return ee()->twitter_model->insert_access_token($access_token, $access_token_secret);
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
		ee()->twitter_model->delete_all_settings();

		$message = lang('Authentication Settings Erased.');
		ee('CP/Alert')->makeBanner('success-message')->asSuccess()->withTitle(lang('Succesfully authenticated'))->addToBody($message)->defer();

		ee()->functions->redirect(ee('CP/URL', 'addons/settings/twitter/')->compile());
		exit;
	}

}

/* End of File: mcp.module.php */
