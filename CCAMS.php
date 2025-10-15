<?php

class CCAMS {

	private $timer;
	private $is_debug;
	private $is_valid;
	private $networkmode;
	private $client_ipaddress;
	private $root;
	private $f_config;
	private $f_bin;
	private $f_debug;
	public $f_log;
	private $file_log;
	public $logfile_prefix;
	private $logtext_prefix;
	private $file_lock;
	public $lock;

	private $users;
	private $usedcodes;	// the codes reserved by the api
	private $squawkranges;
	private $squawkgroups;

	private $squawk;	// the codes available for use

	function __construct($debug = false)
	{
		date_default_timezone_set("UTC");
		include_once('vatsim.php');

		$this->timer = microtime(true);
		$this->is_valid = false;
		$this->networkmode = false;
		$this->root = __DIR__;
		$this->f_config = '/config/';
		$this->f_bin = '/bin/';
		$this->f_log = '/log/';
		$this->f_debug = '/debug/';
		$this->logfile_prefix = 'log_';
		$this->file_log = $this->f_log.$this->logfile_prefix.date('Y-m-d').'.txt';

		if ($debug) {
			$this->is_debug = true;
			error_reporting(E_ALL);
			//echo 'running CCAMS class<br>';
			echo realpath(__FILE__).'<br>';
		} else {
			$this->is_debug = false;
			error_reporting(0);
		}

		$this->file_lock = '/request.lock';
		$this->lock = fopen($this->root.$this->file_lock, 'c'); // 'c' means create if not exists

		if (!$this->lock) {
			http_response_code(500);
			$this->write_log("file reading error;lock file '$this->file_lock'");
			exit;
		}		
	}

	function __destruct() {
		flock($this->lock, LOCK_UN);
		//if ($this->is_debug) echo 'End of CCAMS class<br>';
	}

	function is_valid() {
		return $this->is_valid;
	}

	function authenticate() {
		//return http_response_code(401);
		if (!$this->check_connection()) return http_response_code(406);
		if (!$this->check_user_agent()) return http_response_code(401);
		if (!$this->check_user_callsign()) {
			echo '0000';
			return;
		}

		if (flock($this->lock, LOCK_EX)) {
			$req = $this->check_user_request();
			flock($this->lock, LOCK_UN);
			if (!$req) return http_response_code(429);
		}

		$this->is_valid = true;
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

		$this->logtext_prefix = date("c").";".$client_ipaddress.";".$_SERVER['HTTP_USER_AGENT'].";$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI];".$this->is_debug.";".filter_input(INPUT_GET,'callsign').";";
		$this->client_ipaddress = intval(gmp_import(inet_pton($client_ipaddress)));

		if (inet_pton($client_ipaddress)) return true;
		$this->write_log("IP address detection issue;HTTP_CLIENT_IP:$_SERVER[HTTP_CLIENT_IP],HTTP_X_FORWARDED_FOR:$_SERVER[HTTP_X_FORWARDED_FOR],HTTP_X_FORWARDED:$_SERVER[HTTP_X_FORWARDED],HTTP_FORWARDED_FOR:$_SERVER[HTTP_FORWARDED_FOR],HTTP_FORWARDED:$_SERVER[HTTP_FORWARDED],REMOTE_ADDR:$_SERVER[REMOTE_ADDR]");
	}

	private function check_user_agent() {
		if ($this->is_debug) return true;
		else if (preg_match('/neoradar\/([\.\d]+)/',$_SERVER['HTTP_USER_AGENT'])) return true;

		// activate network mode (instead of local mode (for sweatbox, simulator))
		if (!(array_key_exists('sim',$_GET) || (array_key_exists('connectiontype',$_GET) && !(filter_input(INPUT_GET,'connectiontype')=='1' || filter_input(INPUT_GET,'connectiontype')=='2')))) {
			$this->networkmode = true;
			if ($this->is_debug) echo 'network mode enabled (sim '.(array_key_exists('sim',$_GET) ? 'true' : 'false').', connectiontype '.(array_key_exists('connectiontype',$_GET) ? filter_input(INPUT_GET,'connectiontype') : 'false').')<br>';
		}

		if (preg_match('/EuroScope\s(\d+\.){3}\d+\splug-in:\sCCAMS\/2\.[5-9]\.\d/',$_SERVER['HTTP_USER_AGENT'])) return true;
		if (preg_match('/CCAMS Server V1/',$_SERVER['HTTP_USER_AGENT'])) return true;
		$this->write_log("user agent not authorised;".$_SERVER['HTTP_USER_AGENT']);
		return false;
	}

