<?php


namespace Snog\TV\Util;


class Tmdb
{
	public static function parseShowId($url)
	{
		if (stristr($url, 'themoviedb'))
		{
			// preg_match_all USED FOR FUTURE API PARAMETER CAPTURING
			preg_match_all('/\d+/', $url, $matches);

			if (!empty($matches))
			{
				$showId = $matches[0][0];
			}
			else
			{
				$showId = '';
			}
		}
		else if (is_numeric($url))
		{
			if (intval($url) == 0)
			{
				$showId = '';
			}
			else
			{
				$showId = $url;
			}
		}
		else
		{
			$showId = '';
		}

		return $showId;
	}
}