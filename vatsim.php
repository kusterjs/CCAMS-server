<?php

class VATSIM {
	
	private $f_orig;
	private $f_bin;
	private $is_debug;
	public $vdata_upd;
	
	function __construct($debug = false) {
		$this->f_orig = '/data/';
		$this->f_bin = '/bin/';
		
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
		$v_status = 'status.txt';
		switch ($datatype) {
			case 'vatsim-status':
				$copy = $this->curl_copy('https://status.vatsim.net/status.txt',$v_status);
				break;
			case 'vatsim-data':
				// check if file exists and is already current
				if ($data = $this->get_cache_file('vdata.bin')) {
					if (strtotime($data['general']['update_timestamp'].' +1 minute') > time()) {
						break;
					}
				}

				if (!($filecontent = file_get_contents(__DIR__.$this->f_orig.$v_status))) {
					$f = new VATSIM();
					$f->get_filetype('vatsim-status');
				}

				// get url from status file
				if ($filecontent) {
					if (preg_match_all('/(?<type>json)(?<version>[3])[=](?<url>(?:http[s]?:)[\/]{2}.*?(?<name>[^.\/]+[.][^.\/]+)[\/](?:.*?[\/])?(?<file>[\S]+))/m',$filecontent,$matches,PREG_SET_ORDER)) {
						foreach ($matches as $m) {
							if ($copy = $this->curl_copy($m['url'],$datatype.'.'.$m['type'])) {
								$write = $this->write_cache_file(json_decode(file_get_contents($copy),true),'vdata.bin');
								$this->vdata_upd = true;
								break;
							}
						}
					}
				}
				break;
			case 'vatspy-data':
				if ($copy = $this->curl_copy('https://raw.githubusercontent.com/vatsimnetwork/vatspy-data-project/master/VATSpy.dat','VATSpy.dat')) {
					$airports = array();
					$pseudoID = array();

					foreach (file(__DIR__.$this->f_orig.'VATSpy.dat') as $apt) {
						if (preg_match('/^([A-Z]{4})(?:\|([^\|]*)){4}\|([^\1]{4})\2?/i',$apt,$m)) {
							$vatspy['FIR'][] = strtolower($m[3]);
							$vatspy['ICAO'][] = strtolower($m[1]);
							$airports[strtolower($m[1])] = strtolower($m[3]);
							if (!empty($m[2]) && $m[2]!=$m[3]) $vatspy['IATA'][] = strtolower($m[2]);
							else $vatspy['IATA'][] = '';
						}
					}
					$write = $this->write_cache_file($vatspy,'vatspy.bin');
				}
				break;
		}
		if (isset($copy) && !$copy) echo "Error while copying file '".$datatype."' to local server.";
		if (isset($write) && !$write) echo "Error while writing bin file '".$datatype."'.";
	}

	private function curl_copy($orig, $file) {
		$dest = __DIR__.$this->f_orig.$file;
		if (!($fp = fopen($dest,'w'))) return false;
		if (!($curl = curl_init($orig))) return false;
		if (!curl_setopt($curl, CURLOPT_FILE, $fp)) return false;

		if (!curl_exec($curl)) return false;
		curl_close($curl);

		if (!fclose($fp)) return false;
		return $dest;
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