	private function check_user_callsign() {
		if (!array_key_exists('callsign',$_GET)) return false;
		if (preg_match('/_(DEL|GND|TWR|APP|DEP|CTR|FSS)$/',filter_input(INPUT_GET,'callsign'))) return true;
		//mail('ccams@kilojuliett.ch','CCAMS unauthorised callsign use detected',$this->logtext_prefix);
		$this->write_log("user callsign not authorised;".filter_input(INPUT_GET,'callsign'));
		return false;
	}

	private function check_user_request() {
		if (!$this->users = $this->read_bin_file('users.bin')) {
			// create an empty array if the bin file couldn't been read
			$this->users = [];
		}

		// create compare hash
		$hash = filter_input(INPUT_GET,'callsign');
		if (preg_match('/EuroScope\s(\d+\.){3}\d+\splug-in:\sCCAMS\/2\.(5\.2|[6-9]\.\d)/',$_SERVER['HTTP_USER_AGENT'])) {
			if (array_key_exists('orig',$_GET)) $hash .= filter_input(INPUT_GET,'orig');
			if (array_key_exists('dest',$_GET)) $hash .= filter_input(INPUT_GET,'dest');
			if (array_key_exists('latitude',$_GET)) $hash .= filter_input(INPUT_GET,'latitude');
			if (array_key_exists('longitude',$_GET)) $hash .= filter_input(INPUT_GET,'longitude');
		}

		if (isset($this->users[$this->client_ipaddress])) {
			// check details of IP address which has already an entry

			if (time()-$this->users[$this->client_ipaddress][1] < 2 && $this->users[$this->client_ipaddress][2]==md5($hash)) {
				$this->write_log("spam protection;multiple joint requests detected");
				return false;
			}

			$this->users[$this->client_ipaddress][0] -= pow(2,floor((time()-$this->users[$this->client_ipaddress][1])/60))-1;	// reduce count depending on the time of the last request
			if ($this->users[$this->client_ipaddress][0] <= 0) $this->users[$this->client_ipaddress][0] = 0;
			elseif ($this->users[$this->client_ipaddress][0] > 15) {
				$this->write_log("spam protection;too many requests from specific IP");
				//exit('Too many requests. Your next code is available in '.(60-(time()-$this->users[$this->client_ipaddress][1])).' seconds.<br>');
				return false;
			}

			// increase IP address count
			$this->users[$this->client_ipaddress][0] += 1;
		} else {
			// set initial count for a new IP address
			$this->users[$this->client_ipaddress][0] = 1;
		}

		// update timestamp
		$this->users[$this->client_ipaddress][1] = time();

		// update compare hash
		$this->users[$this->client_ipaddress][2] = md5($hash);

		// write cache file
		return $this->write_bin_file('users.bin',$this->users);
	}

	function request_code() {
		if (!$this->is_valid) return;
		if ($this->is_debug) file_put_contents($this->root.$this->f_debug.'log.txt',date("c").' '.__FILE__." starting request_code\n",FILE_APPEND);

		// generate an array with all possible squawk codes
		$squawk = array_fill(0,4095,'');

		// remove all non-discrete codes from possible results
		foreach ($squawk as $code => $val) {
			if ($code%64==0) unset($squawk[$code]);
		}

		// remove group codes
		$this->squawkgroups = $this->read_bin_file('groups.bin');
		foreach ($this->squawkgroups as $callsign_match => $group) {
			foreach ($group as $group_code => $polygons) {
				unset($squawk[$group_code]);
			}
		}

		// removed codes already in use
		if (!$this->networkmode || flock($this->lock, LOCK_EX)) {
			if (array_key_exists('flightrule',$_GET)) {
				$flightrule = filter_input(INPUT_GET,'flightrule');
			} else if (array_key_exists('flightrules',$_GET)) {
				$flightrule = filter_input(INPUT_GET,'flightrules');
			} else {
				$flightrule = 'I';
			}

			foreach ($this->codes_used() as $code) unset($squawk[$code]);

			$this->squawk = $squawk;
			// the squawk variable contains now only the codes that can be assigned

			//echo var_dump($squawk);
			$this->squawkranges = $this->read_bin_file('ranges.bin');
			if (!($ssr = $this->squawk_from_rules($flightrule))) {
				// if there is no key found, start preparing $squawk for assigning a random code
				// remove specific ranges of codes from possible results (0001 to 0077, as they are usually reserved for VFR)
				if ($flightrule == 'I') {
					foreach ($squawk as $code => $code) {
						if ($code<64) unset($squawk[$code]);
					}
				}
				$this->squawk = $squawk;
				// revised table of assignable codes

				// removing all known FIR ranges from it
				if (array_key_exists('FIR',$this->squawkranges)) {
					foreach ($this->squawkranges['FIR'] as $rangename) {
						foreach ($rangename as $rangecondition) {
							foreach ($rangecondition as $range) {
								for ($code = $range[0];$code<=$range[1];$code++) {
									unset($squawk[$code]);
								}
							}
						}
					}
				}

				if (count($squawk) == 0) {
					// if no codes are left, select a random code but disregarding all known FIR ranges
					$squawk = $this->squawk;
				}
				if ($this->is_debug) echo 'selecting a random squawk<br>';
				$ssr = array_rand($squawk);
			}
			$resp = sprintf("%04o",$ssr);

			if ($this->networkmode) {
				// reserve code
				$this->reserve_code($resp,2*3600);

				// write cache files
				$this->write_bin_file('squawks.bin',$this->usedcodes);
			}
			flock($this->lock, LOCK_UN);
		}

		// create output
		$this->write_log("code assigned;".$resp);

		if ($this->is_debug) $resp .= '<br>';
		return $resp;
	}

