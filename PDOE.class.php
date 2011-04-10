<?php 

class PDOE extends PDO {
		
	var $driver;

	function __construct($dsn,$user=null,$pass=null,$options=null) {
		parent::__construct($dsn,$user,$pass,$options);
		$this->driver = substr($dsn,0,strpos($dsn,':'));
	}

	function operate($args) {
		extract($args);
		/* potential arguments:
			sql:

			table:
			limit:	
			sort:
			where:	
			cols:

			o:
			m:

			mapWith:
			reduceBy:
			f:
		 */
		$fetchOpts = isset($fetchOpts) ? $fetchOpts : PDO::FETCH_ASSOC;
		if( isset($table) && !isset($sql) ) {
			$cols = isset($cols) ? 
				(is_array($cols) ? implode(', ',$cols) : $cols) :
				'*';

			if(isset($limit)) {
				$limit = "limit $limit";
				$offset = isset($offset) ? $offset :
					(isset($page) ? "offset ".($page*$limit) : null);
			} else {
				$limit = null;
				$offset = null;
			}

			if(isset($sort)) {
				if(is_array($sort)) {
					$orderBy = implode(', ',$sort);
					$orderBy = "order by $orderBy";
				} else if(is_string($sort)) 
					$orderBy = "order by $sort";
				else 
					$orderBy = null;
			} else
				$orderBy = null;
		
			if( isset($where) ) {
				if(is_array($where)) {
					$tmp = $this->array2where($where);
					$where = $tmp['where'];
					$params = $tmp['params'];
				} 
			} else
				$where = null;

			$sql = "select $cols from $table $where $limit $offset $orderBy";
		}
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
			while($row = $sth->fetch($fetchOpts)) {
				$rv = $reduceBy($row,$rv);
			}
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

	function saverec($table,$rec,$identifier=null) {
		$this->_msg("CALL: PDOE::saverec($table,$rec,$identifier)");
		if(is_array($identifier) && (count(identifier) > 0)) { 
			$rv = $this->updaterec($table,$rec,$identifier) ? true: false;
		} 
		else if(($prikey = $this->prikey($table)) && isset($rec[$prikey])) {
			$rv = $this->updaterec($table,$rec,array($prikey=>$rec[$prikey])) ? true : false;
		}
		else {
			$rv = $this->insertrec($table,$rec);
		}
		$this->_msg("RETURN: PDOE::saverec() = $rv");
		return $rv;
	}

	function updaterec($table,$rec,$where=null) {
		$this->_msg("CALL: PDOE::updaterec($table,$rec,$where)");
		// possibly query could be hashed by table and where parameters
		// to an already prepared statement
		$query = array("UPDATE $table SET ");
		$comma = '';
		$values = array();

		foreach($rec as $k => $v) {
			array_push($query,$comma,$k,'=?');
			$comma = ', ';	
			$values[] = $v;
		}

		$prikey = null;
		if(!$where && ($prikey = $this->prikey($table)) && isset($rec[$prikey]))
			$where = array($prikey => $rec[$prikey]);
		$qw = new PDOE_QueryWriter(array(
			'where'=>$where,'table'=>$table,'pdoe'=>$this));

		array_push($query,' ',$qw->where());
		$query = implode(null,$query);
		$this->_msg("QUERY: $query");

		$params = array_merge($values,$qw->params);
		if($sth = $this->prepare($query))
			$result = $sth->execute($params);
		else
			throw new Exception("query wasn't succesfully prepared |$query|");

		$rv = $result ? $sth->rowCount() : false;
		$this->_msg("RETURN: PDOE::updaterec() = $rv");
		return $rv;
	}

	function insertrec($table,$rec) {
		$this->_msg("CALL: PDOE::insertrec($table,$rec)");
		$placeholders = array_fill(0,count($rec),'?');
		$values = array_values($rec);
		if(!array_key_exists(0,$rec) && ($keys = array_keys($rec)) ) {
			$query = implode(null,array(
				'INSERT INTO ',$table,' (',
				implode(', ',$keys),') VALUES (',
				implode(', ',$placeholders),') '
			));
		} else {
			$query = implode(null,array(
				'INSERT INTO ',$table,' VALUES (',
				implode(', ',$placeholders),') '
			));
		}
		if(!$sth = $this->prepare($query))
			throw new Exception("failure preparing query |$query| - no statement handle");
		$result = $sth->execute($values);
		$rv = $result ? $this->lastInsertId() : false;
		$this->_msg("RETURN: PDOE::insertrec() = $rv");
		return $rv;
	}	

	function fetch($table,$where=null,$column=null) {
		$this->_msg("CALL: PDOE::fetch($table,$where)");
		// possibly query could be hashed by table and where parameters
		// to an already prepared statement
		$qw = new PDOE_QueryWriter(array(
			'where'=> $where,'table'=>$table,'type'=>'fetch','pdoe'=>$this));
		$qw->where();
		$query = "SELECT * from $table {$qw->whereSQL} LIMIT 1";
		//echo "\n [QUERY: $query]";
		$this->_msg("QUERY: $query");
		if( ($sth = $this->prepare($query)) 
		 && !($sth->execute($qw->params) === false) ) {
		 	$rv = $sth->fetch(PDO::FETCH_ASSOC);
		} else 
		 	$rv = false;
		$this->_msg("RETURN: PDOE::fetch() = $rv");
		return $rv;
	}

	function deleterec($table,$where=null) {
		$qw = new PDOE_QueryWriter(array(
			'table'=>$table,'where'=>$where,'pdoe'=>$this));
		$qw->where();
		$query = "DELETE FROM $table {$qw->whereSQL}";	
		$this->_msg("QUERY: $query");
		if($sth = $this->prepare($query))
			$rv = $sth->execute($qw->params);
		else
			throw new Exception("couldn't prepare query |$query|");
		return $rv;
	}

	function prikey($table,$forcecheck=false) {
		global $_prikey_cache;
		if( !$forcecheck && isset($_prikey_cache[$table]) ) {
			$rv = $_prikey_cache[$table];
		} else if($this->driver == 'sqlite') {
			$rv = $this->_prikey_sqlite($table);
		} else
			$rv = $this->_prikey_mysql($table);
		return $rv;
	}

	function _prikey_sqlite($table,$forcecheck=false) {
		$sth = $this->query("pragma table_info ($table)");
		$rv = null;
		if($sth)
		while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
			if($row['pk'] == 1) {
				$rv = $row['name'];
				$_prikey_cache[$table] = $rv;
				break;
			}
		}
		return $rv;
	}

