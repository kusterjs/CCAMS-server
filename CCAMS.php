<?php

class CCAMS {

	private $timer;
	private $is_debug;
	private $is_valid;
	private $client_ipaddress;
	private $root;
	private $f_bin;
	public $f_log;
	private $file_log;
	public $logfile_prefix;
	private $logtext_prefix;
	
	private $users;
	private $usedcodes;	// the codes reserved by the api
	private $squawkranges;
	private $srange_keys;
	
	private $squawk;	// the codes available for use

	function __construct($f_bin, $debug = false)
	{
		date_default_timezone_set("UTC");
		include_once('../cron/vatsim.php');
		
		$this->timer = microtime(true);
		$this->is_valid = false;
		$this->root = __DIR__;
		$this->f_bin = $f_bin;
		$this->f_log = '/log/';
		$this->logfile_prefix = 'log_';
		$this->file_log = $this->f_log.$this->logfile_prefix.date('Y-m-d').'.txt';

		
		if ($debug) {
			$this->is_debug = true;
			error_reporting(E_ALL);
			//echo 'running CCAMS class<br />';
			echo realpath(__FILE__).'<br />';
		} else {
			$this->is_debug = false;
			error_reporting(0);
		}
	}
	
	function __destruct() {
		//if ($this->is_debug) echo 'End of CCAMS class<br />';
	}
	
	function is_valid() {
		return $this->is_valid;
	}
	
	function set_sqwk_range($key,$text) {
		if ($text=='') {
			$codes = array();			
		} else {
			if (!preg_match_all('/([A-Z]{3,}):([\d]{4})(?::([\d]{4}))?(?::([A-Z]{2,4}|\*))?/i',$text,$m)) return false;

			foreach ($m[0] as $k0 => $m0) {
				$orig = strtolower($m[1][$k0]);
				if (strlen($m[4][$k0])<2) $dest = 'zzzz';
				else $dest = strtolower($m[4][$k0]);

				$codes[$orig][$dest][] = octdec($m[2][$k0]);
				if (!empty($m[3][$k0])) {
					$codes[$orig][$dest][] = octdec($m[3][$k0]);
				} else {
					$codes[$orig][$dest][] = octdec($m[2][$k0]);
				}
			}
		}
		$this->write_cache_file('/cache/ranges.bin',$codes,$key);
	}
	
	function get_squawk_range($key) {
		// depreciated
		$text = '';
		if ($codes = $this->read_cache_file('/cache/ranges.bin',$key)) {
			foreach ($codes as $orig => $group) {
				foreach ($group as $dest => $range) {
					$text .= "\n".strtoupper($orig).':'.sprintf("%04o",$range[0]);
					if ($range[1]!=$range[0]) $text .= ':'.sprintf("%04o",$range[1]);
					if ($dest!='zzzz') $text .= ':'.strtoupper($dest);
				}
			}
		}
		return $text;
	}
	
	function get_sqwk_ranges() {
		$json = array();
		if ($codes = $this->read_cache_file('/cache/ranges.bin')) {
			foreach ($codes as $table => $category) {
				$txt = '';
				foreach ($category as $orig => $group) {
					foreach ($group as $dest => $range) {
						$txt .= "\n".strtoupper($orig).':'.sprintf("%04o",$range[0]);
						if ($range[1]!=$range[0]) $txt .= ':'.sprintf("%04o",$range[1]);
						if ($dest!='zzzz') $txt .= ':'.strtoupper($dest);
					}
				}
				$json[$table] = trim($txt);
			}
		}
		return json_encode($json);
	}

	function get_reserved_codes() {
		$json = array();
		if ($codes = $this->read_cache_file('/cache/squawks.bin')) {
			ksort($codes);
			$txt = '';
			foreach ($codes as $squawk => $time) {
				$txt .= "\n".sprintf("%04o",$squawk)."\t".date('Y-m-d H:i:s',$time);
			}
		}
		return json_encode(array(trim($txt)));
	}