	private function codes_used() {
		$codes = [];
		$this->usedcodes = [];
		// check squawks used according vatsim-data file and exclude these codes from possible results
		if ($this->networkmode || $this->is_debug) {
			$vatsim = new VATSIM($this->is_debug);
			$vdata = $vatsim->get_vdata();
			if ($this->networkmode || $vatsim->vdata_upd) {
				foreach ($vdata['pilots'] as $pilot) {
					if ($pilot['transponder']==decoct(octdec($pilot['transponder'])) && octdec($pilot['transponder'])%64!=0) $codes[] = octdec($pilot['transponder']);
					if ($this->is_debug) file_put_contents($this->root.$this->f_debug.'log.txt',date("c").' '.__FILE__." invalid code in vatspy file detected (".$pilot['callsign'].", ".$pilot['transponder'].")\n",FILE_APPEND);
				}
				$codes = array_unique($codes);
				sort($codes);
			}
			if (!empty($codes) && $vatsim->vdata_upd) $this->write_log("vdata updated extracted transponder codes;".implode(',',array_map(function($num) { return sprintf("%04d", decoct($num)); }, $codes)));
		}

		if (!$this->networkmode) {
			// collect already reserved codes
			//if (!$this->usedcodes = $this->check_reserved_codes()) $this->usedcodes = [];
			$this->usedcodes = $this->check_reserved_codes();

			// exclude all cached codes from possible results
			foreach ($this->usedcodes as $code => $time) {
				if ($time > time()) $codes[] = $code;
			}
		}

		// exclude all codes already known to be assigned by the controller asking for a code
		if (array_key_exists('codes',$_GET)) {
			foreach (preg_split('/[,~]+/', urldecode(filter_input(INPUT_GET,'codes')), -1, PREG_SPLIT_NO_EMPTY) as $code) {
				if ($this->reserve_code($code, 1800)) $codes[] = octdec($code);
			}
		}
		return array_unique($codes);
	}

	function check_reserved_codes() {
		if (($codes = $this->read_bin_file('squawks.bin'))!==false) {
			foreach ($codes as $code => $time) {
				if ($time <= time()) unset($codes[$code]);
			}
			return $codes;
		}
		return [];
	}

	function clean_squawk_cache() {
		//if (($codes = $this->check_reserved_codes())!==false)
		$this->write_bin_file('squawks.bin',$this->check_reserved_codes());
	}

	function clean_user_cache() {
		if (($users = $this->read_bin_file('users.bin'))!==false) {
			foreach ($users as $ip => $user) {
				if (time()-$user[1] > 600) {
					unset($users[$ip]);
				}
			}
			$this->write_bin_file('users.bin',$users);
		}
	}

