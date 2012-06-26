<?php

/**
* フィールド情報を提供するクラス
*
* 型やクォートの判断、などフィールドに付随する各種情報を与えられたフィールド名から返すことを目的としたクラス。SQLを用いてフィールド情報を取得します。クラス内では主にMySQL関数を使用します。(ADODB、PDO、MySQLiは使用してない)

* @author ODA Daisuke
* @copyright 2012 althi.Inc
* @version バージョン
* @link http://www.php.net/manual/ja/book.mysql.php MySQL関数
* @uses MySQL_RunQuery
* @uses OD_MySQLException
* @uses OD_LogicalException
*/

class OD_FieldInfoMySQL
{

	//-----------------------------------------------------
	// 定数
	//-----------------------------------------------------

	/**
	* MySQLデータカテゴリ(数値型、日付型、文字列型)
	*/
	const DCAT_NUM = 0;
	const DCAT_DATE = 1;
	const DCAT_STR = 2;

	/**
	* MySQLキーの種類
	* @todo 要確認
	*/
	const KEY_PRIMARY = "PRI";
	const KEY_UNIQUE = "UNI";
	const KEY_INDEX = "KEY";
	const KEY_FULLTEXT = "FUL";

	/**
	* SHOW COLUMNS FROM で得られる必須の情報のフィールド名
	*/
									// 説明( 'SHOW COLUMNS' の結果のレコード型[想定される値など])
	const COL_FIELDNAME = 'Field'; // フィールド名 (string)
	const COL_DATATYPE = 'Type'; // データ型 (string)
	const COL_NULL = 'Null'; // NULL許容 (string[YES/NO])
	const COL_KEY = 'Key'; // キー (string[PRI/""/] )
	const COL_DEFAULT = 'Default'; // デフォルト値 (型に寄って違う？[NULL/""/ ])
	const COL_EXTRA = 'Extra'; // Extra (string[auto_incrementとか])

	/**
	* その他の情報のフィールド名
	*/
	const COL_DATA_CAT = 'DataCategory'; // データカテゴリ

	//-----------------------------------------------------
	// プロパティ
	//-----------------------------------------------------

	/**
	* SQL(SHOW COLUMNS)の結果を格納する配列(フィールドの基本情報を保持)
	* @var array
	*/
	protected $infoBasic = null;

	/**
	* データ型についての拡張情報を格納する配列
	* @var array
	*/
	protected $infoExtType = null;

	/**
	* 基本データのレコード数(=指定テーブルのフィールド数)
	* @var int
	*/
	private $rowCount = null;

	/**
	* テーブル名
	* @var string
	*/
	private $tblName = null;

	/**
	* コネクションリンク resource(mysql link)
	* @var resource
	*/
	private $link = null;

	//-----------------------------------------------------
	// メソッド
	//-----------------------------------------------------

	/**
	* コンストラクタ
	*
	* データベースへの接続、SQL実行を行い、フィールド基本情報(フィールド名・型など)を取得します。
	* @param object {@link MySQL_Base MySQL_Baseクラス}
	* @param string テーブル名
	* @param string データベース名。コネクションで既にDBが選択されていれば省略可能です。DBが選択されてないコネクションで省略した場合は例外をスローします。
	*/
	function __construct( MySQL_Base $base, $table_name, $db_name = null)
	{
		$errMsg = "インスタンスの生成に失敗しました。";

		if (isset($base) && isset($table_name)) {

			//データベース選択(必要あれば)
			if (is_null($base->getDBName())) {
				if (!is_null($db_name)) {
					$base->selectDB($db_name);
				} else {
					throw new OD_MySQLException($errMsg . 
						"データベースが選択できません。データベース未選択状態で引数(データベース名)省略はできません。", 
						OD_MySQLException::CANT_SELECT_DB);
				}
			}

			//SQLを実行
			$strSQL = "SHOW COLUMNS FROM " . $table_name;
			$this->infoBasic = $base->query($strSQL);

			// すべての必要インデックスが基本情報に存在するか調べる
			if (!$this->allInfoFeilds_Exist()) {
				throw new OD_MySQLException($errMsg . 
					"与えられた結果レコードに必須フィールドが欠けています。", 
					OD_MySQLException::NESS_FIELD_LACKS);
			}

			$this->rowCount = count($this->infoBasic[self::COL_FIELDNAME ]);
			if ($this->rowCount > 0) { 
				//パース実行
				$this->parse(); 
				$this->tblName = $table_name;
				$this->link = $base->getLinkID();
			} else {
				throw new OD_MySQLException($errMsg . 
					"フィールド情報取得SQLに対してレコードが空の結果がかえりました。",
					OD_MySQLException::REC_COUNT_ZERO);
			}
		} else {
			throw new OD_LogicalException($errMsg . 
				"引数(データベース情報)がセットされていません。", 
				OD_LogicalException::HAS_UNSETED_PARAM);
		}
	}

