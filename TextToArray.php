<?php

/**
* カスタム例外クラス
*/
require_once('OD/LogicalException.php');
require_once('OD/MySQLException.php');
require_once('OD/FileDirException.php');

/**
* テキストファイルのデータを配列へ格納するクラス
*
* @author ODA Daisuke
* @copyright 2012 althi.inc
* @version
*/

class OD_TextToArray 
{

	/**
	* オープンするファイルパス
	* @var string
	*/
	protected $filePath = null;

	/**
	* ファイルリード時に使用する関数名を格納する(zgipとtextで関数が異なるため)
	* テキスト用の関数はこんな感じ
	* - array(
	* - 	"open" => "gzopen", // オープン用
	* - 	"gets" => "gzgets", // 行取得用
	* - 	"close" => "gzclose" // クローズ用
	* - 	)
	* @var array
	*/
	protected $readFunc = array();

	/**
	* 行をフィールド単位に分割するデリミタ
	* @var string
	*/
	protected $delim = null;

	/**
	* ファイルの種類(gzip, text)を判別する文字列
	*/
	const GZIP_INFO_STRING = 'gzip compressed data, from FAT filesystem (MS-DOS, OS/2, NT)';
	const TEXT_INFO_STRING = 'UTF-8 Unicode';

	/**
	* 区切り文字の種類
	*/
	const DELIM_TAB = "\t";
	const DELIM_COMMA = ",";

	/**
	* ファイルを読み込む
	*
	* ファイルパスをチェックして、利用可能か調べます。利用可能なファイルの種類はgzipかtext(utf-8)ファイルです。
	* @param string ファイルパス
	* @param string デリミタ(行をフィールド単位で分割するためのデリミタです。デフォルトでTABになります。)
	* @throws Exception_FileDir, Exception_Logical -- インスタンス作成に失敗した場合例外をスローします。
	* @see Exception_FileDir, Exception_Logical
	*/
	function open($openFilePath, $delimiter = self::DELIM_TAB)
	{
		$errMsg = get_class() . "クラスのインスタンスの作成に失敗しました。";
		if (is_readable($openFilePath)) {
			// ファイルの種類をチェック
			$objFinfo = new OD_FieldInfoMySQL();
			$strInfo = $objFinfo->file($openFilePath);
			switch (true) {
				case mb_strpos($strInfo, self::GZIP_INFO_STRING) !== false:
					$this->filePath = $openFilePath;
					$this->readFunc = array("open" => "gzopen", "gets" => "gzgets", "close" => "gzclose");
					break;
				case mb_strpos($strInfo, self::TEXT_INFO_STRING) !== false:
					$this->filePath = $openFilePath;
					$this->readFunc = array("open" => "fopen", "gets" => "fgets", "close" => "fclose");
					break;
				default:
					throw new OD_FileDirException($errMsg . 
							"[" . $strInfo . "]" .
						"は利用可能なファイルタイプではありません。", 
						OD_FileDirException::UNEXPECTED_FILE_TYPE);
			}
		} else {
			throw new OD_FileDirException($errMsg . 
				"ファイルを読み込めません。", 
				OD_FileDirException::CANNOT_OPEN_FILE);
		}

		// 分割文字の格納
		if (isset($delimiter) && $delimiter !== "") {
			$this->delim = $delimiter;
		} else {
			throw new OD_LogicalException($errMsg . 
				"与えられたデリミタ(引数)は不正です。", 
				OD_LogicalException::INVALID_PARAM);
		}
	}

	/**
	* ファイルデータを格納した配列を取得する(列方向配列型)
	* こんな形で格納されます。
	* - array(
	* -		"key1" => array( value1-1, value1-2, value1-3,　),
	* -		"key2" => array( value2-1, value2-2, value2-3,　),
	* -		"key3" => array( value3-1, value3-2, value3-3,　),
	* -		)
	* @return array
	* @throws Exception_FileDir, Exception_Logical -- 配列格納に失敗した場合例外をスローします。
	* @see Exception_FileDir, Exception_Logical
	*/
	public function getArray()
	{
		$errMsg = "ファイルデータを配列に格納できません。";
		// ファイルを開く
		if ($fp = $this->readFunc["open"]($this->filePath,'r')) {
			// オープン成功
			$rowCnt = -1;
			$dataArray = array();
			while ($buffLine = $this->readFunc["gets"]($fp)){
				$rowArray = explode($this->delim, $buffLine);
				if ($rowCnt == -1) {
					$lblArray = $rowArray;
				} else {
					$colLen = count($rowArray);
					for ($i = 0; $i < $colLen; $i++) {
						$dataArray[$lblArray[$i]][$rowCnt] = $rowArray[$i];
					}
				}
				$rowCnt++;
			}
			$this->readFunc["close"]($fp);
			return $dataArray;
		} else {
			throw new OD_FileDirException($errMsg . 
				"ファイルを開けません。", 
				OD_FileDirException::CANNOT_OPEN_FILE);
		}
	}