	private function reserve_code($code,$seconds) {
		// code in octal format (as displayed for use, numbers 0-7 only)
		if (octdec($code)%64==0) return false;	// disregard non-discrete codes (they don't need to return true as they will be removed from the possible range anyway)
		if (decoct(octdec($code))!=$code) return false;	// non-valid octal code
		//if (!$this->networkmode) return true;	// code reservation not required in local mode

		if ($this->is_debug) $expiryTime = time() + 360;
		else $expiryTime = time() + $seconds;

		if (array_key_exists(octdec($code),$this->usedcodes)) {
			if ($this->usedcodes[octdec($code)] > $expiryTime) return false;
		}
		$this->usedcodes[octdec($code)] = $expiryTime;
		if ($this->is_debug) echo 'reserving code '.octdec($code).'<br>';
		return true;
	}

	private function squawk_from_rules($flightrule) {
		if (!$this->squawkranges) return false;

		$vatspy = $this->read_bin_file('vatspy.bin');
		if (!preg_match('/^(([a-z\-]+)(_[a-z0-9]+)?)_+[a-z]+$/i',strtolower(filter_input(INPUT_GET,'callsign')),$callsign)) return false;
		if ($orig = array_key_exists('orig',$_GET)) $orig = strtolower(filter_input(INPUT_GET,'orig'));
		if ($dest = array_key_exists('dest',$_GET)) $dest = strtolower(filter_input(INPUT_GET,'dest'));

		// collect conditions
		$conditions = [];
		if ($flightrule == 'V') {
			$conditions['FR'] = 'vfr';
			if ($orig) $conditions['ADEP'] = $orig;
			if ($dest) $conditions['ADES'] = $dest;
		} else {
			if ($dest) {
				for ($len=strlen($dest);$len>0;$len--) {
					$conditions[] = substr($dest,0,$len);
				}
				if ($vatspy) {
					if ($iata = array_search($dest,$vatspy['IATA'])) $conditions[] = $vatspy['FIR'][$iata];
					if ($icao = array_search($dest,$vatspy['ICAO'])) $conditions[] = $vatspy['FIR'][$icao];
				}
			}
			$conditions[] = 'zzzz';
		}

		// collecting search key words for the search in the APT table
		$search = [];
		if ($orig) $search[] = $orig;
		$search[] = $callsign[2];
		if ($vatspy && strlen($callsign[2]) == 3) if ($iata = array_search($callsign[2],$vatspy['IATA'])) $search[] = $vatspy['ICAO'][$iata];
		$searches['APT'] = array_unique($search);

		// collecting search key words for the search in the FIR table
		$search = [];
		if ($vatspy && $orig) if ($icao = array_search($orig,$vatspy['ICAO'])) $search[] = $vatspy['FIR'][$icao];
		$search[] = $callsign[1];
		$search[] = $callsign[2];
		if ($vatspy) {
			if ($iata = array_search($callsign[2],$vatspy['IATA'])) $search[] = $vatspy['FIR'][$iata];
			if ($icao = array_search($callsign[2],$vatspy['ICAO'])) $search[] = $vatspy['FIR'][$icao];
		}
		for ($len = strlen($callsign[2]); $len>1; $len--) $search[] = substr($callsign[2],0,$len);
		$searches['FIR'] = array_unique($search);

		if ($code = $this->get_range_code($callsign, $searches, $conditions)) return $code;
		if ($flightrule == 'V' && $this->squawkgroups) if ($code = $this->get_group_code($callsign, $conditions)) return $code;
		return false;
	}

	private function get_range_code($callsign, array $searches, array $conditions) {
		// completition of the $conditions array
		$conditions = array_unique($conditions);

		/*
		searching for a code with the following logic
			1. going through all the range table names in $searches
			2. going through all the search terms for that specific table
			3. look for a range name starting with the search phrase
			4. within that entry, going through all the $conditions entries and looking for a match (the default entry is 'zzzz')
			5. checking all available codes of a found range if they are available for assignment, and if so return that code
		*/
		foreach ($searches as $tablekey => $search) {
			if (!array_key_exists($tablekey,$this->squawkranges)) continue;
			foreach ($search as $needle) {
				foreach ($conditions as $condition) {
					if ($this->is_debug) echo 'Scanning range in '.$tablekey.' table for match with '.strtoupper($needle).', condition is '.strtoupper($condition).'<br>';
					if (array_key_exists($needle,$this->squawkranges[$tablekey]) && strlen($callsign[2]) <= strlen($needle)) {
						// look first for an exact match
						if ($codesearch = $this->search_code_range($tablekey, $needle, $condition)) return $codesearch;
					} else {
						// otherwise, try partial matches
						foreach (array_keys($this->squawkranges[$tablekey]) as $rangename) {
							// echo substr($rangename,0,strlen($needle)).' =? '.$needle.'<br>';
							if (strcmp(substr($rangename,0,strlen($needle)),$needle) == 0 && strlen($callsign[2]) <= strlen($rangename)) {
								if ($codesearch = $this->search_code_range($tablekey, $rangename, $condition)) return $codesearch;
							}
						}
					}
				}
			}
		}
		return false;
	}

