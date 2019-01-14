<?
/**
 * Created by DH Song.
 * Create Date : 2017-02
 * Update Date : 2017-06-13
 * Version 0.2  : checkbox hidden + array_filter, replace mode, mysqli + connecter parameter, 
 * Version 0.3  : [2017-07-19] insert update/delete log add function modifyLOG(), Use only mysqli
 * Version 0.4  : [2017-08-09] add function TableInfo(), rename function inputHidden()
 */

class Forming
{
	private $InjectionPattern = '/(and|or).*(union|select|insert|update|delete|from|where|limit|create|drop).*/i';
	private $connect_db;
	private $logTable = "";										// 수정/ 삭제시 기록을 남기는 테이블, 기본 로그테이블 초기세팅 가능
	private $specialData = array("NULL","NOW()");	// 데이터에 들어갈 함수 및 특수 데이터 정의 : 이곳에 정의된 문자열은 따옴표(')를 붙이지 않음

	public $insert_id;			// 최종 입력 id

	function Forming($connect_db)
	{
		$this->connect_db = $connect_db;
	}
	function setLogTable($tableName)		// 로그테이블 지정 및 변경
	{
		$this->logTable = $tableName;
	}
	function addSpecialData($SPData)		// 특수데이터 추가
	{
		array_push($this->specialData,$SPData);
	}
	//==================================================		GNU보드 SQL 인젝션 방지 함수 시작		==================================================
	// multi-dimensional array에 사용자지정 함수적용
	/*function array_map_deep($fn, $array)
	{
		if(is_array($array)) {
			foreach($array as $key => $value) {
				if(is_array($value)) {
					$array[$key] = array_map_deep($fn, $value);
				} else {
					$array[$key] = call_user_func($fn, $value);
				}
			}
		} else {
			$array = call_user_func($fn, $array);
		}

		return $array;
	}*/
	// SQL Injection 대응 문자열 필터링
	function sql_escape_string($str)
	{
		$replace = "";
		if($this->InjectionPattern) $str = preg_replace($this->InjectionPattern, $replace, $str);
		return call_user_func('addslashes', $str);
	}
	//==================================================		GNU보드 SQL 인젝션 방지 함수 끝		==================================================


	//==================================================		개발중 함수 시작		==================================================
	/*function SelectBox($name,$arroption,$defualt)
	{
		$echo .= "<select name='$name'>";
		foreach($arroption as $key => $value)
		{
			
			if($defualt == $key || $defualt == $value ) $echo .= "<option value='$key' selected>$value</option>";
			else $echo .= "<option value='$key'>$value</option>";
		}
		$echo .= "</select>";

		return $echo;

	}*/
	//table^column^option
	//==================================================		개발중 함수 끝		==================================================


	//==================================================		SQL 처리 함수 시작		==================================================
	
	function sql_query($query)	// 쿼리 함수
	{
		$query = trim($query);		// Blind SQL Injection 취약점 해결
		$query = preg_replace("#^select.*from.*union.*#i", "select 1", $query);	// union의 사용을 허락하지 않습니다.
		$query = preg_replace("#^select.*from.*where.*`?information_schema`?.*#i", "select 1", $query);		// `information_schema` DB로의 접근을 허락하지 않습니다.
		//echo "$query";
		//return call_user_func('mysqli_query', $this->connect_db, $query);
		return mysqli_query($this->connect_db, $query);
	}
	/*function ($result)
	{
		return call_user_func($this->DB.'_fetch_array', $result);
	}*/

