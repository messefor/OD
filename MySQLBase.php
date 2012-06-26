<?php

/**
* OD_LogicalExceptionクラス(カスタム例外クラス)
*/
require_once('OD/LogicalException.php');

/**
* OD_MySQLExceptionクラス(カスタム例外クラス)
*/
require_once('OD/MySQLException.php');

/**
* OD_FieldInfoMySQLクラス
*/
require_once('OD/FieldInfoMySQL.php');

/**
* getInfo系のメソッド(getHostInfo、getServerInfoなど)が失敗したとき例外を投げるか
*/
define("GET_EXP_FLAG", false);


/**
* MySQL使用時のカスタム・ベースクラス
*
* MySQLサーバとのコネクションの作成、コネクション情報の取得を提供します。MySQL関数を使っています。(MySQLi関数やADODB,PDOでなく)
* @author ODA Daisuke
* @copyright 2012 althi.Inc
* @version
* @uses OD_MySQLException
* @uses OD_LogicalException
* @link http://www.php.net/manual/ja/ref.mysql.php MySQL関数
*/

class OD_MySQLBase
{
	//-----------------------------------------------------
	// プロパティ
	//-----------------------------------------------------

	/**
	* MySQLのコネクション・リンクID
	* @var boolean|resource
	*/
	protected $link = false;

	/**
	* データベースサーバーのホスト名
	* @var string
	*/
	protected $svrName = null;

	/**
	* データベース名
	* 選択(select)されたDB名を保持
	* @var resource
	*/
	protected $dbName = null;

	/**
	* データベース・ユーザー名
	* @var string
	*/
	protected $userName = null;

	/**
	* SELECT文を実行した結果のレコード数
	* @var int
	*/
	protected $recordCount = null;

	/**
	* SELECT文以外を実行して影響を受けたレコード数
	* @var int
	*/
	protected $affectedRows = null;

	/**
	* データベース情報取得用関数(MySQL情報関数)
	* @var string
	* @internal
	*/
	private $CMD_GET_HOST_USER = 'USER()';
	private $CMD_GET_DATABASE = 'DATABASE()';

	//-----------------------------------------------------
	// 定数
	//-----------------------------------------------------
	
	/**
	* MySQLのエンコーディング
	*/
	const MYSQL_CHARSET_UTF8 = 'utf8';
	const MYSQL_CHARSET_SJIS = 'sjis';


	//-------------------------------------------------------
	// メソッド
	//-------------------------------------------------------

	/**
	* コンストラクタ
	*
	* データベースに接続します。
	* [注意] 既にコネクションが確立した状態で、再度openメソッドを実行して失敗した場合、以前のコネクションも自動的に切断されるみたいです。(mysql_connectの仕様と思われる。)
	* @param string MySQLサーバのホスト名
	* @param string データベースのユーザー名
	* @param string データベースのパスワード
	* @throws OD_MySQLException -接続出来ない場合に例外をスローします。
	* @throws OD_LogicalException -接続出来ない場合に例外をスローします。
	* @see OD_MySQLException, OD_LogicalException
	*/
	function __construct($server, $username, $password, $client_encoding = self::MYSQL_CHARSET_UTF8)
	{
		$ErrMsg = "インスタンスの作成に失敗しました。データベースに接続できません。";

		// 引数がセットされているか接続する前にチェックする → 負荷をかけないため
		if (isset($server) && isset($username) && isset($password)) {
			$this->link = mysql_connect($server, $username, $password);
			if ($this->link !== false) {
				$this->userName = $username;
				$this->svrName = $server;

			} else {
				throw new OD_MySQLException($ErrMsg . mysql_error(), mysql_errno());
			}
		} else {
			throw new OD_LogicalException($ErrMsg . "コンストラクタの引数の一部がセットされていません。", OD_LogicalException::HAS_UNSETED_PARAM);
		}

		// 文字エンコーディング設定
		if (!mysql_set_charset($client_encoding)){
			throw new OD_MySQLException("クライアントのキャラクタセット設定に失敗しました。" . mysql_error(), mysql_errno());
		}

	}