	private function get_group_code($callsign, array $conditions) {
		if (array_key_exists('latitude',$_GET) && array_key_exists('longitude',$_GET)) {
			$position = [floatval(filter_input(INPUT_GET,'longitude')), floatval(filter_input(INPUT_GET,'latitude'))];

			foreach ($this->squawkgroups as $group_code => $groups) {
				foreach ($groups as $group_key => $group) {
					if ($this->is_debug) echo "Checking conditions for group code '$group_code'<br>";
					if (isset($group['ATC'])) {
						if (!array_any($group['ATC'], function (string $value) use ($callsign) {
							return str_starts_with(strtoupper($callsign[1]), strtoupper($value));
						})) continue;
					}
					foreach ($conditions as $condition_key => $condition_str) {
						if (isset($group[$condition_key])) {
							if (strcasecmp($condition_str, $group[$condition_key]) != 0) {
								continue 2;
							}
						}
					}

					if (isset($group['geo_limit'])) {
						foreach ($group['geo_limit'] as $polygon_key => $polygon) {
							if ($this->pointInPolygon($position, $polygon)) {
								if ($this->is_debug) echo "Position ".implode(',', $position)." is within polygon $polygon_key<br>";
								return $group_code;
							} else {
								if ($this->is_debug) echo "Position ".implode(',', $position)." is outside polygon $polygon_key<br>";
							}
						}
					} else {
						if ($this->is_debug) echo "Group code without geographical restrictions found<br>";
						return $group_code;
					}
				}
			}
		}

		return false;
	}

	private function search_code_range($tablekey, $rangename, $condition) {
		// searching in a specific table (APT or FIR) and range name for a code matching the condition
		if (array_key_exists($condition,$this->squawkranges[$tablekey][$rangename])) {
			foreach ($this->squawkranges[$tablekey][$rangename][$condition] as $range) {
				for ($code = $range[0];$code<=$range[1];$code++) {
					if ($this->is_debug) echo 'probing code '.$code.'<br>';
					if (array_key_exists($code,$this->squawk)) return $code;
					else {
						if ($this->is_debug) echo 'code '.$code.' already reserved<br>';
					}
				}
			}
		}

		return false;
	}

	/*
	Description: The point-in-polygon algorithm allows you to check if a point is
	inside a polygon or outside of it.
	Author: Michaël Niessen (2009)
	Website: http://AssemblySys.com

	If you find this script useful, you can show your
	appreciation by getting Michaël a cup of coffee ;)
	https://ko-fi.com/assemblysys

	As long as this notice (including author name and details) is included and
	UNALTERED, this code can be used and distributed freely.
	*/

	// adapted and simplified version of the source named above
	private function pointInPolygon($point, $vertices) {
        // Check if the point is inside the polygon or on the boundary
        $intersections = 0; 
        $vertices_count = count($vertices);

        for ($i=1; $i < $vertices_count; $i++) {
            $vertex1 = $vertices[$i-1]; 
            $vertex2 = $vertices[$i];
            if ($point[1] > min($vertex1[1], $vertex2[1]) and $point[1] <= max($vertex1[1], $vertex2[1]) and $point[0] <= max($vertex1[0], $vertex2[0]) and $vertex1[1] != $vertex2[1]) { 
                $xinters = ($point[1] - $vertex1[1]) * ($vertex2[0] - $vertex1[0]) / ($vertex2[1] - $vertex1[1]) + $vertex1[0]; 
                if ($vertex1[0] == $vertex2[0] || $point[0] <= $xinters) {
                    $intersections++; 
                }
            } 
        } 

        // If the number of edges we passed through is odd, then it's in the polygon. 
		return $intersections % 2;
    }

	// generic functions to read and write files
	function read_bin_file($file, $key = '') {
		if (file_exists($this->root.$this->f_bin.$file)) {
			if (($data = unserialize(file_get_contents($this->root.$this->f_bin.$file)))!==false) {
				if (!empty($key) && array_key_exists($key,$data)) return $data[$key];
				return $data;
			}
		}
		$this->write_log("file reading error;bin file ".$file);
		return false;
	}