	function request_code() {
		if (!$this->is_valid) return;
		if (!array_key_exists('callsign',$_GET)) return;
		
		// generate an array with all possible squawk codes
		$squawk = array_fill(0,4095,'');

		// remove all non-discrete codes from possible results
		foreach ($squawk as $code => $code) {
			if ($code%64==0) unset($squawk[$code]);
		}
		// removed codes already in use
		foreach ($this->codes_used() as $code) unset($squawk[$code]);
		
		$this->squawk = $squawk;
		// the squawk variable contains now only the codes that can be assigned
		
		//echo var_dump($squawk);
		$this->squawkranges = $this->read_cache_file('/cache/ranges.bin');
		if (!($ssr = $this->squawk_from_range())) {
			// if there is no key found, start preparing $squawk for assigning a random code (removing all known FIR ranges from it)
			if (array_key_exists('FIR',$this->squawkranges)) {
				foreach ($this->squawkranges['FIR'] as $rangename) {
					for ($code = $rangename['zzzz'][0];$code<=$rangename['zzzz'][1];$code++) {
						unset($squawk[$code]);
					}
				}
			}
			// remove specific ranges of codes from possible results (0001 to 0077, as they are usually reserved for VFR)
			foreach ($squawk as $code => $code) {
				if ($code<64) unset($squawk[$code]);
			}

			if ($this->is_debug) echo 'selecting a random squawk<br />';
			$ssr = array_rand($squawk);
		}
		if (!$this->reserve_code(sprintf("%04o",$ssr),2*3600)) return;
		
		// write cache files
		$this->write_cache_file('/cache/squawks.bin',$this->usedcodes);
		
		
		// create output
		$resp = sprintf("%04o",$ssr);
		$this->write_log("code assigned;".$resp);
		
		if ($this->is_debug) $resp .= '<br />';
		return $resp;
	}
	
	function checks() {
		//return http_response_code(401);
		if (!$this->check_connection()) return http_response_code(406);
		if (!$this->check_user_agent()) return http_response_code(401);
		if (!$this->check_user_request()) return http_response_code(429);
		if (!$this->usedcodes = $this->check_reserved_codes()) $this->usedcodes = array();
		
		$this->is_valid = true;
	}
	
	function check_reserved_codes() {
		if (($codes = $this->read_cache_file('/cache/squawks.bin'))!==false) {
			foreach ($codes as $code => $time) {
				if ($time <= time()) unset($codes[$code]);
			}
			return $codes;
		}
		return false;
	}
	
	function clean_squawk_cache() {
		if (($codes = $this->check_reserved_codes())!==false) $this->write_cache_file('/cache/squawks.bin',$codes);
	}
	
	function get_logs() {
		if (($logfiles = glob(__DIR__.$this->f_log.$this->logfile_prefix.'*'))!==false) {
			rsort($logfiles);
			foreach ($logfiles as $file) {
				$date = new DateTimeImmutable(str_replace($this->logfile_prefix,'',pathinfo($file, PATHINFO_FILENAME)));
				if (!$this->is_debug && $date->diff(new DateTime('now'))->days > 64) continue;
				$logs['day'][$date->format('Y-m-d')] = '';
				$logs['week'][$date->format('W')] = '';
				$logs['month'][$date->format('F Y')] = '';
			}
			foreach ($logs as $key => $value) {
				$resp[$key] = array_keys($value);
			}
			//array_unique($resp, SORT_STRING);
			return json_encode($resp);
		}
		return false;
	}
	