	/**
	* データベースを選択する
	* @param string	選択するデータベース名
	* @throws OD_MySQLException データベースを選択出来ない場合に例外をスローします。
	* @see OD_MySQLException
	*/
	public function selectDB($name_of_db)
	{
		$errMsg = "データベースを選択できません。";
		if (isset($name_of_db)) {
			if ($this->isConnected()) {
				if (mysql_select_db($name_of_db, $this->link)) {
					$this->dbName = $name_of_db;
				} else {
					throw new OD_MySQLException($errMsg . mysql_error(), mysql_errno());
				}
			} else {
				throw new OD_MySQLException($errMsg . "データベースへの接続が確立されてません。", OD_MySQLException::DB_NOT_CONNECTED);
			}
		} else {
			throw new OD_LogicalException($errMsg . "引数(データベース名)がセットされていません。", OD_LogicalException::HAS_UNSETED_PARAM);
		}
	}

	/**
	* プロパティを初期化する
	*/
	protected function resetPropaty()
	{
		$this->link = false;
		$this->dbName = null;
		$this->svrName = null;
		$this->userName = null;
	}

	//-------------------------------------------------------
	// SQL実行系メソッド
	//-------------------------------------------------------

	/**
	* SQLを実行する
	* @param string 実行するSQL文
	* @return mixed SELECT、SHOW文では結果をarrayで、それ以外はtrueを返します。
	* @throws OD_MySQLException -SQL実行失敗時に例外をスローします。
	* @see OD_MySQLException
	* @uses conv_result_to_Array()
	* @todo クォートや末尾のセミコロンの処理について対処を考える。
	*/
	public function query($strSQL)
	{
		// プロパテティの初期化
		$this->recordCount = null;
		$this->affectedRows = null;
		$this->resultArray = array();

		$errMsg = "SQLの実行に失敗しました。";
		if ($this->isConnected()) {
			$rs = mysql_query($strSQL, $this->link);
			if (is_resource($rs)) {
				if (get_resource_type($rs) === "mysql result") {
					// SELECT文 成功
					return $this->conv_result_to_Array($rs);
				}
			} else {
				if ($rs === true) {
					// アクションSQL(SELECT以外)成功
					$this->affectedRows = mysql_affected_rows();
					return true;
				} elseif ($rs === false) {
					// SQL 失敗
					throw new OD_MySQLException($errMsg . mysql_error(), mysql_errno());
				}
			}
		} else {
			throw new OD_MySQLException($errMsg . "データベースとの接続が確立されてません。", OD_MySQLException::DB_NOT_CONNECTED);
		}
	}

	/**
	* リソース型の結果を連想配列へ変換する
	* @param resource mysql_query関数で得られたresource型(mysql result)。SELECT文の結果。
	* @return array 変換した連想配列を返します。レコード数が0の場合は空の配列を返します。
	* @throws OD_MySQLException -コンバートに失敗した場合に例外をスローします。
	* @throws OD_LogicalException -コンバートに失敗した場合に例外をスローします。
	* @see OD_MySQLException, OD_LogicalException
	*/
	private function conv_result_to_Array($rs) 
	{
		$errMsg = "リソースから配列へのコンバートに失敗しました。";
		$retArray = array(); //　戻りの配列
		if (is_resource($rs)){
			if (mysql_num_rows($rs) !== false) {
				$this->recordCount = mysql_num_rows($rs);
				if ($this->recordCount > 0) {
					$rowCnt = 0;
					while ($rcdArray = mysql_fetch_array($rs, MYSQL_ASSOC)) {
						foreach ($rcdArray as $key => $nowValue) {
							//$retArray[$rowCnt][$key] = $nowValue;
							$retArray[$key][$rowCnt] = $nowValue;
						}
						$rowCnt++;
					}
				}
				return $retArray;
			} else {
				throw OD_MySQLException($errMsg . "レコード数を取得出来ません。" . mysql_error(), mysql_errno());
			}

		} else {
			throw OD_LogicalException($errMsg . "変換対象がリソース型ではありません。", OD_LogicalException::UNEXPECTED_PARAM_TYPE );
		}
	}

	/**
	* 問い合わせ結果のレコード数を取得する
	* @return	int	レコード数をint型で返します。
	*/
	public function getRecordCount()
	{
		return $this->recordCount;
	}

	/**
	* アクションSQLの実行で影響を受けたレコード数を取得する
	* @return	int	レコード数をint型で返します。
	*/
	public function getAffectedRows()
	{
		return $this->affectedRows;
	}

	//-------------------------------------------------------
	// 情報(サーバ、プロトコル、クライアント、ホスト）取得メソッド
	//-------------------------------------------------------

