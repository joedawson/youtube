<?php

return [
	
	/**
	 * Application Name.
	 */
	'application_name' => 'Your Application',

	/**
	 * Client ID.
	 */
	'client_id' => getenv('GOOGLE_CLIENT_ID'),

	/**
	 * Client Secret.
	 */
	'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),

	/**
	 * Redirect URI, this does not include your TLD.
	 * Example: 'callback' would be http://domain.com/callback
	 */
	'redirect_uri' => 'callback',

	/**
	 * Scopes.
	 */
	'scopes' => [
		'https://www.googleapis.com/auth/youtube',
		'https://www.googleapis.com/auth/youtube.upload',
		'https://www.googleapis.com/auth/youtube.readonly'
	],

	/**
	 * Developer key.
	 */
	'developer_key' => getenv('GOOGLE_DEVELOPER_KEY')

];