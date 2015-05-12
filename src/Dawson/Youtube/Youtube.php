<?php namespace Dawson\Youtube;

use File, DB, Exception;

use Carbon\Carbon;

class Youtube {

	protected $client;
	protected $youtube;
	protected $snippet;
	protected $status;
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
		$this->client->setRedirectUri(url(config('youtube.redirect_uri')));
		$this->client->setScopes(config('youtube.scopes'));
		$this->youtube = new \Google_Service_YouTube($this->client);
		$this->snippet = new \Google_Service_YouTube_VideoSnippet(); 
		$this->status = new \Google_Service_YouTube_VideoStatus();
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
	public function upload($path, array $snippet, $status = 'public', $development = false)
	{

		/* ------------------------------------
		#. Authenticate if no Access Token
		------------------------------------ */
		if(!$this->client->getAccessToken())
		{
			if($development) {
				$this->client->setDeveloperKey(config('youtube.developer_key'));
			} else {
				return redirect()->to($this->client->createAuthUrl());	
			}
		}

		/* ------------------------------------
		#. Setup the Snippet
		------------------------------------ */
		$this->snippet->setTitle($snippet['title']);
		$this->snippet->setDescription($snippet['description']);

		/* ------------------------------------
		#. Set the Privacy Status
		------------------------------------ */
		$this->status->privacyStatus = $status;

		/* ------------------------------------
		#. Set the Snippet & Status
		------------------------------------ */
		$this->video->setSnippet($this->snippet);
		$this->video->setStatus($this->status);

		/* ------------------------------------
		#. Set the defer to true
		------------------------------------ */
		$this->client->setDefer(true);

		/* ------------------------------------
		#. Set the Chunk Size
		------------------------------------ */
		$chunkSize = 1 * 1024 * 1024;

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
		$status = false;
		$handle = fopen(public_path($path), "rb");
		while (!$status && !feof($handle)) {
			$chunk = fread($handle, $chunkSize);
			$status = $media->nextChunk($chunk);
		}
		fclose($handle);

		/* ------------------------------------
		#. Set the defer to false again
		------------------------------------ */
		$this->client->setDefer(true);
	}

}