	/**
	* MySQLサーバの情報を取得する
	* @return string MySQL サーバのバージョン返します。
	* @throws OD_MySQLException 情報が取得出来ない場合に例外をスローします。
	* @see OD_MySQLException
	* @link http://www.php.net/manual/ja/function.mysql-get-server-info.php mysql_get_server_info()
	*/
	public function getServerInfo()
	{
		$errMsg = "サーバ情報を取得できません。";
		if ($this->isConnected()) {
			$serverInfo = mysql_get_server_info($this->link);
			if ($serverInfo === false) {
				if (GET_EXP_FLAG) { // 例外を投げるか
					throw new OD_MySQLException($errMsg . mysql_error(), mysql_errno());
				}
			} else {
				return $serverInfo;
			}
		} else {
			if (GET_EXP_FLAG) { //例外を投げるか
				throw new OD_MySQLException($errMsg . "データベースへの接続が確立されてません。", OD_MySQLException::DB_NOT_CONNECTED);
			}
		}
	}

	/**
	* MySQLホスト情報を取得する
	* @return string 使用されているMySQL接続の型を表す文字列を返します。
	* @link http://www.php.net/manual/ja/function.mysql-get-host-info.php mysql_get_host_info()
	*/
	//* @throws OD_MySQLException 情報が取得出来ない場合に例外をスローします。
	//* @see OD_MySQLException
	public function getHostInfo()
	{
		$errMsg = "ホスト情報を取得できません。";
		if ($this->isConnected()) {
			$hostInfo = mysql_get_host_info($this->link);
			if ($hostInfo === false) {
				if (GET_EXP_FLAG) { // 例外を投げるか
					throw new Exception(mysql_error(), mysql_errno());
				}
			} else {
				return $hostInfo;
			}
			
		} else {
			if (GET_EXP_FLAG) { // 例外を投げるか
				throw new OD_MySQLException($errMsg . "データベースへの接続が確立されていません。", OD_MySQLException::DB_NOT_CONNECTED);
			}
		}
	}

	/**
	* MySQLプロトコル情報を取得する
	* @return string 成功した場合に MySQL プロトコルバージョン
	* @link http://www.php.net/manual/ja/function.mysql-get-proto-info.php mysql_get_proto_info()
	*/
	//* @throws OD_MySQLException 情報が取得出来ない場合に例外をスローします。
	//* @see OD_MySQLException
	public function getProtoInfo()
	{
		$errMsg = "プロトコル情報を取得できません。";
		if ($this->isConnected()) {
			$protoInfo = mysql_get_proto_info($this->link);
			if ($protoInfo === false) {
				if (GET_EXP_FLAG) { // 例外を投げるか
					throw new OD_MySQLException($errMsg . mysql_error(), mysql_errno());
				}
			} else {
				return $protoInfo;
			}
		} else {
			if (GET_EXP_FLAG) { // 例外を投げるか
				throw new OD_MySQLException($errMsg . "データベースへの接続が確立されていません。", OD_MySQLException::DB_NOT_CONNECTED);
			}
		}
	}

	/**
	* MySQLクライアントの情報を取得する
	* @return string MySQLクライアントのバージョンをstringで返します。
	* @link http://www.php.net/manual/ja/function.mysql-get-client-info.php mysql_get_client_info
	*/
	//* @throws OD_MySQLException 情報が取得出来ない場合に例外をスローします。
	//* @see OD_MySQLException
	public function getClientInfo()
	{
		$errMsg = "クライアント情報を取得できません。";
		$clientInfo = mysql_get_client_info();
		if ($clientInfo === false) {
			if (GET_EXP_FLAG) { // 例外を投げるか
				throw new OD_MySQLException($errMsg . mysql_error(), mysql_errno());
			}
		} else {
			return $clientInfo;
		}
	}

	//-------------------------------------------------------
	// その他プロパティ取得メソッド
	//-------------------------------------------------------

	/**
	* 接続中のデータベースのリンクIDを取得する
	* @return resource リソース型 resource(mysql link) を返します。接続していない場合はnullとなります。
	*/
	public function getLinkID()
	{
		return $this->link;
	}