	function setInsert($table, $input)	//insert 함수
	{
		//배열의 key값과 val값을 이용하여 쿼리를 만든다.
		$i = 0;
		while (list($key,$val)=each($input)) {
			if(is_array($val)) {
				$val = $val[0];
				$valMark = "";
			}
			else if(in_array($val, $this->specialData)) $valMark = "";		// 특수 데이터인 경우 따음표 붙이지 않음
			else $valMark = "'";
			if($i == 0) {
				$inputKey = $key;
				$inputVal = $valMark.$val.$valMark;
			} else {
				$inputKey .= ", $key";
				$inputVal .= ", ".$valMark.$val.$valMark;
			}
			$i++;
		}
		$query = "insert into $table ($inputKey) values ($inputVal)";
		//echo "$query";
		$result = $this->sql_query($query);
		return $result;
	}
	function setUpdate($table, $input, $query_option="", $type=0, $debug="")
	{
		if($type == 0) {
			$i = 0;
			while (list($key,$val)=each($input)) {
				if(is_array($val)) {
					$val = $val[0];
					$valMark = "";
				}
				else if(in_array($val, $this->specialData)) $valMark = "";		// 특수 데이터인 경우 따음표 붙이지 않음
				else $valMark = "'";
				if($i == 0)  $inputVal = "$key = ".$valMark.$val.$valMark;
				else $inputVal .= ", $key = ".$valMark.$val.$valMark;
				$i++;
			}
		}
		else $inputVal = $input;
		$query = "update $table set $inputVal $query_option";
		//echo $query ;
		
		if($debug == "1") { echo $query."<br/><br/>";exit;}
		elseif($debug == "")	$result = $this->sql_query($query);
		return $result;
	}
	function setDelete($table, $query_option="", $debug="") 
	{
		$query = "delete from $table $query_option";

		if($debug == "1") {
			echo $query."<br/><br/>";
			exit;
		} elseif($debug == "") {
			$result = $this->sql_query($query);
		}
		else if ($debug == "safe") {

			$result = $this->sql_query($query);
		}
		return $result;
	}

	function TableInfo($table)
	{
		$query = "show full columns from $table";
		$res = $this->sql_query($query);
		while($row = mysqli_fetch_array($res)) {
			$table_list[$row['Field']] = $row;
		}
		return $table_list;
	}



	function UDIProcess($mode, $table, $REQUEST, $arrException = array('idx'), $where="")		// mode : update,insert,delete / arrException : update,insert 제외컬럼 / where 문
	{
		//$REQUEST = $this->array_map_deep($this->DB."_real_escape_string",  $REQUEST);	// 인젝션 제거
		$query = "show columns from $table";
		$res = $this->sql_query($query);
		while($row = mysqli_fetch_array($res)) {
			$Field = $row['Field'];
			if(!in_array($Field,$arrException) )
			{
				if(is_array($REQUEST[$Field])) $input[$Field] = implode(",",array_filter($REQUEST[$Field]));		// 배열인 경우 [,] 구분자로 문자열 붙이기
				else if($mode == "replace" && !isset($REQUEST[$Field])) $input[$Field] = "NULL";		// 모드가 [replace] 인 경우 disable 데이터 NULL 처리
				//else if(isset($REQUEST[$Field])) $input[$Field] = $REQUEST[$Field];					// 값 세팅 된경우 disable인경우 제외
				//else if(! $REQUEST[$Field] === NULL ) $input[$Field] = $REQUEST[$Field];			// 널인경우 정확히 널인경우 제외 (위와 동일?)
				else if(!empty($REQUEST[$Field])) $input[$Field] = $REQUEST[$Field];				// 값이 빈 경우 공백,0 인경우 제외

			}
		}
		if(strstr(strtolower($where),"where")<0) $where = "where ".$where;
		switch($mode)
		{
			case "update":			// 저장된 값이 있는경우만 UPDATE
			case "replace":			// 해당 컬럼을 모두 업데이트 공백 및 NULL 포함 처리
				$this->modifyLOG($table, $this->GetData($table,$where),$input);			// 과거데이터 변경 로그기록
				$exeResult = $this->setUpdate($table,$input,$where);
				break;

			case "insert":
				$exeResult = $this->setInsert($table,$input);
				$this->insert_id = mysqli_insert_id($this->connect_db);
				break;

			case "copy":		// 해당 데이터를 새로운 row로 복사해서 추가
				// 개발중
				break;

			case "delete":
				$this->modifyLOG($table, $this->GetData($table,$where));			// 과거데이터 로그기록
				$exeResult = $this->setDelete($table,$where);
				break;
		}
		if(!$exeResult) {return false;}
		return true;
	}
	function TBList($table,$where_order="",$limit="", $fetchColumn="*")
	{
		$table_list = array();
		$query = "select $fetchColumn from $table $where_order $limit";
		//echo "$query\n";
		$res = $this->sql_query($query);
		while($row = mysqli_fetch_array($res)) {
			$table_list[] = $row;
		}
		return $table_list;
	}
	function GetData($table,$where="",$fetchColumn="*")
	{
		$ret = $this->TBList($table,$where,$limit="limit 1", $fetchColumn);
		$ret = $ret[0];
		if(count($ret) <= 2) return $ret[0];	// 하나의 항목인 경우 값을 전달
		else return $ret;
	}
	function GetCount($table,$where="")
	{
		return $this->GetData($table,$where,"count(*)");
	}
	
