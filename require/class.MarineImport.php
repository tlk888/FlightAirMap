<?php
/**
 * This class is part of FlightAirmap. It's used to import marine data
 *
 * Copyright (c) Ycarus (Yannick Chabanois) <support@flightairmap.com>
 * Licensed under AGPL license.
 * For more information see: https://www.flightairmap.com/
*/
require_once(dirname(__FILE__).'/class.Connection.php');
require_once(dirname(__FILE__).'/class.AIS.php');
require_once(dirname(__FILE__).'/class.Marine.php');
require_once(dirname(__FILE__).'/class.MarineLive.php');
require_once(dirname(__FILE__).'/class.MarineArchive.php');
require_once(dirname(__FILE__).'/class.Scheduler.php');
require_once(dirname(__FILE__).'/class.Translation.php');
require_once(dirname(__FILE__).'/class.Stats.php');
require_once(dirname(__FILE__).'/class.Source.php');
if (isset($globalServerAPRS) && $globalServerAPRS) {
    require_once(dirname(__FILE__).'/class.APRS.php');
}

class MarineImport {
    private $all_tracked = array();
    private $last_delete_hourly = 0;
    private $last_delete = 0;
    private $stats = array();
    private $tmd = 0;
    private $source_location = array();
    public $db = null;
    public $nb = 0;

