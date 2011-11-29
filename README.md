#TGL Twitter

TGL Twitter is nearly an identical port of EllisLabs Twitter Timeline plugin, however TGL Twitter has a CP Backend, allowing a user to authenticate with Twitter using oAuth.  

The initial reason for building this was because Twitter's Streaming API, which does not require authentication, limits your request by IP Address.  On a shared hosting setup, where many sites can be run off of one IP Address, this presents a problem.

The syntax for the modle is nearly identical to that of the Twitter Timeline plugin, so please refer to that for template tag questions.

This functionality of this plugin could easily be expanded to use more of Twitters API methods, I just originally needed it to display tweets.

##Requirements

* EE 2.0
* Twitter Account

##Installation
1. Add-ons -> Modules -> TGL_Twitter -> Install
2. Login into http://dev.twitter.com Note: This does not have to be the account you are going to authorize with, or the account you are going to be displaying tweets for.  It is just the account that "owns" the application (your site).
3. Click "Create a new application" button.
4. Fillout the <i>Create an Application</i> form.  You do not need to enter a Callback URL.
5. Copy the Consumer Key and Consumer Secret, under the oAuth settings, and paste them into the Form in the Module CP.  Click "Update".
6. Click "Generate new Request Token".
7. Follow on-page instructions to get PIN number, and update form with PIN.

##Template Tags

Please see: http://expressionengine.com/downloads/details/twitter_timeline/

**keep in mind that all tags use {exp:tgl_twitter} instead of {exp:twitter_timeline}**

## Known Issues

Please take note that on some API calls, such as user timeline,  if the API does not detect a valid oAuth token it will just default to the unauthenticated API conditions.  An example of this is if you install the module, then add in your template tags without authenticating first, data will still be shown.  However, this data will be rate limited to 150 and is returned based on unauthenticated API conditions.