	/**
	* ファイルデータを格納した配列を取得する(行方向配列型)
	* こんな形で格納されます。
	* - array(
	* -		array( key1 => value1-1, key2 => value1-2, key3 => value1-3,　),
	* -		array( key1 => value2-1, key2 => value2-2, key3 => value2-3,　),
	* -		array( key1 => value3-1, key2 => value3-2, key3 => value3-3,　),
	* -		)
	* @return array
	* @throws Exception_FileDir, Exception_Logical -- 配列格納に失敗した場合例外をスローします。
	* @see Exception_FileDir, Exception_Logical
	*/
	public function getArrayHorizontal()
	{
			$errMsg = "ファイルデータを配列に格納できません。";
			if ($fp = $this->readFunc["open"]($this->filePath,'r')) {
				// ファイルオープン成功
				$rowCnt = 0;
				$dataArray = array();
				while ($buffLine = $this->readFunc["gets"]($fp)){
					$rowArray = explode($this->delim, $buffLine);
					if ($rowCnt == 0) {
						$lblArray = $rowArray;
					} else {
						$rowKeyArray = array_combine($lblArray, $rowArray);
						if (is_array($rowKeyArray) !== false) {
							$dataArray[] = $rowKeyArray;
						} else {
							// キー配列($lblArray)と値配列($rowArray)の要素数が異なる
							throw new OD_LogicalException($errMsg . 
								"キーとなる配列と値を格納している配列の要素数が異なります。", 
								OD_LogicalException::CANT_CREATE_ARRAY);
						}
					}
					$rowCnt++;
				}
				fclose($fp);
				return $dataArray;
			} else {
				throw new OD_FileDirException($errMsg . 
					"ファイルを開けません。", 
					OD_FileDirException::CANNOT_OPEN_FILE);
			}
	}

	/**
	* 読み込んだファイルパスを返す
	*@return string ファイルパス
	*/
	function getFilePath()
	{
		return $this->filePath;
	}


} // end of class

// -----------------------------------------------------
// AppleRepoToArray
// -----------------------------------------------------

/**
* フィールド情報クラス
*/
require_once("OD/FieldInfoMySQL.php");


/**
* Appleの売上レポートファイルのデータを配列へ格納するクラス
*
* @author ODA Daisuke
* @copyright 2012 althi.inc
* @version
*/

class OD_AppleRepoToArray extends OD_TextToArray
{

	/**
	* MySQL_FieldInfoクラスのインスタンス
	* @var MySQL_FieldInfo
	*/
	private $infoField = null;

	/**
	* コンストラクタ
	* MySQL_FieldInfoクラスのインスタンスをプロパテティに格納します。
	* @param MySQL_FieldInfo MySQL_FieldInfoクラス
	*/
	function __construct(MySQL_FieldInfo $FieldInfo)
	{
		if ($FieldInfo instanceof MySQL_FieldInfo) {
			$this->infoField = $FieldInfo;
		} else {
			throw new OD_LogicalException($errMsg . 
				"与えられた引数はMySQL_FieldInfoクラスではありません。", 
				OD_LogicalException::UNEXPECTED_PARAM_TYPE);
		}
	}

	/**
	* ファイルデータを格納した配列を取得する(列方向配列型)
	* TextToArrayクラスのgetArrayクラスをオーバライドします。
	* - こんな形で格納されます。
	* - array(
	* -		"key1" => array( value1-1, value1-2, value1-3,　),
	* -		"key2" => array( value2-1, value2-2, value2-3,　),
	* -		"key3" => array( value3-1, value3-2, value3-3,　),
	* -		)
	*
	* - 【オーバライドで以下の機能を追加しています】
	* - 最終フィールドのLFの削除
	* - NOT NULL 制約がないフィールドの" "をNULLに変換
	* - NOT NULL 制約があるフィールドの" "を""空文字に変換
	* @return array
	* @throws Exception_FileDir, Exception_Logical -- 配列格納に失敗した場合例外をスローします。
	* @see Exception_FileDir, Exception_Logical
	*/
	public function getArray()
	{
		$errMsg = "ファイルデータを配列に格納できません。";
		// ファイルを開く
		if ($fp = $this->readFunc["open"]($this->filePath,'r')) {
			// オープン成功
			$rowCnt = -1;
			$dataArray = array();
			while ($buffLine = $this->readFunc["gets"]($fp)){
				$buffLine = mb_substr($buffLine, 0, mb_strlen($buffLine) - 1); // [変更箇所]最後の改行chr(10)の削除
				$rowArray = explode($this->delim, $buffLine);
				if ($rowCnt == -1) {
					$lblArray = $rowArray;
				} else {
					$colLen = count($rowArray);
					for ($i = 0; $i < $colLen; $i++) {
						$dataArray[$lblArray[$i]][$rowCnt] = $this->valueFix($rowArray[$i], $lblArray[$i]); // [変更箇所]
					}
				}
				$rowCnt++;
			}
			$this->readFunc["close"]($fp);
			return $dataArray;
		} else {
			throw new OD_FileDirException($errMsg . 
				"ファイルを開けません。", 
				OD_FileDirException::CANNOT_OPEN_FILE);
		}
	}

	/**
	* Appleレポートに特有の値をフィックスして返す
	* こんな形で変更されます。
	* - 【以下の部分をフィックスします】
	* - NOT NULL 制約がないフィールドの" "をNULLに変換
	* - NOT NULL 制約があるフィールドの" "を""空文字に変換
	* @param mixed 変換前の値
	* @param string 対応するフィールド名
	* @return array
	* @throws Exception_FileDir, Exception_Logical -- 配列格納に失敗した場合例外をスローします。
	* @see Exception_FileDir, Exception_Logical
	*/
	private function valueFix($preValue, $fieldName)
	{
		if ($preValue == " ") {
			if ($this->infoField->allowsNULL($fieldName)) {
				return null;
			} else {
				return "";
			}
		} else {
			return $preValue;
		}
	}

}
?>