	private function check_connection() {
		// check IP address
		if (isset($_SERVER['HTTP_CLIENT_IP']))
			$client_ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$client_ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if(isset($_SERVER['HTTP_X_FORWARDED']))
			$client_ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
			$client_ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		else if(isset($_SERVER['HTTP_FORWARDED']))
			$client_ipaddress = $_SERVER['HTTP_FORWARDED'];
		else if(isset($_SERVER['REMOTE_ADDR']))
			$client_ipaddress = $_SERVER['REMOTE_ADDR'];
		else
			$client_ipaddress = 'UNKNOWN';

		$this->logtext_prefix = date("c").";".$client_ipaddress.";".$_SERVER['HTTP_USER_AGENT'].";$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI];".$this->is_debug.";".$_GET['callsign'].";";
		$this->client_ipaddress = intval(gmp_import(inet_pton($client_ipaddress)));
		
		if (inet_pton($client_ipaddress)) return true;
		$this->write_log("IP address detection issue;HTTP_CLIENT_IP:$_SERVER[HTTP_CLIENT_IP],HTTP_X_FORWARDED_FOR:$_SERVER[HTTP_X_FORWARDED_FOR],HTTP_X_FORWARDED:$_SERVER[HTTP_X_FORWARDED],HTTP_FORWARDED_FOR:$_SERVER[HTTP_FORWARDED_FOR],HTTP_FORWARDED:$_SERVER[HTTP_FORWARDED],REMOTE_ADDR:$_SERVER[REMOTE_ADDR]");
	}
	
	private function check_user_agent() {
		if (preg_match('/EuroScope CCAMS\/(1\.7|2\.0)\.\d/',$_SERVER['HTTP_USER_AGENT']) || $this->is_debug) return true;
		$this->write_log("user agent not authorised");
		return false;
	}
	
	private function check_user_request() {
		if ($this->users = $this->read_cache_file('/cache/users.bin')) {
			$this->users[$this->client_ipaddress][0] -= pow(2,floor((time()-$this->users[$this->client_ipaddress][1])/60))-1;	// reduce count depending on the time of the last request
			if ($this->users[$this->client_ipaddress][0] <= 0) unset($this->users[$this->client_ipaddress]);
			elseif ($this->users[$this->client_ipaddress][0] > 15) {
				$this->write_log("spam protection;too many requests from specific IP");
				//exit('Too many requests. Your next code is available in '.(60-(time()-$this->users[$this->client_ipaddress][1])).' seconds.<br />');
				return false;
			}
		} else {
			$this->users = array();
		}
		
		// increase count of user
		if (isset($this->users[$this->client_ipaddress])) $this->users[$this->client_ipaddress][0] += 1;
		else $this->users[$this->client_ipaddress][0] = 1;
		$this->users[$this->client_ipaddress][1] = time();
		return $this->write_cache_file('/cache/users.bin',$this->users);
	}
	
	private function codes_used() {
		$codes = array();
		// check squawks used according vatsim-data file and exclude these codes from possible results
		$vatsim = new VATSIM($this->is_debug);
		$vdata = $vatsim->get_vdata();
		foreach ($vdata['pilots'] as $pilot) {
			if ($pilot['transponder']==decoct(octdec($pilot['transponder']))) $codes[] = octdec($pilot['transponder']);
		}		

		// exclude all codes already known to be assigned by the controller asking for a code
		if (array_key_exists('codes',$_GET)) {
			foreach (explode(',',$_GET['codes']) as $code) {
				if ($this->reserve_code($code, 1800)) $codes[] = octdec($code);
			}
		}
	
		// exclude all cached codes from possible results
		foreach ($this->usedcodes as $code => $time) {
			if ($time > time()) $codes[] = $code;
		}
		
		return array_unique($codes);
	}
	
	private function reserve_code($code,$seconds) {
		// code in octal format (as displayed for use, numbers 0-7 only)
		if (octdec($code)%64==0) return true;
		if (decoct(octdec($code))!=$code) return false;
		
		if ($this->is_debug) $expiryTime = time() + 360;
		else $expiryTime = time() + $seconds;
		
		if (array_key_exists(octdec($code),$this->usedcodes)) {
			if ($this->usedcodes[octdec($code)] > $expiryTime) return false;
		}
		$this->usedcodes[octdec($code)] = $expiryTime;
		return true;
	}
	