	//-----------------------------------------------------
	// パース
	//-----------------------------------------------------
	/**
	* フィールド情報(SHOW COLUMNSの返り値)をパースして、拡張情報を作成する。
	* @uses allInfoFeilds_Exist()
	* @uses getDataCat()
	* @throws Exception_MySQL -パースに失敗した場合に例外をスローします。
	* @throws Exception_Logical -パースに失敗した場合に例外をスローします。
	*/
	protected function parse()
	{
		$errMsg = "フィールド情報のパースに失敗しました。";

		for ($i = 0; $i < $this->rowCount; $i++) {

			// データ型拡張情報のパース
			$this->infoExtType[self::COL_FIELDNAME][$i] = $this->infoBasic[self::COL_FIELDNAME][$i];
			$this->infoExtType[self::COL_DATA_CAT][$i] = $this->getDataCat($this->infoBasic[self::COL_DATATYPE][$i]);

			// その他のパースをここに追加

		}
	}

	/**
	* 基本情報に必須フィールドが全て含まれているか調べる
	*
	* SHOW COLUMNS で取得できる基本データに必須フィールドが全て含まれているか調べます。constでフィールド定義を追加したときは
	* (MySQLのSHOW COLUMNSの返り値の仕様が変わった場合)この関数内の配列にフィールドを追加する必要があります。
	* - ※ 現在の必須フィールド
	* - Field // フィールド名
	* - Type // データ型
	* - Null // NULL許容
	* - Key // キー
	* - Default // デフォルト値
	* - Extra // Extra
	* @return boolean すべて存在するtrue、何れかの必須フィールドが存在しないfalse
	*/
	private function allInfoFeilds_Exist()
	{
		$nessFields = array(
							self::COL_FIELDNAME,
							self::COL_DATATYPE,
							self::COL_NULL,
							self::COL_KEY,
							self::COL_DEFAULT,
							self::COL_EXTRA
							// SHOW COLUMNSのフィールド変更がある場合はここに追加
							);

		foreach ($nessFields as $value) { //必須フィールドのループ
			if (!array_key_exists($value, $this->infoBasic)) {
				return false;
			}
		}
		return true;
	}

	/**
	* データカテゴリを取得する(パースする)
	*
	* MySQLのデータタイプ(char,varchar,int,smallintとか)からデータカテゴリ(数値型、文字列型、日付型)を取得します。
	*
	* @link http://dev.mysql.com/doc/refman/5.1-olh/ja/data-type-overview.html MySQLのデータ型
	* @param string MySQLのデータ型
	* @return int 数値=0、日付=1、文字列=2
	* @todo 
	* - データタイプの分類を考え直す(追加する TEXT型など)。
	*/
	private function getDataCat($DataType)
	{
		if (is_string($DataType)) {
			$errMsg = "データカテゴリを判断できませんでした。";

			// 日付型のパターン
			$pttnDate = '/(date|datetime|timestamp|time|year\s?(\([24]\))?)/';

			// 数値型のパターン
			$pttnNum = '/(int|tinyint|smallint|mediumint|bigint|float|real|' .
						'double|double precision|integer|smallint|decimal|numeric|dec|bit|numeric)' .
						'\(\d+(,\d+)?\)\s?((un)?signed)?/';

			// 文字列型のパターン
			$pttnStr = '/((char|varchar|character varying|varbinary)\(\d+\)' .
						'|(binary|char byte|blob|tinyblob|mediumblob|longblob|text)(\(\d+\))?' .
						'|(enum|set)([^\)]+))/';

			switch (true) {
				case (preg_match($pttnDate, $DataType) == 1) :
					return self::DCAT_DATE;
					break;
				case (preg_match($pttnNum, $DataType) == 1) :
					return self::DCAT_NUM;
					break;
				case (preg_match($pttnStr, $DataType) == 1) :
					return self::DCAT_STR;
					break;
				default:
					throw new OD_MySQLException($errMsg . 
						"想定外のMySQLデータ型です。正規表現にマッチしませんでした。", 
						OD_MySQLException::UNKNOWN_DATATYPE_NAME);
			}
		} else {
			throw new OD_LogicalException($errMsg . 
				"引数(MySQLデータタイプ)が文字列ではありません。", 
				OD_LogicalException::UNEXPECTED_PARAM_TYPE);
		}
	}


	//-----------------------------------------------------
	// フィールド存在確認
	//-----------------------------------------------------
	/**
	* 指定されたフィールドが存在するか調べる
	* @param string フィールド名
	* @return boolean 存在する場合true、存在しない場合false
	*/
	public function fieldExists($fieldName)
	{
		return in_array($fieldName, $this->infoBasic[self::COL_FIELDNAME]);
	}

	//-----------------------------------------------------
	// データカテゴリ情報関連
	//-----------------------------------------------------
	/**
	* フィールドのデータカテゴリが日付型か調べる
	* @param string フィールド名
	* @return boolean 日付型の場合true、日付型でない場合false
	* @uses chkDataCat()
	* @throws Exception_MySQL -データカテゴリ判別に失敗した場合、例外をスローします。
	*/
	public function is_DateCat($fieldName)
	{
		return $this->chkDataCat($fieldName, self::DCAT_DATE);
	}

