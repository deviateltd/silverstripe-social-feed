<?php

use \Abraham\TwitterOAuth\TwitterOAuth;

class SocialFeedProviderTwitter extends SocialFeedProvider implements SocialFeedProviderInterface
{
	private static $db = array (
		'ConsumerKey' => 'Varchar(400)',
		'ConsumerSecret' => 'Varchar(400)',
		'AccessToken' => 'Varchar(400)',
		'AccessTokenSecret' => 'Varchar(400)',
		'ScreenName' => 'Varchar',
		'TweetModeExtended' => 'Boolean',
		'ShowReTweetedImages' => 'Boolean',
	);

	private static $has_one = array(
		'DefaultImage' => 'Image'
	);

	private static $field_labels = array (
		'TweetModeExtended' => 'Extended mode (use if images are not showing)',
		'ShowReTweetedImages' => 'Show images in re-tweets'
	);

	private static $singular_name = 'Twitter Provider';
	private static $plural_name = 'Twitter Provider\'s';

	private $type = 'twitter';

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab('Root.Main', new LiteralField('sf_html_1', '<h4>To get the necessary Twitter API credentials you\'ll need to create a <a href="https://apps.twitter.com" target="_blank">Twitter App.</a></h4>'), 'Label');
		$fields->addFieldsToTab('Root.Main', new LiteralField('sf_html_2', '<p>You can manually grant permissions to the Twitter App, this will give you an Access Token and Access Token Secret.</h5><p>&nbsp;</p>'), 'Label');
		$fields->addFieldsToTab('Root.Main', $uploadField = new UploadField($name = 'DefaultImage', $title = 'Default image to use if there is none in tweet' ));
		$uploadField->setAllowedExtensions(array('jpg', 'jpeg', 'png', 'gif'));
		return $fields;
	}

	public function getCMSValidator()
	{
		return new RequiredFields(array('ConsumerKey', 'ConsumerSecret'));
	}

	/**
	 * Return the type of provider
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	public function getFeedUncached()
	{
		// NOTE: Twitter doesn't implement OAuth 2 so we can't use https://github.com/thephpleague/oauth2-client
		$connection = new TwitterOAuth($this->ConsumerKey, $this->ConsumerSecret, $this->AccessToken, $this->AccessTokenSecret);
		$parameters = ['count' => 25, 'exclude_replies' => true];
		if($this->ScreenName)
		{
			$parameters['screen_name'] = $this->ScreenName;
		}
		if($this->TweetModeExtended)
		{
			$parameters['tweet_mode'] = "extended";
		}
		$result = $connection->get('statuses/user_timeline', $parameters);
		if (isset($result->error)) {
			user_error($result->error, E_USER_WARNING);
		}
		return $result;
	}

	/**
	 * @return HTMLText
	 */
	public function getPostContent($post) {
		$text = '';
		if($this->TweetModeExtended && isset($post->full_text)) {
			$text = $post->full_text;
		} elseif(isset($post->text)) {
			$text = $post->text;
		}
		$text = preg_replace('/(https?:\/\/[a-z0-9\.\/]+)/i', '<a href="$1" target="_blank">$1</a>', $text); 

		$result = DBField::create_field('HTMLText', $text);
		return $result;
	}

	/**
	 * Get the creation time from a post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getPostCreated($post)
	{
		return $post->created_at;
	}

	/**
	 * Get the post URL from a post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getPostUrl($post)
	{
		return 'https://twitter.com/' . (string) $post->user->id .'/status/' . (string) $post->id;
	}

	/**
	 * The user's name who tweeted
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getUserName($post)
	{
		return $post->user->name;
	}

	/**
	 * The first image for a Tweet
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getImage($post)
	{
		if(property_exists($post->entities, 'media') && $post->entities->media[0]->media_url_https) {
			return $post->entities->media[0]->media_url_https;
		} elseif($this->ShowReTweetedImages && isset($post->retweeted_status) && property_exists($post->retweeted_status, 'entities')&& property_exists($post->retweeted_status->entities, 'media') && $post->retweeted_status->entities->media[0]->media_url_https) {
			return $post->retweeted_status->entities->media[0]->media_url_https;
		} elseif(isset($this->DefaultImageID)){
			$file = File::get_by_id('File', $this->DefaultImageID);
			if($file && $file->exists()) {
				return '/' . $file->Filename;
			}
		}
	}
}
