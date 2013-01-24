<?php if( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'twitter/classes/twitteroauth.php';

/**
 * Twitter
 *
 * This is an improvement on TGL_Twitter (https://github.com/bryantAXS/TGL_Twitter) which was an improvement on Ellislabs Twitter Timeline
 * (http://expressionengine.com/downloads/details/twitter_timeline/). Twitter supports Twitter's API version 1.1 using oAuth.
 *
 * @package default
 * @author Derek Jones
 * @author Bryant Hughes
 * @author Bryan Burgers
 */

class Twitter
{

	var $return_data	= '';
	var $cache_name		= 'twitter_timeline_cache';
	var $cache_expired	= FALSE;
	var $rate_limit_hit = FALSE;
	var $refresh		= 45;		// Period between cache refreshes, in minutes (purposely high default to prevent hitting twitter's rate limit on shared IPs - be careful)
	var $limit			= 20;
	var $parameters		= array();
	var $months			= array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	var $use_stale;


	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Twitter() {
		// Adding an old-style constructor allows use on older installs of EE2.
		Twitter::__construct();
	}

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function __construct()
	{
		$this->EE =& get_instance();
	}

	public function user()
	{
		// Fetch parameters
		$this->refresh		= $this->EE->TMPL->fetch_param('twitter_refresh', $this->refresh);
		$this->limit		= $this->EE->TMPL->fetch_param('limit', $this->limit);
		$this->use_stale	= $this->EE->TMPL->fetch_param('use_stale_cache', 'yes');
		$this->screen_name	= $this->EE->TMPL->fetch_param('screen_name');

		if (!$this->screen_name)
		{
			$this->EE->TMPL->log_item("Parameter screen_name was not provided");
			return;
		}

		// timeline type
		$timeline	= 'user';
		$log_extra	= "For User {$this->screen_name}";

		$this->parameters['screen_name'] = $this->screen_name;

		$this->EE->TMPL->log_item("Using '{$timeline}' Twitter Timeline {$log_extra}");

		// Create a unique ID for caching.
		$uniqueid = $timeline.'_timeline'.$this->screen_name;

		if (count($this->parameters))
		{
			foreach ($this->parameters as $k => $v)
			{
				$uniqueid .= '/' . urlencode($k) . '=' . urlencode($v);
			}
		}

		// retrieve statuses
		$statuses = $this->_fetch_data($uniqueid);

		if ( ! $statuses)
		{
			return;
		}


		// Some variables needed for the parsing process

		$count		= 0;
		$created_at	= array();


		// parse created_at date variables outside of the loop to save processing
		if (preg_match_all("/".LD."(user_)?created_at\s+format=(\042|\047)([^\\2]*?)\\2".RD."/s", $this->EE->TMPL->tagdata, $matches))
		{
			for ($i = 0; $i < count($matches['0']); $i++)
			{
				$matches['0'][$i] = str_replace(array(LD, RD), '', $matches['0'][$i]);
				$created_at[$matches['0'][$i]] = $this->EE->localize->fetch_date_params($matches['3'][$i]);
			}
		}

		$return_data = '';

		$count = 0;

		// Loop through all statuses and do our template replacements
		foreach ($statuses as $val)
		{

			// If this is a retweet, let's use that data instead
			$retweeted = FALSE;
			if (isset($val['retweeted_status'])) {
				$retweeted = TRUE;
				$retweeter = $val['user']['name'];
				$val = $val['retweeted_status'];
				$val['retweeter'] = $retweeter;
			}

			$tagdata = $this->EE->TMPL->tagdata;
			$count++;

			if ($count > $this->limit)
			{
				return;
			}

			// Link up anything that needs to be linked up
			if (isset($val['entities']) && is_array($val['entities']))
			{
				$find = array();
				$replace = array();

				foreach ($val['entities'] as $type => $found)
				{
					foreach ($found as $info)
					{
						switch($type)
						{
							case 'user_mentions':	$find[]		= '@'.$info['screen_name'];
										$replace[]	= "<a title='{$info['name']}' href='http://twitter.com/{$info['screen_name']}'>@{$info['screen_name']}</a>";
								break;
							case 'hashtags':		$find[]		= '#'.$info['text'];
													// Because EE's xss_clean replaces %23 with #, we need to use %2523; EE changes %25 into %, so we get %23.
													$replace[]	= "<a title='Search for {$info['text']}' href='http://twitter.com/search?q=%2523{$info['text']}'>#{$info['text']}</a>";
								break;
							case 'urls':			$find[]		= $info['url'];
								$displayurl = $info['url'];
								if (isset($info['display_url'])) { $displayurl = $info['display_url']; }
													$replace[]	= "<a title='{$info['expanded_url']}' href='{$info['url']}'>{$displayurl}</a>";
						}
					}
				}

				$val['text'] = str_replace($find, $replace, $val['text']);

				unset($find, $replace);
			}

			$val['id'] = $val['id_str'];

			// Add count

			$val['count'] = $count;


			// Clean the tweet

			$val['text'] = $this->EE->security->xss_clean($val['text']);
			$val['text'] = $this->EE->functions->encode_ee_tags($val['text'], TRUE);

			// Prep conditionals

			$cond	 = $val;
			$tagdata = $this->EE->functions->prep_conditionals($tagdata, $cond['user']);

			unset($cond['user']);
			$cond['retweeted'] = $retweeted;
			$tagdata = $this->EE->functions->prep_conditionals($tagdata, $cond);


			// Parse all found variables

			foreach ($this->EE->TMPL->var_single as $var_key => $var_val)
			{
				// parse {switch} variable

				if (preg_match("/^switch\s*=.+/i", $var_key))
				{
					$sparam = $this->EE->functions->assign_parameters($var_key);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);

						$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
					}

					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $sw, $tagdata);
				}


				// parse {created_at}

				if (isset($created_at[$var_key]))
				{
					$date = ($var_key[0] == 'u') ? $statuses[$key]['user']['user_created_at'] : $statuses[$key]['created_at'];

					$human_time = $this->_parse_twitter_date($date);

					// We already have GMT so we need $this->EE->localize->convert_human_date_to_gmt to
					// NOT do any localization.  Fib the Session userdata for sec.
					$dst		= $this->EE->session->userdata['daylight_savings'];
					$timezone	= $this->EE->session->userdata['timezone'];

					$this->EE->session->userdata['timezone'] = 'UTC';
					$this->EE->session->userdata['daylight_savings'] = 'n';

					$date = $this->EE->localize->convert_human_date_to_gmt($human_time);

					// reset Session userdata to original values
					$this->EE->session->userdata['timezone'] = $timezone;
					$this->EE->session->userdata['daylight_savings'] = $dst;

					foreach ($created_at[$var_key] as $dvar)
					{
						$var_val = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $date, TRUE), $var_val);
					}

					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $var_val, $tagdata);
				}


				// Parse {status_relative_date}

				if ($var_key == 'status_relative_date')
				{
					$human_time	= $this->_parse_twitter_date($val['created_at']);

					$date		= $this->EE->localize->set_server_time($this->EE->localize->convert_human_date_to_gmt($human_time));
					$tagdata	= $this->EE->TMPL->swap_var_single($var_key, $this->EE->localize->format_timespan($this->EE->localize->now - $date), $tagdata);
				}

				if ($var_key == 'permalink')
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $this->_build_permalink($val), $tagdata);
				}

				if ($var_key == 'reply_intent')
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $this->_build_reply_intent($val), $tagdata);
				}

				if ($var_key == 'retweet_intent')
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $this->_build_retweet_intent($val), $tagdata);
				}

				if ($var_key == 'favorite_intent')
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $this->_build_favorite_intent($val), $tagdata);
				}

				if ($var_key == 'relative_date')
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $this->_build_relative_date($val), $tagdata);
				}

				if ($var_key == 'iso_date')
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $this->_build_iso_date($val), $tagdata);
				}

				// Parse all others, main array, user array, all others

				if (isset($val[$var_key]))
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $val[$var_key], $tagdata);
				}
				elseif (isset($val['user'][$var_key]))
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, $val['user'][$var_key], $tagdata);
				}
				else
				{
					$tagdata = $this->EE->TMPL->swap_var_single($var_key, '', $tagdata);
				}
			}

			$return_data .= $tagdata;
		}

		return $return_data;
	}

	public function script() {
		return "<script type=\"text/javascript\" src=\"//platform.twitter.com/widgets.js\"></script>";
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch data
	 *
	 * Grabs and parses the Twitter status messages
	 *
	 * @access	public
	 * @return	array
	 */
	function _fetch_data($uniqueid)
	{
		$rawjson			= '';
		$cached_json		= $this->_check_cache($uniqueid);

		if ($this->cache_expired OR ! $cached_json)
		{
			$this->EE->TMPL->log_item("Fetching Twitter timeline remotely");

			if ( function_exists('curl_init'))
			{
				$rawjson = $this->_curl_fetch();
			}
			else {
				// We only support CURL, because that's what oauth uses.
				return;
			}
		}

		// Attempt to parse the data we have
		$json_obj = $this->_check_json($rawjson);

		if ( ! $json_obj)
		{
			// Did we try to grab new data? Tell them that it failed.
			if ( ! $cached_json OR $this->cache_expired)
			{
				$this->EE->TMPL->log_item("Twitter Timeline Error: Unable to retrieve statuses from Twitter.com");

				// Rate limit hit and no cached data?
				// We definitely need to write a cache file so we don't continue
				// to ask Twitter for data on every request.

				if ( ! $cached_json && $this->rate_limit_hit)
				{
					$this->_write_cache($rawjson, $uniqueid);
				}

				// Try to parse cache? Is it worth it?
				if ($this->use_stale != 'yes' OR ! $cached_json)
				{
					return FALSE;
				}

				$this->EE->TMPL->log_item("Twitter Timeline Using Stale Cache: ".$uniqueid);
			}
			else
			{
				$this->EE->TMPL->log_item("Twitter Timeline Retrieved From Cache.");
			}


			// Check the cache
			$json_obj = $this->_check_json($cached_json);


			// If we're hitting twitter's rate limit,
			// refresh the cache timestamp, even if the cache file
			// is the rate limiting message. We need to stop asking for data for a while.

			if ($this->rate_limit_hit && $this->cache_expired)
			{
				$this->_write_cache($cached_json, $uniqueid);
			}

			if ( ! $json_obj)
			{
				$this->EE->TMPL->log_item("Twitter Timeline Error: Invalid Cache File");
				return FALSE;
			}
		}
		else
		{
			// We have (valid) new data - cache it
			$this->_write_cache($rawjson, $uniqueid);
		}

		if ( ! is_array($json_obj) OR count($json_obj) == 0)
		{
			return FALSE;
		}

		return $json_obj;
	}

	// --------------------------------------------------------------------

	/**
	 * Check XML
	 *
	 * Checks the XML for validity and also looks for errors in the data.
	 *
	 * @access	public
	 * @param	object
	 * @return	array
	 */
	function _check_json($rawjson)
	{
		if ($rawjson == '')
		{
			return FALSE;
		}

		$json_obj = json_decode($rawjson, TRUE);

		if ($json_obj === NULL)
		{
			return FALSE;
		}

		// Check for error response
		if (isset($json_obj['errors']))
		{
			$error = $json_obj['errors'][0]['message'];
		}

		if (isset($error))
		{
			$this->rate_limit_hit = TRUE;
			$this->EE->TMPL->log_item("Twitter Timeline error: ".$error);
			return FALSE;
		}

		return $json_obj;
	}

	// --------------------------------------------------------------------

	/**
	 * Check Cache
	 *
	 * Check for cached data
	 *
	 * @access	public
	 * @param	string
	 * @param	bool	Allow pulling of stale cache file
	 * @return	mixed - string if pulling from cache, FALSE if not
	 */
	function _check_cache($url)
	{
		// Check for cache directory

		$dir = APPPATH.'cache/'.$this->cache_name.'/';

		if ( ! @is_dir($dir))
		{
			return FALSE;
		}

		// Check for cache file

        $file = $dir.md5($url);

		if ( ! file_exists($file) OR ! ($fp = @fopen($file, 'rb')))
		{
			return FALSE;
		}

		flock($fp, LOCK_SH);

		$cache = @fread($fp, filesize($file));

		flock($fp, LOCK_UN);

		fclose($fp);

        // Grab the timestamp from the first line

		$eol = strpos($cache, "\n");

		$timestamp = substr($cache, 0, $eol);
		$cache = trim((substr($cache, $eol)));

		if ( time() > ($timestamp + ($this->refresh * 60)) )
		{
			$this->cache_expired = TRUE;
		}

        return $cache;
	}

	// --------------------------------------------------------------------

	/**
	 * Write Cache
	 *
	 * Write the cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function _write_cache($data, $url)
	{
		// Check for cache directory

		$dir = APPPATH.'cache/'.$this->cache_name.'/';

		if ( ! @is_dir($dir))
		{
			if ( ! @mkdir($dir, 0777))
			{
				return FALSE;
			}

			@chmod($dir, 0777);
		}

		// add a timestamp to the top of the file
		$data = time()."\n".$data;


		// Write the cached data

		$file = $dir.md5($url);

		if ( ! $fp = @fopen($file, 'wb'))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);

		@chmod($file, 0777);
	}

	// --------------------------------------------------------------------

	/**
	 * curl Fetch
	 *
	 * Fetch Twitter statuses using cURL
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function _curl_fetch()
	{
		$data = '';

		//this is where we have modified the plugin to fetch our data via oauth

		$this->EE->load->model('twitter_model');
		$settings = $this->EE->twitter_model->get_settings();

		// Read in our saved access token/secret
		$access_token = $settings['access_token'];
		$access_token_secret = $settings['access_token_secret'];

		// Create our twitter API object
		$oauth = new TwitterOAuth($settings['consumer_key'], $settings['consumer_secret'], $access_token, $access_token_secret);
		$oauth->decode_json = FALSE;

		$params = array('include_rts'=>'true', 'screen_name' => $this->screen_name);
		$data = $oauth->get("statuses/user_timeline", $params);

		return $data;
	}

	// --------------------------------------------------------------------

	/**
	 * Parse Twitter Date
	 *
	 * Reformats Twitter's dates to a standard human time notation
	 * Twitter's dates are in the format: Fri Apr 13 15:34:45 +0000 2007
	 * Returns in YYYY-MM-DD HH:MM:SS
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function _parse_twitter_date($str)
	{
		$parts = explode(' ', $str);
		$month = array_keys($this->months, $parts[1]);
		$mm = sprintf("%02s", $month[0] + 1);

		return "{$parts[5]}-{$mm}-{$parts[2]} {$parts[3]}";

	}

	function _build_permalink($status) {
		return 'https://twitter.com/' . $status['user']['screen_name'] . '/statuses/' . $status['id_str'];
	}

	function _build_reply_intent($status) {
		return 'https://twitter.com/intent/tweet?in_reply_to=' . $status['id_str'];
	}

	function _build_retweet_intent($status) {
		return 'https://twitter.com/intent/retweet?tweet_id=' . $status['id_str'];
	}

	function _build_favorite_intent($status) {
		return 'https://twitter.com/intent/favorite?tweet_id=' . $status['id_str'];
	}

	function _build_relative_date($status, $now = NULL) {
		$dt = new DateTime($status['created_at']);
		if (is_null($now)) {
			$now = new DateTime();
		}
		$diff = $dt->diff($now);

		$output = '';

		if ($diff->d < 1) {
			if ($diff->h > 0) {
				$output = $diff->h . "h";
			}
			else if ($diff->i > 0) {
				$output = $diff->i . "m";
			}
			else {
				$output = $diff->s . "s";
			}
		}
		else {
			if ($dt->format('Y') == $now->format('Y')) {
				$output = $dt->format("j M");
			}
			else {
				$output = $dt->format("j M y");
			}
		}

		return $output;
	}

	function _build_iso_date($status) {
		return (new DateTime($status['created_at']))->format(DateTime::ISO8601);
	}

}

/* End of File: mod.module.php */