	private function write_bin_file($file, $data, $key = '') {
		if ($this->is_debug) file_put_contents($this->root.$this->f_debug.'log.txt',date("c").' '.__FILE__.", write_bin_file $file\n",FILE_APPEND);
		if (empty($key)) {
			//if ($this->is_debug) echo var_dump(serialize($data)).'<br>';
			if (file_put_contents($this->root.$this->f_bin.$file, serialize($data))) return true;
		} else if (($d = $this->read_bin_file($file))!==false) {
			$d[$key] = $data;
			if (file_put_contents($this->root.$this->f_bin.$file, serialize($d))) return true;
		} else {
			if (file_put_contents($this->root.$this->f_bin.$file, serialize([]))) return true;
		}
		$this->write_log("file writing error;bin file ".$key);
		return false;
	}

	private function write_log($text) {
		file_put_contents($this->root.$this->file_log,$this->logtext_prefix.sprintf("%.6f",microtime(true)-$this->timer).";".$text."\n",FILE_APPEND);
	}

	function collect_sqwk_range_data() {
		$rfiles = scandir($this->root.$this->f_config);
		$sqwk_ranges = [];
		if (flock($this->lock, LOCK_EX)) {
			foreach ($rfiles as $rfile) {
				$path_parts = pathinfo($rfile);
				if ($path_parts['extension'] == 'dat') {
					$this->set_sqwk_range($path_parts['filename'], file_get_contents($this->root.$this->f_config.$rfile));
				} else if ($path_parts['extension'] == 'geojson') {
					$sqwk_ranges = $sqwk_ranges + $this->get_sqwk_areas($this->root.$this->f_config.$rfile);
				}
			}
			$this->write_bin_file('groups.bin',$sqwk_ranges);
			flock($this->lock, LOCK_UN);
		}
	}

	// the following functions are used for the website-based interactions (change and display codes)
	function set_sqwk_range($key,$text) {
		if ($text=='') {
			$codes = [];
		} else {
			if (!preg_match_all('/([A-Z0-9_]{3,}):([\d]{4})(?::([\d]{4}))?(?::([A-Z]{2,4}|\*))?/i',$text,$m)) return false;

			foreach ($m[0] as $k0 => $m0) {
				$orig = strtolower($m[1][$k0]);
				if (strlen($m[4][$k0])<2) $needle = 'zzzz';
				else $needle = strtolower($m[4][$k0]);

				$codes[$orig][$needle][] = array(octdec($m[2][$k0]), (!empty($m[3][$k0])) ? octdec($m[3][$k0]) : octdec($m[2][$k0]));
			}
		}
		$this->write_bin_file('ranges.bin',$codes,$key);
	}

