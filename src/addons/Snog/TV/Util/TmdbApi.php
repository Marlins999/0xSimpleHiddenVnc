<?php

namespace Snog\TV\Util;


use XF\InputFilterer;

class TmdbApi
{
	protected $url = 'https://api.themoviedb.org/3/';

	protected $imageUrl = 'https://image.tmdb.org/t/p/';

	protected $language;

	protected $licenseKey;

	protected $errors = [];

	public function __construct($licenseKey = null)
	{
		$this->licenseKey = $licenseKey ?: \XF::options()->TvThreads_apikey;
		$this->language = \XF::options()->TvThreads_language;
	}

	public function getErrors()
	{
		return $this->errors;
	}

	public function getShow($showId, $subRequests = [])
	{
		$params = [];
		if ($subRequests !== null)
		{
			$params = ['append_to_response' => implode(',', $subRequests)];
		}

		$response = $this->request($this->url . 'tv/' . $showId, $params);

		return $response ? $this->filterResponse($response->getBody()) : [];
	}

	public function getSeason($showId, $season, array $subRequests = null)
	{
		$params = [];
		if ($subRequests !== null)
		{
			$params = ['append_to_response' => implode(',', $subRequests)];
		}

		$response = $this->request($this->url . 'tv/' . $showId . '/season/'. $season, $params);

		return $response ? $this->filterResponse($response->getBody()) : [];
	}

	public function getEpisode($showId, $season, $episode, $subRequests = [])
	{
		$params = [];
		if ($subRequests !== null)
		{
			$params = ['append_to_response' => implode(',', $subRequests)];
		}

		$response = $this->request($this->url . 'tv/' . $showId . '/season/'. $season .  '/episode/' . $episode, $params);

		return $response ? $this->filterResponse($response->getBody()) : [];
	}

	public function getGenres()
	{
		$response = $this->request($this->url . 'genre/tv/list');

		return $response ? $this->filterResponse($response->getBody()) : [];
	}

	public function getPoster($srcPath, $size = 'original')
	{
		$response = $this->request($this->imageUrl . $size . $srcPath);
		if ($response)
		{
			return $response->getBody();
		}

		return null;
	}

	protected function filterResponse($body)
	{
		$data = \GuzzleHttp\json_decode($body, true);
		$dataFilter = new InputFilterer();
		return $dataFilter->filter($data, 'array');
	}

	protected function request($url, $params = [], $options = [])
	{
		$params = array_merge([
			'api_key' => $this->licenseKey,
			'lang' => $this->language,
		], $params);

		try
		{
			$response = \XF::app()->http()->client()->get($url . '?' . http_build_query($params), $options);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			if (null !== $e->getResponse())
			{
				$error = 'TMDb Error ' . $e->getResponse()->getStatusCode();
				$error .= ': ' . $e->getResponse()->getReasonPhrase();
			}
			else
			{
				$error = $e->getMessage();
			}

			$this->errors[] = $error;
		}

		return $response ?? null;
	}
}