	private function squawk_from_range() {
		if (!$this->squawkranges) return false;
		$this->srange_keys = array_keys($this->squawkranges);	// currently not used

		$vatspy = $this->read_cache_file($this->f_bin.'vatspy.bin');
		$callsign = strtolower(preg_replace('/^([A-Za-z\-]+)_.+/m','$1',$_GET['callsign']));
		if ($orig = array_key_exists('orig',$_GET)) $orig = strtolower($_GET['orig']);
		
		$conditions = array();
		if (array_key_exists('flightrule',$_GET)) if ($_GET['flightrule']=='V') $conditions[] = 'vfr';
		if (array_key_exists('flightrules',$_GET)) if ($_GET['flightrules']=='V') $conditions[] = 'vfr';
		if ($dest = array_key_exists('dest',$_GET)) {
			$dest = strtolower($_GET['dest']);
			for ($len=strlen($dest);$len>1;$len--) {
				$conditions[] = substr($dest,0,$len);
			}
		}
		
		// collecting search key words for the search in the APT table
		$search = array();
		if ($orig) $search[] = $orig;
		$search[] = $callsign;
		if ($vatspy) if ($iata = array_search($callsign,$vatspy['IATA'])) $search[] = $vatspy['ICAO'][$iata];
		$searches['APT'] = array_unique($search);
		
		// collecting search key words for the search in the FIR table
		$search = array();
		if ($vatspy && $orig) if ($icao = array_search($orig,$vatspy['ICAO'])) $search[] = $vatspy['FIR'][$icao];
		$search[] = $callsign;
		if ($vatspy) {
			if ($iata = array_search($callsign,$vatspy['IATA'])) $search[] = $vatspy['FIR'][$iata];
			if ($icao = array_search($callsign,$vatspy['ICAO'])) $search[] = $vatspy['FIR'][$icao];
		}
		for ($len = strlen($callsign)-1; $len>1; $len--) $search[] = substr($callsign,0,$len);
		$searches['FIR'] = array_unique($search);		


		if ($code = $this->get_range_code($searches,$conditions)) return $code;
		return false;
	}
	
	
/*	private function find_squawk($needle, $condition = 'zzzz') {

	}
*/	
	private function get_range_code(array $searches, array $conditions) {
		// $rangekey: th
		//if (!array_key_exists($rangekey,$this->squawkranges)) return false;	// check the that the required range table is available
		
		// completition of the $conditions array
		$conditions[] = 'zzzz';
		$conditions = array_unique($conditions);
		
		/*	
		searching for a code with the following logic
			1. going through all the range table names in $searches
			2. going through all the search terms for that specific table
			3. look for a range name starting with the search phrase
			4. within that entry, going through all the $conditions entries and looking for a match (the default entry 'zzzz' is therefore added in the begin of this function)
			5. checking all available codes of a found range if they are available for assignment, and if so return that code
		*/		
		foreach ($searches as $tablekey => $search) {
			if (!array_key_exists($tablekey,$this->squawkranges)) continue;
			foreach ($search as $needle) {
				foreach ($conditions as $condition) {
					if ($this->is_debug) echo 'scanning range in '.$tablekey.' table for match with '.$needle.', condition is '.$condition.'<br />';
					foreach (array_keys($this->squawkranges[$tablekey]) as $rangename) {
						if (substr($rangename,0,strlen($needle))==$needle) {
							if (array_key_exists($condition,$this->squawkranges[$tablekey][$rangename])) {
								for ($code = $this->squawkranges[$tablekey][$rangename][$condition][0];$code<=$this->squawkranges[$tablekey][$rangename][$condition][1];$code++) {
									if ($this->is_debug) echo 'probing code '.$code.'<br />';
									if (array_key_exists($code,$this->squawk)) return $code;
								}
							}
						}
					}
				}
			}
		}
		return false;
	}
	
/*	private function get_range_code_old($needle, $rangekey, $condition = 'zzzz') {
		if ($this->is_debug) echo 'scanning range in '.$rangekey.' table for match with '.$needle.', condition is '.$condition.'<br />';
		if (array_key_exists($rangekey,$this->squawkranges)) {
			if (array_key_exists($needle,$this->squawkranges[$rangekey])) {
				if (array_key_exists($condition,$this->squawkranges[$rangekey][$needle])) {
					for ($code = $this->squawkranges[$rangekey][$needle][$condition][0];$code<=$this->squawkranges[$rangekey][$needle][$condition][1];$code++) {
						if ($this->is_debug) echo 'testing code '.$code.'<br />';
						if (array_key_exists($code,$this->squawk)) return $code;
					}
				}
			}
		}
		return false;
	}
*/	
	private function read_cache_file($file, $key = '') {
		if (($data = unserialize(file_get_contents($this->root.$file)))!==false) {
			if (!empty($key) && array_key_exists($key,$data)) return $data[$key];
			return $data;
		}
		$this->write_log("file reading error;cache ".$key);
		return false;
	}
	