	/**
	* 現在データベースへの接続が確立しているか調べる
	* @return boolean 接続している場合はtrue、接続していない場合はfalseを返します。
	*/
	public function isConnected()
	{
		if ($this->link !== false) {
			// if it seemed to be once connected
			if (mysql_ping($this->link)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	* 現在データベースが選択されているか調べる
	*
	* 実際にサーバに接続してデータベース名を取得して確認します。
	* プロパティ値だけで判断するばあいは{@link getDBName() getDBName()}がnullを返すかどうかで判断できます。
	* @return boolean 現在データベースが選択さている場合はtrue、選択されてない場合、接続していない場合はfalseを返します。
	* @uses getInfoByQuery()
	* @throws OD_MySQLException DBが選択されているが、プロパティ$dbNameと一致しない場合、例外をスローします。
	*/
	public function isDBSelected()
	{
		$errMsg = "データベース選択状態判断時に問題が発生しました。";
		if ($this->isConnected()) {
			$nowDBName = $this->getInfoByQuery($this->CMD_GET_DATABASE);
				if ($nowDBName === $this->dbName) { // 一応現在のプロパティと比較する
					if (!is_null($nowDBName)) {
						return true;
					} else {
						return false;
					}
				} else {
					throw OD_MySQLException($errMsg . 
					"取得したデータベース名と現在のプロパティの値が一致しません。", 
					 OD_MySQLException::INTERNAL_AND_FETCHED_MISMATCH);
				}
		} else {
			return false;
		}
	}

	/**
	* SQL情報関数でデータベース情報(データベース名、ユーザー名)を取得する
	*
	* リンクを用いて接続した場合、データベース接続の基本情報が不足するため、SQLを発行してデータを取得します。SQLにはMySQLの組み込みコマンドを使用しています。
	* @param string MySQL情報関数(constで定義)
	* @return string 結果を文字列で返します。
	* @throws OD_MySQLException 情報取得出来ない場合に例外をスローします。
	* @see $svrName, $dbName, $userName, OD_MySQLException
	*/
	private function getInfoByQuery($strCMD)
	{
		$errMsg = "データベース情報の取得に失敗しました。";

		$strSQL = "SELECT " . $strCMD;
		$rs = mysql_query($strSQL, $this->link);
		if ($rs !== false) {
			if (is_resource($rs)) {
				if (get_resource_type($rs) == "mysql result") {
					if (mysql_num_rows($rs) == 1) {
						$rsArray = mysql_fetch_assoc($rs);
						if (array_key_exists($strCMD,$rsArray)) {
							return $rsArray[$strCMD];
						} else {
							throw new OD_MySQLException($errMsg . 
							"指定されたフィールドが存在しません。", 
							OD_MySQLException::NONEXIST_FIELD_INDICATED);
						}
					} else {
						throw new OD_MySQLException($errMsg . 
							"結果のレコード数が１ではありません。", 
							OD_MySQLException::REC_COUNT_NOTONE);
					}
				} else {
					//基本ありえない
					throw new OD_MySQLException($errMsg . 
						"結果がresource(mysql result)型ではありません。" . 
						OD_MySQLException::SQL_KINDS_INVALID);
				}
			} else {
				// trueが返っている
				throw new OD_MySQLException($errMsg . 
					"SELECT文ではないSQLが発行された可能性があります。", 
					OD_MySQLException::SQL_KINDS_INVALID);
			}
		} else {
			// SQL失敗
			throw new OD_MySQLException($errMsg . 
				"SQLの実行に失敗しました。". mysql_error(), 
				mysql_errno());
		}
	}

	/**
	* 現在接続しているサーバ名を取得する
	* @return string 現在接続しているサーバ名を文字列で返します。接続していない場合はnullになります。
	*/
	public function getServerName()
	{
		return $this->svrName;
	}

	/**
	* 現在接続しているデータベース名を取得する
	* @return string 現在接続しているデータベース名を文字列で返します。接続していない場合はnullにを返します。
	*/
	public function getDBName()
	{
		return $this->dbName;
	}

	/**
	* 現在のデータベースのユーザー名を取得する
	* @return string ユーザー名を文字列で返します。データベースに接続していない場合はnullになります。
	*/
	public function getUserName()
	{
		return $this->userName;
	}

	/**
	* フィールド情報オブジェクトを取得する。
	*
	* @param string テーブル名
	* @param string データベース名
	* @return OD_FieldInfoMySQL OD_FieldInfoMySQLクラスのインスタンスを返します。
	* @see OD_MySQLException
	* @uses OD_FieldInfoMySQL
	*/
	public function getFieldInfo($table_name, $db_name = null)
	{
		return new OD_FieldInfoMySQL($this, $table_name, $db_name = null);
	}

	//-------------------------------------------------------
	// デストラクタ
	//-------------------------------------------------------

	/**
	* デストラクタ
	* 
	* コネクションが継続していれば、コネクションを切断します。
	*/
	function __destruct()
	{
		if ($this->isConnected()) {
			mysql_close($this->link);
		}
	}

}

?>