	function get_sqwk_areas($file) {
		$path_parts = pathinfo($file);
		$condition_list = ['flight_rule' => 'FR', 'origin' => 'ADEP', 'destination' => 'ADES'];

		$json = json_decode(file_get_contents($file), true);
		if (!$json) {
			$this->write_log("file reading error;config file ".$path_parts['basename']);
			return [];
		}

		foreach (['type', 'crs', 'features'] as $attr) {
			if (!isset($json[$attr])) {
				$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected attribute '$attr'");
				return [];
			}
		}
		if ($json['type']!='FeatureCollection') {
			$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected type");
			return [];
		}
		else if (!isset($json['crs']['properties']['name'])) {
			$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected attribute 'name' for its crs");
			return [];
		}
		else if ($json['crs']['properties']['name']!='urn:ogc:def:crs:OGC:1.3:CRS84') {
			$this->write_log("json validation error;file ".$path_parts['basename']." does contains an unknown crs");
			return [];
		}

		foreach ($json['features'] as $feature) {
			foreach (['type', 'properties'] as $attr) {
				if (!isset($feature[$attr])) {
					$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected attribute '$attr' for its feature");
					continue 2;
				}
			}
			foreach (['squawk_code'] as $attr) {
				if (!isset($feature['properties'][$attr])) {
					$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected attribute '$attr' for its feature properties");
					continue 2;
				}
			}
			if (!preg_match('/[0-7]{4}/', $feature['properties']['squawk_code'])) {
				$this->write_log("json validation error;file ".$path_parts['basename']." does contain invalid characters for its feature property 'squawk_code' (".$feature['properties']['squawk_code'].")");
			}

			foreach (['geometry'] as $attr) {
				if (!array_key_exists($attr, $feature)) {
					$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected attribute '$attr' for its feature");
					continue 2;
				}
			}

			$conditions = [];
			foreach ($feature['properties'] as $property => $value) {
				if ($property == 'atc_callsign_match') {
					$conditions['ATC'] = explode(',', $value);
				} else if (array_key_exists($property, $condition_list)) {
					if ($value) {
						$conditions[$condition_list[$property]] = $value;
					}
				}
			}

			if (isset($feature['geometry'])) {
				foreach (['type', 'coordinates'] as $attr) {
					if (!isset($feature['geometry'][$attr])) {
						$this->write_log("json validation error;file ".$path_parts['basename']." does not contain the expected attribute '$attr' for its geometry feature");
						continue 2;
					}
				}

				$geometry = $feature['geometry']['coordinates'];
				if (!str_contains($feature['geometry']['type'], 'Multi')) {
					$geometry = [$geometry];
				}

				foreach ($geometry as $geometry_key => $polygon) {
					// echo "\nPolygon no. ".$geometry_key;
					foreach ($polygon as $coordinates) {
						$conditions['geo_limit'][] = $coordinates;
					}
				}
			} else if (!isset($feature['properties']['atc_callsign_match'])) {
				$this->write_log("json validation error;file ".$path_parts['basename']." is missing either of the attributes 'atc_callsign_match' for its feature properties or 'geometry' for its feature");
				continue;
			}

			// echo $feature['properties']['squawk_code']."\n";
			$code_areas[octdec($feature['properties']['squawk_code'])][] = $conditions;
		}
		// echo var_dump($code_areas);
		return $code_areas;
	}

	function get_sqwk_ranges() {
		$json = [];
		if ($codes = $this->read_bin_file('ranges.bin')) {
			foreach ($codes as $table => $category) {
				$txt = '';
				foreach ($category as $orig => $group) {
					foreach ($group as $needle => $ranges) {
						foreach ($ranges as $range) {
							$txt .= "\n".strtoupper($orig).':'.sprintf("%04o",$range[0]).':'.sprintf("%04o",$range[1]);
							if ($needle!='zzzz') $txt .= ':'.strtoupper($needle);
						}
					}
				}
				$json[$table] = trim($txt);
			}
		}
		return json_encode($json);
	}

	function get_reserved_codes() {
		$json = [];
		if ($codes = $this->read_bin_file('squawks.bin')) {
			ksort($codes);
			$txt = '';
			foreach ($codes as $squawk => $time) {
				$txt .= "\n".sprintf("%04o",$squawk)."\t".date('Y-m-d H:i:s',$time);
			}
		}
		return json_encode(array(trim($txt)));
	}
}


class CCAMSstats {

	private $is_debug;
	private $root;
	private $f_log;
	private $logfile_prefix;
	private $logdata;
	private $stats;


	function __construct($debug = false) {
		date_default_timezone_set("UTC");
		$this->root = __DIR__;
		$this->f_log = '/log/';
		$this->logfile_prefix = 'log_';
		$this->logdata = [];

		if ($debug) {
			$this->is_debug = true;
			error_reporting(E_ALL);
			//echo 'running CCAMS class<br>';
			echo realpath(__FILE__).'<br>';
		} else {
			$this->is_debug = false;
			error_reporting(0);
		}
	}

	// used to get the date list for statistics
	function logStats($maxDate = new DateTime(), $maxCount = 64) {
		if (($logfiles = glob($this->root.$this->f_log.$this->logfile_prefix.'*'))!==false) {
			rsort($logfiles);
			foreach ($logfiles as $file) {
				$date = new DateTimeImmutable(str_replace($this->logfile_prefix,'',pathinfo($file, PATHINFO_FILENAME)));
				if ($date > $maxDate) continue;
				// if ($date->diff(new DateTime('now'))->days > $maxDaysBack) continue;
				$logs['day'][$date->format('Y-m-d')] = '';
				$logs['week'][$date->format('Y-W')] = '';
				$logs['month'][$date->format('Y-m')] = '';
				if (count($logs['day']) >= $maxCount) break;
			}
			foreach ($logs as $key => $value) {
				$resp[$key] = array_keys($value);
			}
			//array_unique($resp, SORT_STRING);
			return json_encode($resp);
		}
		return false;
	}