	private function write_cache_file($file,$data, $key = '') {
		if (empty($key)) {
			if (file_put_contents($this->root.$file, serialize($data))) return true;
		} else if (($d = $this->read_cache_file($file))!==false) {
			$d[$key] = $data;
			if (file_put_contents($this->root.$file, serialize($d))) return true;
		} else {
			if (file_put_contents($this->root.$file, serialize(array()))) return true;
		}
		$this->write_log("file writing error;cache ".$key);
		return false;		
	}
	
	private function write_log($text) {
		file_put_contents(__DIR__.$this->file_log,$this->logtext_prefix.sprintf("%.6f",microtime(true)-$this->timer).";".$text."\n",FILE_APPEND);
	}
}

class CCAMSstats {
	
	private $is_debug;
	private $f_log;
	private $logfile_prefix;
	private $logdata;
	private $stats;
	
	
	function __construct($debug = false) {
		date_default_timezone_set("UTC");
		$this->f_log = '/log/';
		$this->logfile_prefix = 'log_';
		$this->logdata = array();
		
		if ($debug) {
			$this->is_debug = true;
			error_reporting(E_ALL);
			//echo 'running CCAMS class<br />';
			echo realpath(__FILE__).'<br />';
		} else {
			$this->is_debug = false;
			error_reporting(0);
		}
	}
	
	function readStats($date) {
		if (!$date instanceof DateTime) return false;
		if (($logdata = file(__DIR__.$this->f_log.$this->logfile_prefix.$date->format('Y-m-d').'.txt'))===false) return false;
		foreach ($logdata as $line) {
			$data = explode(";",$line);
			if (count($data)==9) $this->logdata[] = $data;
			//echo var_dump($data).'<br />';
		}
		//echo var_dump($this->logdata);
	}

	function createStats() {
		$facilities = array('DEL', 'GND', 'TWR', 'APP', 'DEP', 'CTR', 'FSS');
		$stats['facility'] = array_combine($facilities, array_fill(0, count($facilities), 0));
		$stats['hour'] = array_fill(0,24,0);
		foreach ($this->logdata as $log) {
			if ($log[7]=='code assigned') {
				
			}
			$date = new DateTimeImmutable($log[0]);
			//echo var_dump($date->format('Y-m-d'));
			$stats['date'][$date->format('Y-m-d')] += 1;
			$stats['year'][$date->format('Y')] += 1;
			$stats['month'][$date->format('n')] += 1;
			$stats['week'][$date->format('W')] += 1;
			$stats['day'][$date->format('j')] += 1;
			//echo var_dump($stats);
			$stats['hour'][$date->format('G')] += 1;
			$stats['callsign'][$log[5]] += 1;
			if (preg_match('/^([A-Z]+)_/',$log[5],$m)) $stats['designator'][$m[1]] += 1;
			if (preg_match('/_(DEL|GND|TWR|APP|DEP|CTR|FSS)$/',$log[5],$m)) $stats['facility'][$m[1]] += 1;
			if (preg_match('/CCAMS\/([\d\w\.]+)/',$log[2],$m)) $stats['version'][$m[1]] += 1;
			if (preg_match('/orig=([A-Z]{4})/',$log[3],$m)) $stats['origin'][$m[1]] += 1;
			if (preg_match('/flightrule(?:s)?=([A-Z])/',$log[3],$m)) $stats['flightrule'][$m[1]] += 1;
		}
		ksort($stats['designator']);
		if (array_key_exists('version', $stats)) ksort($stats['version']);
		return json_encode($stats);
		
		//echo var_dump($stats);
	}
}
	

?>