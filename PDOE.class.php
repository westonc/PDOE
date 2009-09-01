<?php

//include_once("MSGS.class.php");

class PDOE extends PDO {

	/* static $qcache = array();

	function prepare($queryStr) {
		if(isset(self::$qcache[$queryStr]))
			return self::$qcache[$queryStr];
		else
			return parent::prepare($queryStr);
	} */

	function operate($args) {
		extract($args);
		$fetchOpts = isset($fetchOpts) ? $fetchOpts : null;
		if( isset($sql) ) 
			$sth = $this->prepare($sql);

		if(!isset($sth)) {
			throw new Exception("No statement to operate over");
		} else if( !($sth instanceof PDOStatement) ) {
			throw new Exception("Statement handle not PDOStatement");
		} else if(! $sth->execute(isset($params) ? $params : null) ) {
			throw new Exception('Error executing statement: '.$sth->errorInfo());
		} else if( isset($mapWith) ) {
			while($row = $sth->fetch($fetchOpts))
				$rv[] = $mapWith($row);
		} else if( isset($reduceBy) ) {
			$rv = isset($rInit) ? $rInit : null;
			while($row = $sth->fetch($fetchOpts))
				$rv = $reduceBy($row,$rv);
		} else if( isset($f) ) {
 			while($row = $sth->fetch($fetchOpts)) 
				$f($row);		
			$rv = $sth->rowCount();
		} else if(isset($o) && isset($m)) {
			while ($row = $sth->fetch($fetchOpts))
				$o->$m($row);	
			$rv = $sth->rowCount();
		} else {
			throw new Exception('No function passed for operation.');
		} 

		return $rv;
	}

	function datef($d=null,$timeInFormat = false) {
		$this->_msg("CALL: datef($d,$timeInFormat)");
		$t = is_numeric($d) ? $d : strtotime($d);
		$format = $timeInFormat ? 'Y-m-d H:i:s' : 'Y-m-d';
		$this->_msg("t: $t format: $format");
		$rv = Date($format,$t);
		$this->_msg("RETURN: datef() = $rv");
		return $rv;
	}

	function saverec($rec,$table,$identifier=null) {
		$this->_msg("CALL: PDOE::saverec($rec,$table,$identifier)");
		if(is_array($identifier) && (count(identifier) > 0)) { 
			$rv = $this->updaterec($rec,$table,$identifier);
		} 
		else if(($prikey = $this->prikey($table)) && isset($rec[$prikey])) {
			$rv = $this->updaterec($rec,$table,array($prikey=>$rec[$prikey]));
		}
		else {
			$rv = $this->insertrec($rec,$table);
		}
		$this->_msg("RETURN: PDOE::saverec($rec,$table,$identifier) = $rv");
		return $rv;
	}

	function updaterec($rec,$table,$where=null) {
		$this->_msg("CALL: PDOE::updaterec($rec,$table,$where)");
		// possibly query could be hashed by table and where parameters
		// to an already prepared statement
		$query = array(
			"UPDATE $table SET ",
		);
		$comma = '';
		$values = array();
		foreach($rec as $k => $v) {
			array_push($query,$comma,$k,'=?');
			$comma = ', ';	
			$values[] = $v;
		}
		$query[] = 	' WHERE ';
		if( is_array($where) && (($whereTermCount = count($where)) > 0) )  {
			$this->_msg("Update via specific where parameters");
			$comma = ' AND ';
			foreach($where as $k => $v) {
				array_push($query,$comma,$k,'=?');
				$values[] = $v;
			}
		} else if($prikey = PDOE::prikey($table)) {
			$this->_msg("update via implicity primary key");
			if(isset($rec[$prikey])) {
				array_push($query,' ',$prikey,'=',$rec[$prikey]);
			} else {
				$this->_msg("except we can't find the primary key. Hmm.");
				$rv = $result ? $rec[$prikey] : $result;
			}
		}
		$query = implode(null,$query);
		$this->_msg("QUERY: $query");
		$sth = $this->prepare($query);
		$result = $sth->execute($values);
		$rv = $result ? $sth->rowCount() : false;
		$this->_msg("RETURN: PDOE::updaterec() = $rv");
		return $rv;
	}

	function insertrec($rec,$table) {
		$this->_msg("CALL: PDOE::insertrec($rec,$table)");
		$keys = array_keys($rec);
		$placeholders = array_fill(0,count($rec),'?');
		$values = array_values($rec);
		// possibly query could be hashed by table 
		// to an already prepared statement
		$query = implode(null,array(
			'INSERT INTO ',$table,' (',
			implode(', ',$keys),') VALUES (',
			implode(', ',$placeholders),') '
		));
		$sth = $this->prepare($query);	
		$result = $sth->execute($values);
		$rv = $result ? $this->lastInsertId() : false;
		$this->_msg("RETURN: PDOE::insertrec() = $rv");
		return $rv;
	}	

	function prikey($table,$forcecheck=false) {
		global $_prikey_cache;
		if(!$forcecheck && isset($_prikey_cache[$table])) {
			$rv = $_prikey_cache[$table];
		} else {
			$sth = $this->prepare("describe $table");
			$rv = null;
			while($row = mysql_fetch_array($result,MYSQL_BOTH)) {
				if($row['Key'] == 'PRI') {
					$rv = $row['Field'];
					$_prikey_cache[$table] = $rv;
					break;
				}
			}
		}
		return $rv;
	}

	function _msg($m) {
		//MSGS::add($m);
	}

	function QueryCollector() {
		return new PDOE_QueryCollector();
	}
	
}

class PDOE_QueryCollector {

	var $rows;

	function __construct() {
		$this->clear();
	}

	function clear() {
		$this->rows = array();
	}

	function collect($row) {
		if(is_array($row))
			$this->rows[] = $row;
	}

	function echoEach($row) {
		echo "\n<br/>";
		print_r($row);
	}

	function echoAll() {
		foreach($this->rows as $row) 
			$this->echoEach($row);
	}

	function get() {
		return $this->rows;
	}

	function __toString() {
		return "Object (PDOE_QCollector)";
	}
}

?>