	function _prikey_mysql($table) {
		$sth = $this->query("describe $table");
		$rv = null;
		if($sth)
		while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
			if($row['Key'] == 'PRI') {
				$rv = $row['Field'];
				$_prikey_cache[$table] = $rv;
				break;
			}
		}
		return $rv;
	}

	function _msg($m) {
		$this->_msgs[] = $m;
		//MSGS::add($m);
	}

	function QueryCollector() {
		return new PDOE_QC();
	}

	function QC() {
		return new PDOE_QC();
	}
}

class PDOE_QueryWriter {

	var $where;
	var $table;
	var $type;
	var $pdoe;

	var $prikey; 

	var $whereSQL;
	var $params;
	
	function __construct($args=null) { 
		$this->pdoe = isset($args['pdoe']) ? $args['pdoe'] : null;
		$this->reset($args);	
	}
	
	function reset($args) {
		if(!is_array($args)) 
			return false;
		else {
			foreach(array('where','table','type','prikey') as $prop)
				$this->$prop = isset($args[$prop]) ? $args[$prop] : null;
			$this->whereStr = null;
			$this->params = array();
			return true;
		}
	}

	function where($args=null) {
		$this->reset($args);
		if(is_array($this->where)) {
			$tmp = $this->array2where($this->where);
			$this->whereSQL = $tmp['whereSQL'];
			$this->params = $tmp['values'];
		} else if(strpos($this->where,':') === 0) {
			$this->whereSQL = 'WHERE '.substr($this->where,1);
		} else if(($this->prikey 
			|| ($this->prikey = $this->pdoe->prikey($this->table) ) 
			&& !empty($this->where) )) {
			$this->whereSQL = "WHERE $this->prikey=?";
			$this->params = array($this->where);
		} else if($this->type == 'fetch' || $this->type == 'select') {
			$this->whereSQL = "ORDER BY $this->prikey DESC";
		} else 
			throw new Exception("queries of this type need targets. ".var_export($this,true));
		return $this->whereSQL;
	}

	function array2where($a) {
		$sql = array();
		$values = array();
		foreach($a as $k => $v) {
			if(is_array($v)) {
				array_merge($values,$v);
				array_push($sql,
					implode(null,array('(',$k,' IN (',implode(',',$v),'))'))
				);
			} else {
				array_push($values,$v);
				array_push($sql,implode(null,array('(',$k,'=?)')));
			}
		}
		if(count($sql) > 0) {
			$sql = implode(' AND ',$sql);
			$sql = "WHERE $sql";
		} else
			$sql = '';
		return array('whereSQL' => $sql, 'values' => $values);
	}

}


class PDOE_QC {

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
		return "Object (PDOE_QC) (a *Q*uery *C*ollector)";
	}
}

?>