	//개발중
	function modifyLOG($tableName, $oldData , $newData = "")
    {
		if(!$this->logTable) return true;		// 로그테이블이 설정되지 않으면 종료
        $exceptionField = array('seq');		// 변경로그 제외항목 설정
		$mode = (!empty($newData))?"update":"delete";
		
		// 테이블 컬럼 확인
        $sql = "SHOW FULL COLUMNS FROM $tableName";
		$result = $this->sql_query($sql);
        while($fieldAttr = mysqli_fetch_array($result))
		{
            $Field = $fieldAttr['Field'];
            if(!in_array($Field,$exceptionField))
            {
                if($mode == "update" && $oldData[$Field] != $newData[$Field]) $ChangeData[$Field] = $oldData[$Field].">>".$newData[$Field];
                else if($strMode == "delete") $ChangeData[$Field] = $oldData[$Field];
            }
		}
		if(empty($ChangeData))  return true;		// 변경 데이터가 없는 경우 종료

        $input['tablename'] = $tableName;
        $input['mode'] = $mode;
        $input['datalog'] = addslashes(json_encode($ChangeData));
        $input['regdate'] = date('Y-m-d H:i:s');
        return $this->UDIProcess("insert", $this->logTable, $input);
		/*
		CREATE TABLE modifyLOG (
			seq int NOT NULL AUTO_INCREMENT COMMENT'순번',
			PRIMARY KEY (seq)
		);
		ALTER TABLE modifyLOG ADD tablename	varchar(100)	COMMENT '테이블이름';
		ALTER TABLE modifyLOG ADD mode			varchar(10)	COMMENT '모드:update/delete';
		ALTER TABLE modifyLOG ADD datalog		mediumtext		COMMENT '변경데이터';
		ALTER TABLE modifyLOG ADD regdate		datetime			COMMENT '변경일시';
		*/
    }
	//==================================================		SQL 처리 함수 끝		==================================================


	///////////////////////////////////////////////////		이하 Input 함수 부분		///////////////////////////////////////////////////
	function inputText($title,$name="",$value="",$option="")
	{
		$echo = "<input type='text' placeholder='$title' name='$name' id='$name'  $option value='$value'>";
		return $echo;
	}
	function inputHidden($name,$value="")
	{
		return "<input type='hidden' name='$name' id='$name' value='$value'>";
	}
	function inputSelect($name,$array = array(""),$value="",$option="")
	{
		$echo = "<select name='$name' id='$name' $option>";
		foreach($array as $array_key => $array_value)
		{
			if($array_key == $value) $echo .="<option value='$array_key' selected>".$array_value."</option>";
			else $echo .="<option value='$array_key'>".$array_value."</option>";
		}
		$echo .="</select>";
		return $echo;
	}
	function inputRadio($name,$array=array(),$value="",$option="",$dv="_")
	{
		foreach($array as $array_key => $array_value)
		{
			$echo .= "<div class='radio-custom radio-inline' groupdiv='$name'>";
			$checked = ($array_key == $value)?"checked":"";
			$echo .= "<input type='radio' group='$name' name='$name' id='$name"."$dv$array_key' $option value='$array_key' $checked><label for='$name"."$dv$array_key'>$array_value</label>\n";
			$echo .= "</div>";
		}
		return $echo;
	}
	function inputCheckbox($name,$array=array(),$value="",$option="",$dv="_")
	{
		$value_array = explode(",",$value);
		$input_name = "$name"."[]";	
		// 체크박스의 경우 체크 해재한 경우 GET,POST,REQUEST 자체가 전달되지 않아 UPDATE가 되지 않음
		// => hidden 인풋을 추가하여 배열로 전달하면 해제의 경우에도 빈값을 전달
		$echo = "<input type='hidden' group='$name' name='$input_name' id='$input_id'>";
	
		foreach($array as $array_key => $array_value)
		{
			$input_id = "$name"."$dv$array_key";
			$checked = (in_array($array_key,$value_array))?"checked":"";
			$echo .= "<div class='checkbox-custom checkbox-inline' groupdiv='$name'>";			
			$echo .= "<input type='checkbox' group='$name' name='$input_name' id='$input_id' $option value='$array_key' $checked>";
			$echo .= "<label for='$input_id'>$array_value</label></div>\n";
		}
		return $echo;
	}

	function inputTextarea($title,$name,$value,$option="")
	{
		$echo = "<textarea name='$name' placeholder='$title' $option>$value</textarea>";
		return $echo;
	}
	///////////////////////////////////////////////////		Input 함수 부분 끝		///////////////////////////////////////////////////

}
?>