	/**
	* フィールドのデータカテゴリが数値型か調べる
	* @param string フィールド名
	* @return boolean 数値型の場合true、数値型でない場合false
	* @uses chkDataCat()
	* @throws Exception_MySQL -データカテゴリ判別に失敗した場合、例外をスローします。
	*/
	public function is_NumCat($fieldName)
	{
		return $this->chkDataCat($fieldName, self::DCAT_NUM);
	}

	/**
	* フィールドのデータカテゴリが文字列型か調べる
	* @param string フィールド名
	* @return boolean 文字列型の場合true、文字列型でない場合false
	* @uses chkDataCat()
	* @throws Exception_MySQL -データカテゴリ判別に失敗した場合、例外をスローします。
	*/
	public function is_StrCat($fieldName)
	{
		return $this->chkDataCat($fieldName, self::DCAT_STR);
	}

	/**
	* フィールドのデータカテゴリと与えられたカテゴリが一致するか調べる
	* @param string フィールド名
	* @param int カテゴリ番号(定数)
	* @return boolean 一致する場合true、一致しない場合falseを返します。
	* @throws Exception_MySQL -データカテゴリ判別に失敗した場合、例外をスローします。
	*/
	private function chkDataCat($fieldName, $CategoryNum)
	{
		$errMsg = "データカテゴリ判別に失敗しました。";
		$idx = array_search($fieldName, $this->infoExtType[self::COL_FIELDNAME]);
		if ($idx !== false) {
			if ($this->infoExtType[self::COL_DATA_CAT][$idx] == $CategoryNum) { 
				return true;
			}
			return false;
		} else {
			throw new OD_MySQLException($errMsg . 
				"指定されたフィールド名がテーブルに存在しません。", OD_MySQLException::NONEXIST_FIELD_INDICATED);  
		}
	}

	//-----------------------------------------------------
	// キー情報関連
	//-----------------------------------------------------
	/**
	* 指定されたフィールドに指定されたキー設定がされているか調べる
	* @param string フィールド名
	* @param string キーの種類(constで定義される)
	* @return boolean 指定キーの場合true、キーでない場合false
	* @throws Exception_MySQL -データカテゴリ判別に失敗した場合、例外をスローします。
	*/
	private function chkKeyType($fieldName, $KEY_TYPE)
	{
		$errMsg = "キー判別に失敗しました。";
		$idx = array_search($fieldName, $this->infoBasic[self::COL_FIELDNAME]);
		if ($idx !== false) {
			if ($this->infoBasic[self::COL_KEY][$idx] == $KEY_TYPE) { 
				return true;
			}
			return false;
		} else {
			throw new OD_MySQLException($errMsg . 
				"指定されたフィールド名がテーブルに存在しません。", OD_MySQLException::NONEXIST_FIELD_INDICATED);  
		}
	}

	/**
	* 指定フィールドがプライマリー・キーか調べる
	* @param string フィールド名
	* @return boolean プライマリー・キー型の場合true、プライマリー・キー型でない場合false
	* @uses chkKeyType()
	*/
	public function is_PriKey($fieldName)
	{
		return $this->chkKeyType($fieldName, self::KEY_PRIMARY);
	}

	//-----------------------------------------------------
	// NULL情報関連
	//-----------------------------------------------------
	/**
	* 指定されたフィールドがNULL値を許容するか調べる
	* @param string フィールド名
	* @return boolean NULLを許容する場合true、しない場合false
	* @throws Exception_MySQL -データカテゴリ判別に失敗した場合、例外をスローします。
	*/
	public function allowsNULL($fieldName)
	{
		$errMsg = "NULL許容の判別に失敗しました。";
		$idx = array_search($fieldName, $this->infoBasic[self::COL_FIELDNAME]);
		if ($idx !== false) {
			switch ($this->infoBasic[self::COL_NULL][$idx]) { 
				case 'YES':
					return true;
					break;
				case 'NO';
					return false;
					break;
				default:
					throw new OD_MySQLException($errMsg . 
						"想定外の値を取得しました(YES/NO以外の取得)。", OD_MySQLException::UNEXPECTED_VALUE_FETCHED);  
			}
		} else {
			throw new OD_MySQLException($errMsg . 
				"指定されたフィールド名がテーブルに存在しません。", OD_MySQLException::NONEXIST_FIELD_INDICATED);  
		}
	}

	/**
	* コネクションリンクを取得する
	* @return resource コネクションリンク resource(mysql link)
	*/
	public function getLinkID()
	{
		return $this->link;
	}

	/**
	* テーブル名を取得する
	* @return string テーブル名
	*/
	public function getTableName()
	{
		return $this->tblName;
	}
}
?>