	function logEntries($mindate, $maxdate = new DateTime()) {
		foreach ($this->logdata as $log) {
			if (!array_filter(array('vdata updated extracted transponder codes', 'code assigned'), fn($needle) => strpos($log['log event'], $needle) !== false))
				$loglist[] = $log;
		}
		rsort($loglist);
		return json_encode($loglist);
	}

	function readStats($date) {
		if (!$date instanceof DateTime) return false;
		$file = $this->root.$this->f_log.$this->logfile_prefix.$date->format('Y-m-d').'.txt';
		if (!file_exists($file)) return false;
		if (($logdata = file($file))===false) return false;
		foreach ($logdata as $line) {
			if (count($data = explode(";",trim($line))) >= 8) {
				while (substr_count($data[2],'(') != substr_count($data[2],')') && count($data) > 8) {
					$data = array_merge(array_slice($data, 0, 2), [implode(';', array_slice($data, 2, 2))], array_slice($data, 4));
				}
				if (count($data) == 8) $data[] = '';
				if (count($data) != 9) continue;
				$data = array_combine(array('timestamp', 'IP address', 'HTTP user agent', 'HTTP request URI', 'debug', 'callsign', 'execution time', 'log event', 'event result'), $data);
				if (preg_match_all('/(\w+)=([^&]+)/', $data['HTTP request URI'], $getparams, PREG_SET_ORDER)) {
					foreach ($getparams as $getparam) {
						$data[$getparam[1]] = $getparam[2];
					}
				}
				if ($callsign = explode('_', $data['callsign'])) {
					$data['designator'] = reset($callsign);
					$data['facility'] = end($callsign);
				}
				$data['client'] = preg_replace('/.+?plug-in: (\w+)\/([\d\w\.]+)/','$1 $2',$data['HTTP user agent']);
				$this->logdata[] = $data;
			}
			// echo var_dump($data).'<br>';
		}
		// echo 'Log Data Counter: '.count($this->logdata).'<br>';
		// echo var_dump($this->logdata);
	}

	function createStats() {
		if (count($this->logdata) == 0) return json_encode(array());
		for ($i = 0; $i < 3; $i++) {
			$stats = [];
			$stats['hour'] = array_fill(0,24,0);
			$stats['facility'] = array_fill_keys(['DEL', 'GND', 'TWR', 'APP', 'DEP', 'CTR', 'FSS'], 0);
			$stats['flightrule'] = array_fill_keys(['I', 'V'], 0);
			if ($i > 0) {
				foreach (['date', 'year', 'month', 'week', 'day', 'callsign', 'designator', 'client', 'orig', 'dest', 'connectiontype'] as $key) {
					$stats[$key] = array_fill_keys(array_keys($statslib[0][$key]), 0);
				}
			}
			foreach ($this->logdata as $log) {
				$date = new DateTime($log['timestamp']);
				if (!array_filter(array('not authorised', 'spam protection', 'code assigned'), fn($needle) => strpos($log['log event'], $needle) !== false)) continue;
				if ($i > 0 && $i-1 != ($log['log event'] == 'code assigned')) continue;
				$dateformats = array('date' => 'Y-m-d', 'year' => 'Y', 'month' => 'n', 'week' => 'W', 'day' => 'j', 'hour' => 'G');
				foreach ($dateformats as $formatname => $dateformat) {
					if (!isset($stats[$formatname][$date->format($dateformat)])) $stats[$formatname][$date->format($dateformat)] = 1;
					else $stats[$formatname][$date->format($dateformat)] += 1;
				}
				foreach (array('callsign', 'designator', 'facility', 'client', 'orig', 'dest', 'flightrule', 'connectiontype') as $simplecount) {
					if (array_key_exists($simplecount, $log) && strlen($log[$simplecount]) > 0) {
						if (!isset($stats[$simplecount][$log[$simplecount]])) $stats[$simplecount][$log[$simplecount]] = 1;
						else $stats[$simplecount][$log[$simplecount]] += 1;
					}
				}
			}
			ksort($stats['designator']);
			krsort($stats['client']);
			$statslib[] = $stats;
		}
		return json_encode($statslib);

		//echo var_dump($stats);
	}
}


// required for PHP < 8.4
if (PHP_VERSION_ID < 80400) {
	function array_any(array $array, callable $callback): bool {
		foreach ($array as $key => $value) {
			if ($callback($value, $key)) {
				return true;
			}
		}
		return false;
	}
}

?>