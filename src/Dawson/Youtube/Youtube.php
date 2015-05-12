<?php namespace Dawson\Youtube;

use File;
use DB;
use Exception;
use Carbon\Carbon;

class Youtube {

	protected $client;
	protected $youtube;
	protected $snippet;
	protected $video;

	/**
	 * Constructor accepts the Google Client object, whilst setting the configuration options.
	 * @param \Google_Client $client
	 */
	public function __construct($development = false)
	{
		$this->client = new \Google_Client;
		$this->client->setApplicationName(config('youtube.application_name'));
		$this->client->setClientId(config('youtube.client_id'));
		$this->client->setClientSecret(config('youtube.client_secret'));
		$this->client->setScopes(config('youtube.scopes'));

		$redirect_uri = config('youtube.route_base_uri') ?
			config('youtube.route_base_uri') . '/' . config('youtube.redirect_uri') : 
			config('youtube.redirect_uri');
		$this->client->setRedirectUri(url($redirect_uri));

		$this->youtube = new \Google_Service_YouTube($this->client);
		$this->snippet = new \Google_Service_YouTube_VideoSnippet();
		$this->video = new \Google_Service_YouTube_Video();

		$accessToken = $this->getLatestAccessTokenFromDB();

		if ($accessToken) {
			$this->client->setAccessToken($accessToken);
		}
	}

	/**
	 * Saves the access token to the database.
	 * @param $accessToken
	 */
	public function saveAccessTokenToDB($accessToken)
	{
		$data = [
			'access_token' => $accessToken,
			'created_at' => Carbon::now(),
		];

		DB::table('youtube_access_tokens')->insert($data);
	}
	/**
	 * Returns the last saved access token, if there is one, or null
	 * @return mixed
	 */
	public function getLatestAccessTokenFromDB()
	{
		$latest = DB::table('youtube_access_tokens')
				->orderBy('created_at', 'desc')
				->first();

		if ($latest) { return $latest->access_token; }

		return null;
	}

	/**
	 * Upload the video to YouTube
	 * @param  string 	$path    	The path to the file you wish to upload.
	 * @param  array 	$snippet 	An array of data.
	 * @param  string 	$status  	The status of the uploaded video, set to 'public' by default.
	 * @return mixed
	 */
	public function upload($path, array $snippet, $privacyStatus = 'public', $development = false)
	{
		/* ------------------------------------
		#. Get Access Token
		------------------------------------ */
		$accessToken = $this->client->getAccessToken();

		/* ------------------------------------
		#. Authenticate if no Access Token
		------------------------------------ */
		if(is_null($accessToken))
		{
			if($development) {
				$this->client->setDeveloperKey(config('youtube.developer_key'));
			} else {
				throw new Exception('An access token is required to attempt an upload.');
			}
		}

		/* ------------------------------------
		#. Refresh Access token if needed
		------------------------------------ */
		if($this->client->isAccessTokenExpired())
		{
			$accessToken = json_decode($accessToken);
			$refreshToken = $accessToken->refresh_token;
			$this->client->refreshToken($refreshToken);
			$newAccessToken = $this->client->getAccessToken();
			$this->saveAccessTokenToDB($newAccessToken);
		}

		/* ------------------------------------
		#. Setup the Snippet
		------------------------------------ */
		$this->snippet->setTitle($snippet['title']);
		$this->snippet->setDescription($snippet['description']);

		/* ------------------------------------
		#. Set the Privacy Status
		------------------------------------ */
		$status = new \Google_Service_YouTube_VideoStatus();
		$status->privacyStatus = $privacyStatus;

		/* ------------------------------------
		#. Set the Snippet & Status
		------------------------------------ */
		$this->video->setSnippet($this->snippet);
		$this->video->setStatus($status);

		/* ------------------------------------
		#. Set the Chunk Size
		------------------------------------ */
		$chunkSize = 1 * 1024 * 1024;

		/* ------------------------------------
		#. Set the defer to true
		------------------------------------ */
		$this->client->setDefer(true);

		/* ------------------------------------
		#. Build the request
		------------------------------------ */
		$request = $this->youtube->videos->insert('status,snippet', $this->video);

		/* ------------------------------------
		#. Upload
		------------------------------------ */
		$media = new \Google_Http_MediaFileUpload(
			$this->client,
			$request,
			'video/*',
			null,
			true,
			$chunkSize
		);

		$media->setFileSize(File::size($path));

		/* ------------------------------------
		#. Read the file and upload in chunks
		------------------------------------ */
		$upload = false;
		$handle = fopen(public_path($path), "rb");
		while (!$upload && !feof($handle)) {
			$chunk = fread($handle, $chunkSize);
			$upload = $media->nextChunk($chunk);
		}
		fclose($handle);

		dd('Fails');

		/* ------------------------------------
		#. Set the defer to false again
		------------------------------------ */
		$this->client->setDefer(true);

		dd($upload);
	}
	
	/**
	 * Pass method calls to the Google Client.
	 * @param  $method
	 * @param  $args
	 * @return mixed
	 */
	public function __call($method, $args)
	{
		return call_user_func_array([$this->client, $method], $args);
	}

}