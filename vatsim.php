<?php

class VATSIM {
	
	private $f_orig;
	private $f_bin;
	private $v_status;
	private $v_map_data;
	private $is_debug;
	public $vdata_upd;
	
	function __construct($debug = false) {
		$this->f_orig = '/data/';
		$this->f_bin = '/bin/';

		$this->v_status = 'https://status.vatsim.net/status.json';
		$this->v_map_data = 'https://api.vatsim.net/api/map_data/';
		
		if ($debug) {
			$this->is_debug = true;
			error_reporting(E_ALL);
			echo realpath(__FILE__).'<br>';
		} else {
			$this->is_debug = false;
			error_reporting(0);
		}

		$this->vdata_upd = false;
	}
	
	function get_filetype($datatype) {
		$v_status = basename($this->v_status);
		$vatspy_status = 'VATSpy.json';
		switch ($datatype) {
			case 'vatsim-status':
				$copy = $this->curl_copy($this->v_status,$v_status);
				break;
			case 'vatsim-data':
				// check if file exists and is already current
				if ($data = $this->get_cache_file('vdata.bin')) {
					if (strtotime($data['general']['update_timestamp'].' +1 minute') > time()) {
						break;
					}
				}

				if (!file_exists(__DIR__.$this->f_orig.$v_status)) {
					$f = new VATSIM();
					$f->get_filetype('vatsim-status');
				} else {
					// get url from status file
					$vdata = json_decode(file_get_contents(__DIR__.$this->f_orig.$v_status),true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
					} else if (isset($vdata['data']['v3'])) {
						foreach ($vdata['data']['v3'] as $url) {
							if ($copy = $this->curl_copy($url,basename($url))) {
								$write = $this->write_cache_file(json_decode(file_get_contents($copy),true),'vdata.bin');
								$this->vdata_upd = true;
								break;
							}
						}
					}
				}
				break;
			case 'vatspy':
				$resp = $this->curl_resp($this->v_map_data, ['Accept: application/json']);

				$vatspy_remote = json_decode($resp, true);

				if (json_last_error() !== JSON_ERROR_NONE) {
					throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
				} else if (file_exists(__DIR__.$this->f_orig.$vatspy_status)) {
					$vatspy = json_decode(file_get_contents(__DIR__.$this->f_orig.$vatspy_status),true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
					} else if ($vatspy['current_commit_hash'] == $vatspy_remote['current_commit_hash']) {
						break;	// hash on local file is already up-to-date
					} else {
						// trigger VATSpy data file updates
						$f = new VATSIM();
						$f->get_filetype('vatspy-data');
						$f->get_filetype('vatspy-geojson');
					}
				}

				$copy = $this->curl_copy($this->v_map_data,$vatspy_status);
				break;
			case 'vatspy-data':
				$vatspy = $this->get_json($this->v_map_data,$vatspy_status);
				if ($copy = $this->curl_copy($vatspy['vatspy_dat_url'],'VATSpy.dat')) {
					foreach (file($copy) as $apt) {
						if (preg_match('/^([A-Z]{4})(?:\|([^\|]*)){4}\|([^\1]{4})\2?/i',$apt,$m)) {
							$vatspy['FIR'][] = strtolower($m[3]);
							$vatspy['ICAO'][] = strtolower($m[1]);
							if (!empty($m[2]) && $m[2]!=$m[3]) $vatspy['IATA'][] = strtolower($m[2]);
							else $vatspy['IATA'][] = '';
						}
					}
					$write = $this->write_cache_file($vatspy,'vatspy.bin');
				}
				break;
			case 'vatspy-geojson':
				$vatspy = $this->get_json($this->v_map_data,$vatspy_status);
				if ($copy = $this->curl_copy($vatspy['fir_boundaries_geojson_url'],'Boundaries.geojson')) {

				}
				break;
		}
		if (isset($copy) && !$copy) echo "Error while copying file '".$datatype."' to local server.";
		if (isset($write) && !$write) echo "Error while writing bin file '".$datatype."'.";
	}

	private function curl_resp($orig, $httpheader = []) {
		$curl = curl_init($orig);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		if (!empty($httpheader)) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);
		}
		$resp = curl_exec($curl);

		if ($resp === false) {
			throw new RuntimeException('cURL error: ' . curl_error($ch));
		}

		if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
			throw new RuntimeException('HTTP error: ' . $httpCode);
		}
		return $resp;
	}

	private function curl_copy($orig, $file = '') {
		$dest = __DIR__.$this->f_orig.$file;
		if (!($fp = fopen($dest,'w'))) return false;
		if (!($curl = curl_init($orig))) return false;
		if (!curl_setopt($curl, CURLOPT_FILE, $fp)) return false;
		if (!curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true)) return false;
		if (!curl_setopt($curl, CURLOPT_TIMEOUT, 10)) return false;

		if (!curl_exec($curl)) return false;
		curl_close($curl);

		if (!fclose($fp)) return false;
		return $dest;
	}
	
	private function get_json($orig, $file = '', $folder = '') {
		$dest = __DIR__.($folder == '' ? $this->f_orig : $folder).($file == '' ? (basename($origin) == '' ? 'result.json' : basename($origin)) : $file);
		if (!file_exists($dest)) {
			$copy = $this->curl_copy($orig,$file);
		}
		$json = json_decode(file_get_contents($dest), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
		}
		return $json;
	}
	
	private function write_cache_file($data,$file) {
		//if ($this->is_debug) echo var_dump($data);
		if (!file_put_contents(__DIR__.$this->f_bin.$file, serialize($data))) return false;
		return true;
	}
	
	private function get_cache_file($file) {
		if ($cache = unserialize(file_get_contents(__DIR__.$this->f_bin.$file))) return $cache;
		return false;
	}

	function get_vdata() {
		$this->get_filetype('vatsim-data');
		return $this->get_cache_file('vdata.bin');
	}
}

if (array_key_exists('r',$_GET)) {
	$file = new VATSIM(array_key_exists('debug',$_GET));
	$file->get_filetype($_GET['r']);
}


?>