    public function __construct($dbc = null) {
	global $globalBeta, $globalServerAPRS, $APRSMarine, $globalNoDB;
	if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
	    $Connection = new Connection($dbc);
	    $this->db = $Connection->db();
	    date_default_timezone_set('UTC');
	}
	// Get previous source stats
	/*
	$Stats = new Stats($dbc);
	$currentdate = date('Y-m-d');
	$sourcestat = $Stats->getStatsSource($currentdate);
	if (!empty($sourcestat)) {
	    foreach($sourcestat as $srcst) {
	    	$type = $srcst['stats_type'];
		if ($type == 'polar' || $type == 'hist') {
		    $source = $srcst['source_name'];
		    $data = $srcst['source_data'];
		    $this->stats[$currentdate][$source][$type] = json_decode($data,true);
	        }
	    }
	}
	*/
	if (isset($globalServerAPRS) && $globalServerAPRS) {
	    $APRSMarine = new APRSMarine();
	    //$APRSSpotter->connect();
	}
    }

    public function checkAll() {
	global $globalDebug, $globalNoDB, $globalVM;
	if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
	    if ($globalDebug) echo "Update last seen tracked data...\n";
	    foreach ($this->all_tracked as $key => $flight) {
		if (isset($this->all_tracked[$key]['id'])) {
		    //echo $this->all_tracked[$key]['id'].' - '.$this->all_tracked[$key]['latitude'].'  '.$this->all_tracked[$key]['longitude']."\n";
    		    $Marine = new Marine($this->db);
    		    if (isset($globalVM) && $globalVM) {
			if ($this->all_tracked[$key]['status'] == 'Racing') {
    			    $Marine->updateLatestMarineData($this->all_tracked[$key]['id'],$this->all_tracked[$key]['ident'],$this->all_tracked[$key]['latitude'],$this->all_tracked[$key]['longitude'],$this->all_tracked[$key]['speed'],$this->all_tracked[$key]['datetime'],$this->all_tracked[$key]['distance'],$this->all_tracked[$key]['race_rank'],$this->all_tracked[$key]['race_time'],$this->all_tracked[$key]['status'],$this->all_tracked[$key]['race_begin']);
    			} else {
    			    $timerace = (strtotime($this->all_tracked[$key]['race_begin'])+$this->all_tracked[$key]['race_time']);
    			    if ($timerace > time()) $finaldatetime = NULL;
    			    else $finaldatetime = date('Y-m-d H:i:s',$timerace);
    			    $Marine->updateLatestMarineData($this->all_tracked[$key]['id'],$this->all_tracked[$key]['ident'],$this->all_tracked[$key]['latitude'],$this->all_tracked[$key]['longitude'],$this->all_tracked[$key]['speed'],$finaldatetime,$this->all_tracked[$key]['distance'],$this->all_tracked[$key]['race_rank'],$this->all_tracked[$key]['race_time'],$this->all_tracked[$key]['status'],$this->all_tracked[$key]['race_begin']);
    			}
    		    } else {
			$Marine->updateLatestMarineData($this->all_tracked[$key]['id'],$this->all_tracked[$key]['ident'],$this->all_tracked[$key]['latitude'],$this->all_tracked[$key]['longitude'],$this->all_tracked[$key]['speed'],$this->all_tracked[$key]['datetime'],$this->all_tracked[$key]['distance'],$this->all_tracked[$key]['race_rank'],$this->all_tracked[$key]['race_time'],$this->all_tracked[$key]['status'],$this->all_tracked[$key]['race_begin']);
		    }
		}
	    }
	}
    }

    public function del() {
	global $globalDebug, $globalNoDB, $globalNoImport;
	// Delete old infos
	if ($globalDebug) echo 'Delete old values and update latest data...'."\n";
	foreach ($this->all_tracked as $key => $flight) {
    	    if (isset($flight['lastupdate'])) {
        	if ($flight['lastupdate'] < (time()-3000)) {
            	    if ((!isset($globalNoImport) || $globalNoImport !== TRUE) && (!isset($globalNoDB) || $globalNoDB !== TRUE)) {
            		if (isset($this->all_tracked[$key]['id'])) {
            		    if ($globalDebug) echo "--- Delete old values with id ".$this->all_tracked[$key]['id']."\n";
			    /*
			    $MarineLive = new MarineLive();
            		    $MarineLive->deleteLiveMarineDataById($this->all_tracked[$key]['id']);
			    $MarineLive->db = null;
			    */
            		    //$real_arrival = $this->arrival($key);
            		    $Marine = new Marine($this->db);
            		    if ($this->all_tracked[$key]['latitude'] != '' && $this->all_tracked[$key]['longitude'] != '') {
				if (isset($globalVM) && $globalVM) {
				    if ($this->all_tracked[$key]['status'] == 'Racing') {
					$result = $Marine->updateLatestMarineData($this->all_tracked[$key]['id'],$this->all_tracked[$key]['ident'],$this->all_tracked[$key]['latitude'],$this->all_tracked[$key]['longitude'],$this->all_tracked[$key]['speed'],$this->all_tracked[$key]['datetime'],$this->all_tracked[$key]['distance'],$this->all_tracked[$key]['race_rank'],$this->all_tracked[$key]['race_time'],$this->all_tracked[$key]['status'],$this->all_tracked[$key]['race_begin']);
				    } else {
					$timerace = strtotime($this->all_tracked[$key]['race_begin'])+$this->all_tracked[$key]['race_time'];
	        			if ($timerace > time()) $finaldatetime = NULL;
					else $finaldatetime = date('Y-m-d H:i:s',$timerace);
					$result = $Marine->updateLatestMarineData($this->all_tracked[$key]['id'],$this->all_tracked[$key]['ident'],$this->all_tracked[$key]['latitude'],$this->all_tracked[$key]['longitude'],$this->all_tracked[$key]['speed'],$finaldatetime,$this->all_tracked[$key]['distance'],$this->all_tracked[$key]['race_rank'],$this->all_tracked[$key]['race_time'],$this->all_tracked[$key]['status'],$this->all_tracked[$key]['race_begin']);
				    }
				} else {
					$result = $Marine->updateLatestMarineData($this->all_tracked[$key]['id'],$this->all_tracked[$key]['ident'],$this->all_tracked[$key]['latitude'],$this->all_tracked[$key]['longitude'],$this->all_tracked[$key]['speed'],$this->all_tracked[$key]['datetime'],$this->all_tracked[$key]['distance'],$this->all_tracked[$key]['race_rank'],$this->all_tracked[$key]['race_time'],$this->all_tracked[$key]['status'],$this->all_tracked[$key]['race_begin']);
				}
				if ($globalDebug && $result != 'success') echo '!!! ERROR : '.$result."\n";
			    }
			    // Put in archive
//				$Marine->db = null;
			}
            	    }
            	    unset($this->all_tracked[$key]);
    	        }
	    }
        }
    }

    public function add($line) {
	global $globalFork, $globalDistanceIgnore, $globalDaemon, $globalDebug, $globalCoordMinChange, $globalDebugTimeElapsed, $globalCenterLatitude, $globalCenterLongitude, $globalBeta, $globalSourcesupdate, $globalAllTracked, $globalNoImport, $globalNoDB, $globalServerAPRS,$APRSMarine, $globalLiveInterval, $globalVM;
	if (!isset($globalCoordMinChange) || $globalCoordMinChange == '') $globalCoordMinChange = '0.02';
	date_default_timezone_set('UTC');
	$dataFound = false;
	$send = false;
	
	// SBS format is CSV format
	if(is_array($line) && (isset($line['mmsi']) || isset($line['id']))) {
	    //print_r($line);
  	    if (isset($line['mmsi']) || isset($line['id'])) {

		
		// Increment message number
		if (isset($line['sourcestats']) && $line['sourcestats'] == TRUE) {
		    $current_date = date('Y-m-d');
		    if (isset($line['source_name'])) $source = $line['source_name'];
		    else $source = '';
		    if ($source == '' || $line['format_source'] == 'aprs') $source = $line['format_source'];
		    if (!isset($this->stats[$current_date][$source]['msg'])) {
		    	$this->stats[$current_date][$source]['msg']['date'] = time();
		    	$this->stats[$current_date][$source]['msg']['nb'] = 1;
		    } else $this->stats[$current_date][$source]['msg']['nb'] += 1;
		}
		
		
		$Common = new Common();
		$AIS = new AIS();
	        if (!isset($line['id'])) $id = trim($line['mmsi']);
	        else $id = trim($line['id']);
		
		if (!isset($this->all_tracked[$id])) {
		    $this->all_tracked[$id] = array();
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('addedMarine' => 0));
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('ident' => '','latitude' => '', 'longitude' => '', 'speed' => '0', 'heading' => '', 'format_source' => '','source_name' => '','comment'=> '','type' => '','typeid' => '','noarchive' => false,'putinarchive' => true,'over_country' => '','mmsi' => '','status' => '','status_id' => '','imo' => '','callsign' => '','arrival_code' => '','arrival_date' => '','mmsi_type' => '','captain_id' => '','captain_name' => '','race_id' => '','race_name' => '','distance' => NULL,'race_rank' => NULL,'race_time' => NULL,'race_begin' => ''));
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('lastupdate' => time()));
		    if (!isset($line['id'])) {
			if (!isset($globalDaemon)) $globalDaemon = TRUE;
			$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('id' => $id.'-'.date('YmdHi')));
		     } else $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('id' => $line['id']));
		    if ($globalAllTracked !== FALSE) $dataFound = true;
		}
		
		if (isset($line['mmsi']) && $line['mmsi'] != '' && $line['mmsi'] != $this->all_tracked[$id]['mmsi']) {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('mmsi' => $line['mmsi']));
		    if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
			$Marine = new Marine($this->db);
			$identity = $Marine->getIdentity($line['mmsi']);
			if (!empty($identity)) {
			    $this->all_tracked[$id]['ident'] = $identity['ship_name'];
			    $this->all_tracked[$id]['type'] = $identity['type'];
			    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('typeid' => $AIS->getShipTypeID($identity['type'])));
			}
			//print_r($identity);
			unset($Marine);
			//$dataFound = true;
		    }
		}
		if (isset($line['type_id'])) {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('type' => $AIS->getShipType($line['type_id'])));
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('typeid' => $line['type_id']));
		}
		if (isset($line['type']) && $line['type'] != '' && $this->all_tracked[$id]['type'] == '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('type' => $line['type']));
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('typeid' => $AIS->getShipTypeID($line['type'])));
		} elseif (isset($line['type']) && $line['type'] != '' && $this->all_tracked[$id]['type'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('type' => $line['type']));
		}
		if (isset($line['status']) && $line['status'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('status' => $line['status']));
		}
		if (isset($line['status_id']) && (!isset($this->all_tracked[$id]['status_id']) || $this->all_tracked[$id]['status_id'] != $line['status_id'])) {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('status_id' => $line['status_id']));
		    if ($this->all_tracked[$id]['addedMarine'] == 1) {
			if (!isset($globalNoImport) || $globalNoImport !== TRUE) {
			    if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				$Marine = new Marine($this->db);
				$Marine->updateStatusMarineData($this->all_tracked[$id]['id'],$this->all_tracked[$id]['status_id'],$this->all_tracked[$id]['status']);
				unset($Marine);
			    }
			}
		    }
		}


		if (isset($line['mmsi_type']) && $line['mmsi_type'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('mmsi_type' => $line['mmsi_type']));
		}
		if (isset($line['imo']) && $line['imo'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('imo' => $line['imo']));
		}
		if (isset($line['callsign']) && $line['callsign'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('callsign' => $line['callsign']));
		}
		if (isset($line['arrival_code']) && $line['arrival_code'] != '') {
		    if (!isset($this->all_tracked[$id]['arrival_code'])) {
			$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('arrival_code' => $line['arrival_code']));
			if ($globalDebug) echo $this->all_tracked[$id]['id'].' => New arrival: '.$line['arrival_code']."\n";
			if ($this->all_tracked[$id]['addedMarine'] != 0) {
			    if (!isset($globalNoImport) || $globalNoImport !== TRUE) {
				if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				    $Marine = new Marine($this->db);
				    $fromsource = NULL;
				    $Marine->updateArrivalPortNameMarineData($this->all_tracked[$id]['id'],$this->all_tracked[$id]['arrival_code'],$fromsource);
				    $Marine->db = null;
				}
			    }
			}
		    } elseif ($this->all_tracked[$id]['arrival_code'] != $line['arrival_code']) {
			$this->all_tracked[$id]['arrival_code'] = $line['arrival_code'];
			if ($globalDebug) echo $this->all_tracked[$id]['id'].' => New arrival: '.$line['arrival_code']."\n";
			if (!isset($line['id'])) {
				$this->all_tracked[$id]['id'] = $id.'-'.date('YmdHi');
				$this->all_tracked[$id]['forcenew'] = 1;
				$this->all_tracked[$id]['addedMarine'] = 0;
			}
		    }
		}
		if (isset($line['arrival_date']) && $line['arrival_date'] != '') {
		    if (strtotime($line['arrival_date']) > time()) $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('arrival_date' => $line['arrival_date']));
		}
		if (isset($line['captain_id']) && $line['captain_id'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('captain_id' => $line['captain_id']));
		}
		if (isset($line['captain_name']) && $line['captain_name'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('captain_name' => $line['captain_name']));
		}
		if (isset($line['race_id']) && $line['race_id'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('race_id' => $line['race_id']));
		}
		if (isset($line['race_name']) && $line['race_name'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('race_name' => $line['race_name']));
		}
		if (isset($line['race_rank']) && $line['race_rank'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('race_rank' => $line['race_rank']));
		}
		if (isset($line['race_time']) && $line['race_time'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('race_time' => $line['race_time']));
		}
		if (isset($line['race_begin']) && $line['race_begin'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('race_begin' => $line['race_begin']));
		}
		if (isset($line['distance']) && $line['distance'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('distance' => $line['distance']));
		}

		//if (isset($line['ident']) && $line['ident'] != '' && $line['ident'] != '????????' && $line['ident'] != '00000000' && ($this->all_tracked[$id]['ident'] != trim($line['ident'])) && preg_match('/^[a-zA-Z0-9-]+$/', $line['ident'])) {
		if (isset($line['ident']) && $line['ident'] != '' && $line['ident'] != '????????' && $line['ident'] != '00000000' && ($this->all_tracked[$id]['ident'] != trim($line['ident']))) {
		    if (!isset($globalNoImport) || $globalNoImport !== TRUE) {
			if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
			    $timeelapsed = microtime(true);
			    $Marine = new Marine($this->db);
			    $Marine->addIdentity($this->all_tracked[$id]['mmsi'],$this->all_tracked[$id]['imo'],$this->all_tracked[$id]['ident'],$this->all_tracked[$id]['callsign'],$this->all_tracked[$id]['type']);
			    $Marine->db = null;
			}
		    }
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('ident' => trim($line['ident'])));
		    if ($this->all_tracked[$id]['addedMarine'] == 1) {
			if (!isset($globalNoImport) || $globalNoImport !== TRUE) {
			    if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				$timeelapsed = microtime(true);
				$Marine = new Marine($this->db);
				$fromsource = NULL;
				$result = $Marine->updateIdentMarineData($this->all_tracked[$id]['id'],$this->all_tracked[$id]['ident'],$fromsource);
				if ($globalDebug && $result != 'success') echo '!!! ERROR : '.$result."\n";
				$Marine->db = null;
				if ($globalDebugTimeElapsed) echo 'Time elapsed for update identspotterdata : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
			    }
			}
		    }
		    if (!isset($this->all_tracked[$id]['id'])) $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('id' => $this->all_tracked[$id]['ident']));
		}

		if (isset($line['datetime']) && strtotime($line['datetime']) > time()-30*60 && strtotime($line['datetime']) < time()+20*60) {
		    if (!isset($this->all_tracked[$id]['datetime']) || strtotime($line['datetime']) > strtotime($this->all_tracked[$id]['datetime'])) {
			$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('datetime' => $line['datetime']));
		    } else {
				if (strtotime($line['datetime']) == strtotime($this->all_tracked[$id]['datetime']) && $globalDebug) echo "!!! Date is the same as previous data for ".$this->all_tracked[$id]['mmsi']."\n";
				elseif (strtotime($line['datetime']) > strtotime($this->all_tracked[$id]['datetime']) && $globalDebug) echo "!!! Date previous latest data (".$line['datetime']." > ".$this->all_tracked[$id]['datetime'].") !!! for ".$this->all_tracked[$id]['hex']." - format : ".$line['format_source']."\n";
				return '';
		    }
		} elseif (isset($line['datetime']) && strtotime($line['datetime']) <= time()-30*60) {
			if ($globalDebug) echo "!!! Date is too old ".$this->all_tracked[$id]['mmsi']." - format : ".$line['format_source']."!!!\n";
			return '';
		} elseif (isset($line['datetime']) && strtotime($line['datetime']) >= time()+20*60) {
			if ($globalDebug) echo "!!! Date is in the future ".$this->all_tracked[$id]['mmsi']." - format : ".$line['format_source']."!!!\n";
			return '';
		} elseif (!isset($line['datetime'])) {
			date_default_timezone_set('UTC');
			$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('datetime' => date('Y-m-d H:i:s')));
		} else {
			if ($globalDebug) echo "!!! Unknow date error ".$this->all_tracked[$id]['mmsi']." date: ".$line['datetime']." - format : ".$line['format_source']."!!!\n";
			return '';
		}


		if (isset($line['speed'])) {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('speed' => round($line['speed'],2)));
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('speed_fromsrc' => true));
		} else if (!isset($this->all_tracked[$id]['speed_fromsrc']) && isset($this->all_tracked[$id]['time_last_coord']) && $this->all_tracked[$id]['time_last_coord'] != time() && isset($line['latitude']) && isset($line['longitude'])) {
		    $distance = $Common->distance($line['latitude'],$line['longitude'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],'m');
		    if ($distance > 1000 && $distance < 10000) {
			$speed = $distance/(time() - $this->all_tracked[$id]['time_last_coord']);
			$speed = $speed*3.6;
			if ($speed < 1000) $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('speed' => round($speed,2)));
  			if ($globalDebug) echo "ø Calculated Speed for ".$this->all_tracked[$id]['hex']." : ".$speed." - distance : ".$distance."\n";
		    }
		}

	        if (isset($line['latitude']) && isset($line['longitude']) && $line['latitude'] != '' && $line['longitude'] != '' && is_numeric($line['latitude']) && is_numeric($line['longitude'])) {
	    	    if (isset($this->all_tracked[$id]['time_last_coord'])) $timediff = round(time()-$this->all_tracked[$id]['time_last_coord']);
	    	    else unset($timediff);
	    	    if ($this->tmd > 5 ||
	    		!isset($timediff) ||
	    		$timediff > $globalLiveInterval ||
	    		(
	    		    $timediff > 30 && 
	    		    isset($this->all_tracked[$id]['latitude']) &&
	    		    isset($this->all_tracked[$id]['longitude']) &&
	    		    $Common->withinThreshold($timediff,$Common->distance($line['latitude'],$line['longitude'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],'m'))
	    		)
	    		) {
			if (isset($this->all_tracked[$id]['archive_latitude']) && isset($this->all_tracked[$id]['archive_longitude']) && isset($this->all_tracked[$id]['livedb_latitude']) && isset($this->all_tracked[$id]['livedb_longitude'])) {
			    if (!$Common->checkLine($this->all_tracked[$id]['archive_latitude'],$this->all_tracked[$id]['archive_longitude'],$this->all_tracked[$id]['livedb_latitude'],$this->all_tracked[$id]['livedb_longitude'],$line['latitude'],$line['longitude'])) {
				$this->all_tracked[$id]['archive_latitude'] = $line['latitude'];
				$this->all_tracked[$id]['archive_longitude'] = $line['longitude'];
				$this->all_tracked[$id]['putinarchive'] = true;
				
				if ($globalDebug) echo "\n".' ------- Check Country for '.$this->all_tracked[$id]['ident'].' with latitude : '.$line['latitude'].' and longitude : '.$line['longitude'].'.... ';
				$timeelapsed = microtime(true);
				if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				    $Marine = new Marine($this->db);
				    $all_country = $Marine->getCountryFromLatitudeLongitude($line['latitude'],$line['longitude']);
				    if (!empty($all_country)) $this->all_tracked[$id]['over_country'] = $all_country['iso2'];
				    $Marine->db = null;
				    if ($globalDebugTimeElapsed) echo 'Time elapsed for update getCountryFromlatitudeLongitude : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
				}
				$this->tmd = 0;
				if ($globalDebug) echo 'FOUND : '.$this->all_tracked[$id]['over_country'].' ---------------'."\n";
			    }
			}

			if (isset($line['latitude']) && $line['latitude'] != '' && $line['latitude'] != 0 && $line['latitude'] < 91 && $line['latitude'] > -90) {
				if (!isset($this->all_tracked[$id]['archive_latitude'])) $this->all_tracked[$id]['archive_latitude'] = $line['latitude'];
				if (!isset($this->all_tracked[$id]['livedb_latitude']) || abs($this->all_tracked[$id]['livedb_latitude']-$line['latitude']) > $globalCoordMinChange || $this->all_tracked[$id]['format_source'] == 'aprs') {
				    $this->all_tracked[$id]['livedb_latitude'] = $line['latitude'];
				    $dataFound = true;
				    $this->all_tracked[$id]['time_last_coord'] = time();
				}
				$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('latitude' => $line['latitude']));
			}
			if (isset($line['longitude']) && $line['longitude'] != '' && $line['longitude'] != 0 && $line['longitude'] < 360 && $line['longitude'] > -180) {
			    if ($line['longitude'] > 180) $line['longitude'] = $line['longitude'] - 360;
				if (!isset($this->all_tracked[$id]['archive_longitude'])) $this->all_tracked[$id]['archive_longitude'] = $line['longitude'];
				if (!isset($this->all_tracked[$id]['livedb_longitude']) || abs($this->all_tracked[$id]['livedb_longitude']-$line['longitude']) > $globalCoordMinChange || $this->all_tracked[$id]['format_source'] == 'aprs') {
				    $this->all_tracked[$id]['livedb_longitude'] = $line['longitude'];
				    $dataFound = true;
				    $this->all_tracked[$id]['time_last_coord'] = time();
				}
				$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('longitude' => $line['longitude']));
			}

		    } else if ($globalDebug && $timediff > 20) {
			$this->tmd = $this->tmd + 1;
			echo '!!! Too much distance in short time... for '.$this->all_tracked[$id]['ident']."\n";
			echo 'Time : '.$timediff.'s - Distance : '.$Common->distance($line['latitude'],$line['longitude'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],'m')."m -";
			echo 'Speed : '.(($Common->distance($line['latitude'],$line['longitude'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],'m')/$timediff)*3.6)." km/h - ";
			echo 'Lat : '.$line['latitude'].' - long : '.$line['longitude'].' - prev lat : '.$this->all_tracked[$id]['latitude'].' - prev long : '.$this->all_tracked[$id]['longitude']." \n";
		    }
		}
		if (isset($line['last_update']) && $line['last_update'] != '') {
		    if (isset($this->all_tracked[$id]['last_update']) && $this->all_tracked[$id]['last_update'] != $line['last_update']) $dataFound = true;
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('last_update' => $line['last_update']));
		}
		if (isset($line['format_source']) && $line['format_source'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('format_source' => $line['format_source']));
		}
		if (isset($line['source_name']) && $line['source_name'] != '') {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('source_name' => $line['source_name']));
		}
		if (isset($line['noarchive']) && $line['noarchive'] === true) {
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('noarchive' => true));
		}
		
		if (isset($line['heading']) && $line['heading'] != '') {
		    if (is_int($this->all_tracked[$id]['heading']) && abs($this->all_tracked[$id]['heading']-round($line['heading'])) > 10) $this->all_tracked[$id]['putinarchive'] = true;
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('heading' => round($line['heading'])));
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('heading_fromsrc' => true));
		    //$dataFound = true;
  		} elseif (!isset($this->all_tracked[$id]['heading_fromsrc']) && isset($this->all_tracked[$id]['archive_latitude']) && $this->all_tracked[$id]['archive_latitude'] != $this->all_tracked[$id]['latitude'] && isset($this->all_tracked[$id]['archive_longitude']) && $this->all_tracked[$id]['archive_longitude'] != $this->all_tracked[$id]['longitude']) {
  		    $heading = $Common->getHeading($this->all_tracked[$id]['archive_latitude'],$this->all_tracked[$id]['archive_longitude'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude']);
		    $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('heading' => round($heading)));
		    if (abs($this->all_tracked[$id]['heading']-round($heading)) > 10) $this->all_tracked[$id]['putinarchive'] = true;
  		    if ($globalDebug) echo "ø Calculated Heading for ".$this->all_tracked[$id]['ident']." : ".$heading."\n";
  		}
		//if (isset($globalSourcesupdate) && $globalSourcesupdate != '' && isset($this->all_tracked[$id]['lastupdate']) && time()-$this->all_tracked[$id]['lastupdate'] < $globalSourcesupdate) $dataFound = false;



		if ($dataFound === true && (isset($this->all_tracked[$id]['mmsi']) || isset($this->all_tracked[$id]['id']))) {
		    $this->all_tracked[$id]['lastupdate'] = time();
		    if ($this->all_tracked[$id]['addedMarine'] == 0 || (isset($globalVM) && $globalVM)) {
		        if (!isset($globalDistanceIgnore['latitude']) || $this->all_tracked[$id]['longitude'] == ''  || $this->all_tracked[$id]['latitude'] == '' || (isset($globalDistanceIgnore['latitude']) && $Common->distance($this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],$globalDistanceIgnore['latitude'],$globalDistanceIgnore['longitude']) < $globalDistanceIgnore['distance'])) {
			    if (!isset($this->all_tracked[$id]['forcenew']) || $this->all_tracked[$id]['forcenew'] == 0) {
				if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				    if ($globalDebug) echo "Check if vessel is already in DB...";
				    $timeelapsed = microtime(true);
				    $MarineLive = new MarineLive($this->db);
				    if (isset($line['id']) && isset($globalVM) && $globalVM) {
					$Marine = new Marine($this->db);
					$recent_ident = $Marine->checkId($line['id']);
					if ($globalDebugTimeElapsed) echo 'Time elapsed for update checkId : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
					$Marine->db=null;
				    } elseif (isset($line['id'])) {
					$recent_ident = $MarineLive->checkIdRecent($line['id']);
					if ($globalDebugTimeElapsed) echo 'Time elapsed for update checkIdRecent : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
				    } elseif (isset($this->all_tracked[$id]['mmsi']) && $this->all_tracked[$id]['mmsi'] != '') {
					$recent_ident = $MarineLive->checkMMSIRecent($this->all_tracked[$id]['mmsi']);
					if ($globalDebugTimeElapsed) echo 'Time elapsed for update checkIdentRecent : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
				    } elseif (isset($this->all_tracked[$id]['ident']) && $this->all_tracked[$id]['ident'] != '') {
					$recent_ident = $MarineLive->checkIdentRecent($this->all_tracked[$id]['ident']);
					if ($globalDebugTimeElapsed) echo 'Time elapsed for update checkIdentRecent : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
				    } else $recent_ident = '';
				    $MarineLive->db=null;
				    if ($globalDebug && $recent_ident == '') echo " Not in DB.\n";
				    elseif ($globalDebug && $recent_ident != '') echo " Already in DB.\n";
				} else $recent_ident = '';
			    } else {
				$recent_ident = '';
				$this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('forcenew' => 0));
			    }
			    //if there was no vessel with the same callsign within the last hour and go post it into the archive
			    if($recent_ident == "" && (($this->all_tracked[$id]['latitude'] != '' && $this->all_tracked[$id]['longitude'] != '') || (isset($globalVM) && $globalVM)))
			    {
				if ($globalDebug) {
					if ($this->all_tracked[$id]['mmsi'] == '') echo "\o/ Add ".$this->all_tracked[$id]['mmsi']." in archive DB : ";
					else echo "\o/ Add ".$this->all_tracked[$id]['ident']." in archive DB : ";
				}
				//adds the spotter data for the archive
				    $highlight = '';
				    if (!isset($this->all_tracked[$id]['id'])) $this->all_tracked[$id] = array_merge($this->all_tracked[$id],array('id' => $this->all_tracked[$id]['mmsi'].'-'.date('YmdHi')));
				    if (!isset($globalNoImport) || $globalNoImport !== TRUE) {
					if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
					    $timeelapsed = microtime(true);
					    $Marine = new Marine($this->db);
					    if (isset($globalVM) && $globalVM && $this->all_tracked[$id]['race_begin'] != '') {
						$result = $Marine->addMarineData($this->all_tracked[$id]['id'], $this->all_tracked[$id]['ident'], $this->all_tracked[$id]['latitude'], $this->all_tracked[$id]['longitude'], $this->all_tracked[$id]['heading'], $this->all_tracked[$id]['speed'], $this->all_tracked[$id]['race_begin'], $this->all_tracked[$id]['mmsi'], $this->all_tracked[$id]['type'],$this->all_tracked[$id]['typeid'],$this->all_tracked[$id]['imo'],$this->all_tracked[$id]['callsign'],$this->all_tracked[$id]['arrival_code'],$this->all_tracked[$id]['arrival_date'], $this->all_tracked[$id]['status'], $this->all_tracked[$id]['status_id'],$this->all_tracked[$id]['format_source'],$this->all_tracked[$id]['source_name'],$this->all_tracked[$id]['captain_id'],$this->all_tracked[$id]['captain_name'],$this->all_tracked[$id]['race_id'],$this->all_tracked[$id]['race_name'],$this->all_tracked[$id]['distance'],$this->all_tracked[$id]['race_rank'],$this->all_tracked[$id]['race_time']);
					    } else {
						$result = $Marine->addMarineData($this->all_tracked[$id]['id'], $this->all_tracked[$id]['ident'], $this->all_tracked[$id]['latitude'], $this->all_tracked[$id]['longitude'], $this->all_tracked[$id]['heading'], $this->all_tracked[$id]['speed'], $this->all_tracked[$id]['datetime'], $this->all_tracked[$id]['mmsi'], $this->all_tracked[$id]['type'],$this->all_tracked[$id]['typeid'],$this->all_tracked[$id]['imo'],$this->all_tracked[$id]['callsign'],$this->all_tracked[$id]['arrival_code'],$this->all_tracked[$id]['arrival_date'], $this->all_tracked[$id]['status'], $this->all_tracked[$id]['status_id'],$this->all_tracked[$id]['format_source'],$this->all_tracked[$id]['source_name'],$this->all_tracked[$id]['captain_id'],$this->all_tracked[$id]['captain_name'],$this->all_tracked[$id]['race_id'],$this->all_tracked[$id]['race_name'],$this->all_tracked[$id]['distance'],$this->all_tracked[$id]['race_rank'],$this->all_tracked[$id]['race_time']);
					    }
					    $Marine->db = null;
					    if ($globalDebug && isset($result)) echo $result."\n";
					    if ($globalDebugTimeElapsed) echo 'Time elapsed for update addspotterdata : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
					}
				    }
				    if (isset($line['sourcestats']) && $line['sourcestats'] == TRUE && $this->all_tracked[$id]['latitude'] != '' && $this->all_tracked[$id]['longitude'] != '') {
					// Add source stat in DB
					$Stats = new Stats($this->db);
					if (!empty($this->stats)) {
					    if ($globalDebug) echo 'Add source stats : ';
				    	    foreach($this->stats as $date => $data) {
						foreach($data as $source => $sourced) {
					    	    //print_r($sourced);
				    	    	    if (isset($sourced['polar'])) echo $Stats->addStatSource(json_encode($sourced['polar']),$source,'polar_marine',$date);
				    	    	    if (isset($sourced['hist'])) echo $Stats->addStatSource(json_encode($sourced['hist']),$source,'hist_marine',$date);
				    		    if (isset($sourced['msg'])) {
				    			if (time() - $sourced['msg']['date'] > 10) {
				    		    	    $nbmsg = round($sourced['msg']['nb']/(time() - $sourced['msg']['date']));
				    		    	    echo $Stats->addStatSource($nbmsg,$source,'msg_marine',$date);
			    			    	    unset($this->stats[$date][$source]['msg']);
			    				}
			    			    }
			    			}
			    			if ($date != date('Y-m-d')) {
			    			    unset($this->stats[$date]);
			    			}
				    	    }
				    	    if ($globalDebug) echo 'Done'."\n";
					}
					$Stats->db = null;
				    }
				    
				    $this->del();
				//$ignoreImport = false;
				$this->all_tracked[$id]['addedMarine'] = 1;
				//print_r($this->all_tracked[$id]);
				if ($this->last_delete == 0 || time() - $this->last_delete > 1800) {
				    if ($globalDebug) echo "---- Deleting Live Marine data older than 9 hours...";
				    //MarineLive->deleteLiveMarineDataNotUpdated();
				    if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
					$MarineLive = new MarineLive($this->db);
					$MarineLive->deleteLiveMarineData();
					$MarineLive->db=null;
					if ($globalDebug) echo " Done\n";
				    }
				    $this->last_delete = time();
				}
			    } elseif ($recent_ident != '') {
				$this->all_tracked[$id]['id'] = $recent_ident;
				$this->all_tracked[$id]['addedMarine'] = 1;
				if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				    if ((isset($globalDaemon) && !$globalDaemon) || (isset($globalVM) && $globalVM)) {
					$Marine = new Marine($this->db);
					if (isset($globalVM) && $globalVM) {
					    if ($this->all_tracked[$id]['status'] == 'Racing') {
						$Marine->updateLatestMarineData($this->all_tracked[$id]['id'],$this->all_tracked[$id]['ident'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],$this->all_tracked[$id]['speed'],$this->all_tracked[$id]['datetime'],$this->all_tracked[$id]['distance'],$this->all_tracked[$id]['race_rank'],$this->all_tracked[$id]['race_time'],$this->all_tracked[$id]['status'],$this->all_tracked[$id]['race_begin']);
					    } else {
						//$finaldatetime = date('Y-m-d H:i:s',strtotime($this->all_tracked[$id]['race_begin'])+$this->all_tracked[$id]['race_time']);
						$timerace = (strtotime($this->all_tracked[$id]['race_begin'])+$this->all_tracked[$id]['race_time']);
						if ($timerace > time()) $finaldatetime = NULL;
						else $finaldatetime = date('Y-m-d H:i:s',$timerace);

						$Marine->updateLatestMarineData($this->all_tracked[$id]['id'],$this->all_tracked[$id]['ident'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],$this->all_tracked[$id]['speed'],$finaldatetime,$this->all_tracked[$id]['distance'],$this->all_tracked[$id]['race_rank'],$this->all_tracked[$id]['race_time'],$this->all_tracked[$id]['status'],$this->all_tracked[$id]['race_begin']);
					    }
					} else {
					    $Marine->updateLatestMarineData($this->all_tracked[$id]['id'],$this->all_tracked[$id]['ident'],$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],$this->all_tracked[$id]['speed'],$this->all_tracked[$id]['datetime'],$this->all_tracked[$id]['distance'],$this->all_tracked[$id]['race_rank'],$this->all_tracked[$id]['race_time'],$this->all_tracked[$id]['status'],$this->all_tracked[$id]['race_begin']);
					}
					$Marine->db = null;
				    }
				}
				
			    }
			}
		    }
		    //adds the spotter LIVE data
		    if ($globalDebug) {
			echo 'DATA : ident : '.$this->all_tracked[$id]['ident'].' - type : '.$this->all_tracked[$id]['type'].' - Latitude : '.$this->all_tracked[$id]['latitude'].' - Longitude : '.$this->all_tracked[$id]['longitude'].' - Heading : '.$this->all_tracked[$id]['heading'].' - Speed : '.$this->all_tracked[$id]['speed']."\n";
		    }
		    $ignoreImport = false;
		    if ((isset($globalVM) && $globalVM) && $this->all_tracked[$id]['status'] != 'Racing') $ignoreImport = true;
		    if (!$ignoreImport) {
			if (!isset($globalDistanceIgnore['latitude']) || (isset($globalDistanceIgnore['latitude']) && $Common->distance($this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],$globalDistanceIgnore['latitude'],$globalDistanceIgnore['longitude']) < $globalDistanceIgnore['distance'])) {
				if ($globalDebug) echo "\o/ Add ".$this->all_tracked[$id]['ident']." from ".$this->all_tracked[$id]['format_source']." in Live DB : ";
				if (!isset($globalNoImport) || $globalNoImport !== TRUE) {
				    if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
					$timeelapsed = microtime(true);
					$MarineLive = new MarineLive($this->db);
					$result = $MarineLive->addLiveMarineData($this->all_tracked[$id]['id'], $this->all_tracked[$id]['ident'], $this->all_tracked[$id]['latitude'], $this->all_tracked[$id]['longitude'], $this->all_tracked[$id]['heading'], $this->all_tracked[$id]['speed'],$this->all_tracked[$id]['datetime'], $this->all_tracked[$id]['putinarchive'],$this->all_tracked[$id]['mmsi'],$this->all_tracked[$id]['type'],$this->all_tracked[$id]['typeid'],$this->all_tracked[$id]['imo'],$this->all_tracked[$id]['callsign'],$this->all_tracked[$id]['arrival_code'],$this->all_tracked[$id]['arrival_date'],$this->all_tracked[$id]['status'],$this->all_tracked[$id]['status_id'],$this->all_tracked[$id]['noarchive'],$this->all_tracked[$id]['format_source'],$this->all_tracked[$id]['source_name'],$this->all_tracked[$id]['over_country'],$this->all_tracked[$id]['captain_id'],$this->all_tracked[$id]['captain_name'],$this->all_tracked[$id]['race_id'],$this->all_tracked[$id]['race_name'],$this->all_tracked[$id]['distance'],$this->all_tracked[$id]['race_rank'],$this->all_tracked[$id]['race_time']);
					$MarineLive->db = null;
					if ($globalDebug) echo $result."\n";
					if ($globalDebugTimeElapsed) echo 'Time elapsed for update addlivespotterdata : '.round(microtime(true)-$timeelapsed,2).'s'."\n";
				    }
				}
				if (isset($globalServerAPRS) && $globalServerAPRS && $this->all_tracked[$id]['putinarchive']) {
					$APRSMarine->addLiveMarineData($this->all_tracked[$id]['id'], $this->all_tracked[$id]['ident'], $this->all_tracked[$id]['latitude'], $this->all_tracked[$id]['longitude'], $this->all_tracked[$id]['heading'], $this->all_tracked[$id]['speed'],$this->all_tracked[$id]['datetime'], $this->all_tracked[$id]['putinarchive'],$this->all_tracked[$id]['mmsi'],$this->all_tracked[$id]['type'],$this->all_tracked[$id]['typeid'],$this->all_tracked[$id]['imo'],$this->all_tracked[$id]['callsign'],$this->all_tracked[$id]['arrival_code'],$this->all_tracked[$id]['arrival_date'],$this->all_tracked[$id]['status'],$this->all_tracked[$id]['status_id'],$this->all_tracked[$id]['noarchive'],$this->all_tracked[$id]['format_source'],$this->all_tracked[$id]['source_name'],$this->all_tracked[$id]['over_country']);
				}
				$this->all_tracked[$id]['putinarchive'] = false;

				// Put statistics in $this->stats variable
				
				if (isset($line['sourcestats']) && $line['sourcestats'] == TRUE && $line['format_source'] != 'aprs' && $this->all_tracked[$id]['latitude'] != '' && $this->all_tracked[$id]['longitude'] != '') {
					$source = $this->all_tracked[$id]['source_name'];
					if ($source == '') $source = $this->all_tracked[$id]['format_source'];
					if (!isset($this->source_location[$source])) {
						$Location = new Source($this->db);
						$coord = $Location->getLocationInfobySourceName($source);
						if (count($coord) > 0) {
							$latitude = $coord[0]['latitude'];
							$longitude = $coord[0]['longitude'];
						} else {
							$latitude = $globalCenterLatitude;
							$longitude = $globalCenterLongitude;
						}
						$this->source_location[$source] = array('latitude' => $latitude,'longitude' => $longitude);
					} else {
						$latitude = $this->source_location[$source]['latitude'];
						$longitude = $this->source_location[$source]['longitude'];
					}
					$stats_heading = $Common->getHeading($latitude,$longitude,$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude']);
					//$stats_heading = $stats_heading%22.5;
					$stats_heading = round($stats_heading/22.5);
					$stats_distance = $Common->distance($latitude,$longitude,$this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude']);
					$current_date = date('Y-m-d');
					if ($stats_heading == 16) $stats_heading = 0;
					if (!isset($this->stats[$current_date][$source]['polar'][1])) {
						for ($i=0;$i<=15;$i++) {
						    $this->stats[$current_date][$source]['polar'][$i] = 0;
						}
						$this->stats[$current_date][$source]['polar'][$stats_heading] = $stats_distance;
					} else {
						if ($this->stats[$current_date][$source]['polar'][$stats_heading] < $stats_distance) {
							$this->stats[$current_date][$source]['polar'][$stats_heading] = $stats_distance;
						}
					}
					$distance = (round($stats_distance/10)*10);
					//echo '$$$$$$$$$$ DISTANCE : '.$distance.' - '.$source."\n";
					//var_dump($this->stats);
					if (!isset($this->stats[$current_date][$source]['hist'][$distance])) {
						if (isset($this->stats[$current_date][$source]['hist'][0])) {
						    end($this->stats[$current_date][$source]['hist']);
						    $mini = key($this->stats[$current_date][$source]['hist'])+10;
						} else $mini = 0;
						for ($i=$mini;$i<=$distance;$i+=10) {
						    $this->stats[$current_date][$source]['hist'][$i] = 0;
						}
						$this->stats[$current_date][$source]['hist'][$distance] = 1;
					} else {
						$this->stats[$current_date][$source]['hist'][$distance] += 1;
					}
				}
				

				$this->all_tracked[$id]['lastupdate'] = time();
				if ($this->all_tracked[$id]['putinarchive']) $send = true;
			} elseif (isset($this->all_tracked[$id]['latitude']) && isset($globalDistanceIgnore['latitude']) && $globalDebug) echo "!! Too far -> Distance : ".$Common->distance($this->all_tracked[$id]['latitude'],$this->all_tracked[$id]['longitude'],$globalDistanceIgnore['latitude'],$globalDistanceIgnore['longitude'])."\n";
			//$this->del();
			
			
			if ($this->last_delete_hourly == 0 || time() - $this->last_delete_hourly > 900) {
			    if (!isset($globalNoDB) || $globalNoDB !== TRUE) {
				if ($globalDebug) echo "---- Deleting Live Marine data Not updated since 2 hour...";
				$MarineLive = new MarineLive($this->db);
				$MarineLive->deleteLiveMarineDataNotUpdated();
				$MarineLive->db = null;
				//MarineLive->deleteLiveMarineData();
				if ($globalDebug) echo " Done\n";
			    }
			    $this->last_delete_hourly = time();
			}
			
		    }
		    //$ignoreImport = false;
		}
		//if (function_exists('pcntl_fork') && $globalFork) pcntl_signal(SIGCHLD, SIG_IGN);
		if ($send) return $this->all_tracked[$id];
	    }
	}
    }
    
    public function race_add($data) {
        $Marine = new Marine();
        $Marine->addRace($data['id'],$data['name'],$data['creator'],$data['desc'],$data['startdate'],$data['markers']);
        $Marine->db = null;
    }
}
?>
