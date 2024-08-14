<?php
// *	@copyright	OPENCART.PRO 2011 - 2017.
// *	@forum	http://forum.opencart.pro
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerToolWarmHtmlCache extends Controller {
	static $sitemap = 'https://slotsjudge.com/sitemap.xml';
	static $rtp_mp = 0.2;
	static $slots_limit = 8;
	static $casinos_limit = 3;
	static $codes = [
		'DE',
		'GB'
	];
	static $langs = [
		'en-gb'
	];
	static $delay = 1;
	static $threads = 4;
	static $check_file = DIR_SYSTEM . 'warmer/check.lock';
	static $lock_file = DIR_SYSTEM . 'warmer/warmer.lock';
	static $log_file = DIR_SYSTEM . 'warmer/warmer.log';
	static $headers = [];
	static $user_agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3694.0 Safari/537.36 Chrome-Lighthouse 623";

	private $reset = true;
	
	public function index() {
		if(!$this->isCli()){
			return new Action('error/not_found');
		}
		
		set_time_limit(0);
		
		if(file_exists(self::$lock_file) && (filemtime(self::$lock_file) + 86400) < time()){
			unlink(self::$lock_file);
		}
		
		if(file_exists(self::$lock_file)){
			return;
		}
		
		unlink(self::$log_file);
		
		file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] start warm up cache ." . PHP_EOL, FILE_APPEND);
		
		file_put_contents(self::$lock_file, "[" . date("d-m-Y H:i:s") . "]" . PHP_EOL, FILE_APPEND);
		
		if(file_exists(self::$check_file)){
			unlink(self::$check_file);
		}
		
		$urls = [];

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "url_alias WHERE route = 'common/shortlink' ORDER BY args");

		$url_alias = current($query->rows);
		if ($url_alias['args'] === '') {
			array_shift($query->rows);
			$link_go = $url_alias['url'];
		} else {
			$link_go = '';
		}

		foreach ($query->rows as $url_alias) {
			$urls[] = $this->url->getHostUrl() . str_replace('{:common/shortlink}', $link_go, $url_alias['url']);
		}
		
		if (defined('isDevDomain') && constant('isDevDomain')) {
			$input_urls = $this->process_sitemap($this->url->getHostUrl() . '/sitemap.xml');
		} else {
			$input_urls = $this->process_sitemap(self::$sitemap);
		}
		
		if (!empty($input_urls)) {
			foreach ($input_urls as $url) {
				if(strpos($url, rtrim(HTTP_CATALOG, '/') . '/image/data/') !== false || strpos($url, rtrim(HTTPS_CATALOG, '/') . '/image/data/') !== false){
					continue;
				}
				
				$urls[] = $url;
			}
		}
		
		file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] urlset build finished." . PHP_EOL, FILE_APPEND);
		
		$urls = array_unique($urls);

		if($this->reset){
			$this->rm_dir(DIR_HTMLCACHE);

			file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] Reset html cache: succes." . PHP_EOL, FILE_APPEND);
		}
				
		// Visit links				
		foreach (array_chunk($urls, self::$threads) as $key_chunk => $chunk) {
			$mh = curl_multi_init();
			$ch = [];
			
			foreach ($chunk as $key => $url) {
				foreach(self::$codes as $code){
					/*
					if($code){
						$file = DIR_HTMLCACHE . str_replace(str_replace(['http://','https://', 'sitemap.xml'], ['','',''], self::$sitemap), str_replace(['http://','https://', 'sitemap.xml'], ['','',''], self::$sitemap) . ($code ? $code . '/' : ''), str_replace(['http://','https://'],['',''], $url)) . 'index.html';
					}else{
						$file = DIR_HTMLCACHE . str_replace(['http://','https://'],['',''], $url) . 'index.html';
					}
					
					if(file_exists($file)){
						unlink($file);

						file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] cleaned: " . str_replace(DIR_ROOT, '', $file) . PHP_EOL, FILE_APPEND);
					}
					*/

					$ch[$key] = curl_init();

					curl_setopt($ch[$key], CURLOPT_URL, $url . ($code ? '?cc=' . $code : ''));
					curl_setopt($ch[$key], CURLOPT_USERAGENT, self::$user_agent);
					curl_setopt($ch[$key], CURLOPT_SSL_VERIFYHOST, false);
					curl_setopt($ch[$key], CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch[$key], CURLOPT_FOLLOWLOCATION, true);
					curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch[$key], CURLOPT_TIMEOUT_MS, 10000);
					curl_setopt($ch[$key], CURLOPT_HTTPHEADER, self::$headers);

					curl_multi_add_handle($mh, $ch[$key]);
				}
			}
			
			$running = null;
			
			do {
				curl_multi_exec($mh, $running);
			} while ($running);

			foreach ($ch as $key => $curl) {
				curl_multi_remove_handle($mh, $curl);
			}

			curl_multi_close($mh);
			
			foreach ($chunk as $key => $url) {
				foreach(self::$codes as $code){
					file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] warmed up: " . $url . ($code ? '?cc=' . $code : '') . PHP_EOL, FILE_APPEND);
				}
			}
			
			if(self::$delay){
				sleep(self::$delay);
			}
		}
		
		unlink(self::$lock_file);
		file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] warm up process finished." . PHP_EOL, FILE_APPEND);
	}

	public function start(){
		if(!$this->isCli()){
			return;
		}
		
		if(!file_exists(self::$check_file) && !file_exists(self::$lock_file)){
			file_put_contents(self::$check_file, time(), FILE_APPEND);
		}
	}
	
	public function check(){
		if(!$this->isCli()){
			return;
		}
		
		if(file_exists(self::$check_file)){
			unlink(self::$check_file);
			
			if($this->reset){
				$this->reset = false;
			}

			return $this->index();
		}
	}
	
	public function updateSimilarSlots() {
		$casino_slots = $this->model_content_casino_slot->getCasinoSlotList([
			'only_slots' => 1
		]);
		
		foreach ($casino_slots as $data) {
			$this->cache->delete('similar.slots.' . (int)$data['casino_slot_id'], 'similar');
			
            $this->model_content_casino_slot->getSimilarSlots($data['casino_slot_id'], [
                'f_rtp_min' => $data['rtp'] - self::$rtp_mp,
                'f_rtp_max' => $data['rtp'] + self::$rtp_mp,
                'limit'     => self::$slots_limit,
				'by_country' => false
            ]);
			
			$this->model_content_casino_slot->getSimilarSlots($data['casino_slot_id'], [
                'f_rtp_min' => $data['rtp'] - self::$rtp_mp,
                'f_rtp_max' => $data['rtp'] + self::$rtp_mp,
                'limit'     => self::$slots_limit,
				'by_country' => true
            ]);
			
			file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] similar slots updated for: " . $data['title'] . "." . PHP_EOL, FILE_APPEND);
        }
		
		return true;
	}
	
	public function updateSimilarCasinos() {
		$casinos = $this->model_content_casino->getCasinoList([
			'only_casinos' => 1
		]);
		
		foreach ($casinos as $data) {
			$this->cache->delete('similar.casino.' . (int)$data['casino_id'], 'similar');
			
            $this->model_content_casino->getSimilarCasinos($data['casino_id'], [
                'limit'     => self::$casinos_limit,
				'by_country' => false
            ]);
			
			$this->model_content_casino->getSimilarCasinos($data['casino_id'], [
                'limit'     => self::$casinos_limit,
				'by_country' => true
            ]);
			
			file_put_contents(self::$log_file, "[" . date("d-m-Y H:i:s") . "] similar casinos updated for: " . $data['title'] . "." . PHP_EOL, FILE_APPEND);
        }
		
		return true;
	}
	
	public function process_sitemap($url)
    {
        // URL:s array
        $urls = array();

        // Grab sitemap and load into SimpleXML
        $sitemap_xml = @file_get_contents($url,false,$this->context);

        if(($sitemap = @simplexml_load_string($sitemap_xml)) !== false)
        {
            // Process all sub-sitemaps
            if(count($sitemap->sitemap) > 0)
            {
                foreach($sitemap->sitemap as $sub_sitemap)
                {
                    $sub_sitemap_url = (string)$sub_sitemap->loc;
                    $urls = array_merge($urls, $this->process_sitemap($sub_sitemap_url));
                }
            }

            // Process all URL:s
            if(count($sitemap->url) > 0)
            {
                foreach($sitemap->url as $single_url)
                {
                    $urls[] = (string)$single_url->loc;
                }
            }

            return $urls;
        }
        else
        {
            return array();
        }
    }

	private function rm_dir($dir) {
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		
		foreach ($files as $file) {
			if ($file->isDir()) {
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
	}
	
	private function isCli(): bool
	{
		return PHP_SAPI === 'cli';
	}
}
