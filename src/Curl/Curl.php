<?php namespace Curl;

/**
 * Easy Curl
 *
 * @package curl
 * @author EThaiZone <ethaizone@hotmail.com>
 * @version 1.1.1
 */
class Curl
{

	private $url = "";
	private $header = array();
	private $follow = true;
	private $cookie = "";
	private $info = "";
	private $auth_basic = "";
	private $error = null;

	/**
	 * Init CURL
	 *
	 * @param string $url URL
	 * @return curl
	 */
	public function __construct($url)
	{
		$this->url = $url;
		$this->url = str_replace(" ", "%20", $this->url );
		$this->pt = curl_init();
		curl_setopt($this->pt, CURLOPT_URL, $this->url);
		curl_setopt($this->pt, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->pt, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($this->pt, CURLOPT_TIMEOUT, 600);
		curl_setopt($this->pt, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($this->pt, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($this->pt, CURLOPT_FOLLOWLOCATION, $this->follow);
		curl_setopt($this->pt, CURLOPT_HEADER, 1);
		return $this;
	}

	/**
	 * Add referer
	 *
	 * @param type $referer
	 * @return curl
	 */
	public function referer($referer)
	{
		curl_setopt($this->pt, CURLOPT_REFERER, $referer);
		return $this;
	}

	/**
	 * Disable auto follow url
	 *
	 * @return curl
	 */
	public function nofollow()
	{
		$this->follow = false;
		curl_setopt($this->pt, CURLOPT_FOLLOWLOCATION, false);
		return $this;
	}

	/**
	 * Insert POST data
	 *
	 * @param array $postdata Array of Post data
	 * @return curl
	 */
	public function post($postdata)
	{
		curl_setopt($this->pt, CURLOPT_POST, TRUE);
		curl_setopt($this->pt, CURLOPT_POSTFIELDS, $postdata);
		return $this;
	}

	public function auth_basic($username, $password)
	{
		$this->auth_basic  =  array($username, $password);
		curl_setopt($this->pt, CURLOPT_USERPWD, $username . ":" . $password);
		return $this;
	}

	/**
	 * Assign IP and PORT for proxy
	 *
	 * @param string $ip_port Format IP:PORT
	 * @return curl
	 */
	public function proxy($ip_port)
	{
		curl_setopt($this->pt, CURLOPT_PROXY, $ip_port);
		return $this;
	}

	/**
	 * Enable COOKIE for cache and send
	 *
	 * @param string $cache_cookie Path for cookie cache file
	 * @return curl
	 */
	public function cookie($cache_cookie)
	{
		$this->cookie = $cache_cookie;
		if(file_exists($cache_cookie)) curl_setopt($this->pt, CURLOPT_COOKIEFILE, $cache_cookie);
		curl_setopt($this->pt, CURLOPT_COOKIEJAR, $cache_cookie);
		return $this;
	}

	/**
	 * Assign HTTP Header
	 *
	 * @param mixed $header String or Array of HTTTP Header
	 * @return \curl
	 */
	public function header($header)
	{
		$this->header= (!is_array($header)) ? array($header) : $header;
		return $this;
	}

	/**
	 * Enable GZIP Compression Mode (Reduce bandwidth but increase CPU)
	 *
	 * @return curl
	 */
	public function gzip()
	{
		$this->header = array_merge(array("Accept-Encoding: gzip,deflate"), $this->header);
		return $this;
	}

	/**
	 * Execute CURL
	 *
	 * @return array
	 */
	public function exec()
	{
		$header = array_merge(array(
			"User-Agent: " . (empty($_SERVER['HTTP_USER_AGENT']) ? 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.64 Safari/537.11' : $_SERVER['HTTP_USER_AGENT']),
			"Accept-Charset: utf-8",
			'Expect:',	//Override Expect: 100-continue
		), $this->header)
		curl_setopt($this->pt, CURLOPT_HTTPHEADER, $header);
		$result = curl_exec ($this->pt);
		$this->info = curl_getinfo($this->pt);


		if ($result === false)
		{
			$this->error = array(
				'error' => curl_error($this->pt),
				'no'    => curl_errno($this->pt)
			);
		}

		curl_close ($this->pt);

		@chmod($this->cookie, 0666);

		if ($this->error)
		{
			return false;
		}

		list($res_header, $res_content) = $this->_split_respond($result);
		$res_content = $this->_decode_body($res_header, $res_content);

		if(!empty($res_header['location']) && $this->follow == true)
		{
			$sub = new self($res_header['location']);
			$sub->referer(str_replace(" ", "%20", $this->url ));
			if(!empty($this->cookie)) $sub->cookie($this->cookie);
			if(!empty($this->auth_basic)) $sub->auth_basic($this->auth_basic[0], $this->auth_basic[1]);
			$result = $sub->exec();
			if(!empty($result[0]['ref']))
			{
				if(is_array($result[0]['ref']))
					$result[0]['ref'][] = $this->url ;
				else {
					$result[0]['ref'] = array($this->url );
				}
			}
			return $result;
		}

		return array($res_header, $res_content);

	}

	/**
	 * Get CURL info
	 * @link http://php.net/manual/en/function.curl-getinfo.php
	 * @return array
	 */
	public function info()
	{
		return $this->info;
	}

	/**
	 * Get error
	 *
	 * @return array
	 */
	public function error()
	{
		return $this->error;
	}

	/**
	 * Split header and content from response that returned
	 *
	 * @param string $str HTTP Response
	 * @return boolean
	 */
	private function _split_respond($str)
	{
		if(empty($str)) return FALSE;
		$split = preg_split ( "#\r?\n\r?\n#", $str, 2, PREG_SPLIT_NO_EMPTY );
		$header = $this->_split_header($split[0]);
		if(empty($split[1]) and empty($header['location']))
		{
			echo '<pre>'.print_r($header, true).'</pre>';
			//die('Curl: No content return.');
			return false;
		}
		$html = empty($split[1]) ? '' : $split[1];
		return array($header,$html);
	}

	/**
	 * Decode HTTP Content such as GZIP
	 *
	 * @param array $header HTTP Header (Array)
	 * @param string $content HTTP Content
	 * @return type
	 */
	private function _decode_body($header, $content)
	{
		if ( isset($header['content-encoding']))
		{
			$content = gzinflate(substr(substr($content, 10), 0, -8));
		}
		return $content;
	}

	/**
	 * Split HTTP Header to array
	 *
	 * @param string $str HTTP Header
	 * @return array
	 */
	private function _split_header($str)
	{

		$part = preg_split ( "/\r?\n/", $str, -1, PREG_SPLIT_NO_EMPTY );
		$out = array ();

		for ( $h = 0; $h < sizeof ( $part ); $h++)
		{
			if ( $h == 0 )
			{
				$v = explode ( ' ', $part[$h] );
				$out['status'] = trim($v[1]);
				continue;
			}

			@list($k, $v) = preg_split("#:\s?#", @$part[$h], 2,  PREG_SPLIT_NO_EMPTY);

			if ( $k == 'Set-Cookie' )
				$out['cookies'][] = trim($v);
			else if ( $k == 'Content-Type' &&  ($cs = strpos ( $v, ';' ) ) !== false)
				$out[strtolower($k)] = trim(substr ( $v, 0, $cs ));
			else
				$out[strtolower($k)] = trim($v);

			if(preg_match("#charset=([a-z0-9\-]+)#i", $v, $match))
			{
				$out['charset'] = trim($match[1]);
			}
		}

		return $out;
	}
}

?>