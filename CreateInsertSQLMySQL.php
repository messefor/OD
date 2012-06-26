<?php

/**
* OD_FieldInfoMySQLクラス
*/
require_once("OD/FieldInfoMySQL.php");

/**
* OD_LogicalExceptionクラス
*/
require_once("OD/LogicalException.php");

/**
* OD_MySQLExceptionクラス
*/
require_once("OD/MySQLException.php");

/**
* 連想配列からSQL文(INSERT文)を作成するクラス
*
* 与えられた連想配列から、キーをフィールド名、要素をVALUESとしてINSERT文を作成します。このクラスはMySQL関数を利用しています。(MySQLi、ADODB、PDOではない)
* @author ODA Daisuke
* @copyright 2012 althi.inc
* @version
* @link http://www.php.net/manual/ja/ref.mysql.php	MySQL関数
* @uses OD_MySQLException
* @uses OD_LogicalException
*/

class OD_CreateInsertSQLMySQL
{
	//---------------------------------------------------------------------
	// プロパティ
	//---------------------------------------------------------------------
	/**
	* フィールド情報(MySQL_FieldInfoクラスのインスタンス)
	*/
	protected $fieldInfo = null;

	//---------------------------------------------------------------------
	// 定数
	//---------------------------------------------------------------------
	/**
	* INSETSQL作成の方式
	*/
	const CREATE_ALLINONE = 0;
	const CREATE_EACH = 1;

	//---------------------------------------------------------------------
	// メソッド
	//---------------------------------------------------------------------
	/**
	* コンストラクタ
	*
	* フィールド情報をセットします。({@link MySQL_FieldInfo MySQL_FieldInfoクラス}のインスタンスをプロパティに格納)
	* @param MySQL_FieldInfo MySQL_FieldInfoクラスのインスタンス(追加対象テーブルのフィールド情報）
	* @throws OD_LogicalException インスタンスの作成に失敗した場合、例外をスローします。
	*/
	public function __construct(MySQL_FieldInfo $insFieldInfo)
	{
		$errMsg = "インスタンスの作成に失敗しました。";
		if ($insFieldInfo instanceof MySQL_FieldInfo) {
			$this->fieldInfo = $insFieldInfo;
		} else {
			throw OD_LogicalException($errMsg . 
				"コンストラクタの引数型が不正です。", 
				OD_LogicalException::UNEXPECTED_PARAM_TYPE);
		}
	}


	/**
	* SQLを作成する
	*
	* @param array コンバート元の連想配列。キーがフィールド名、値がレコードの値になります。
	* @param int 出力するINSERT文の形式(CREATE_EACH →　1行ずつ配列に格納。CREATE_ALLINONE → VALUESの後の括弧を複数連ねて1つにする。)
	* @return array|string SQL文が複数格納された配列が返ります。各要素にひとつのSQL文が格納されています。引数に空配列が渡された場合nullが返ります。
	* @throws OD_LogicalException -SQL作成に失敗した場合例外をスローします。
	*/
	public function create(array $dataArray, $outType = self::CREATE_EACH )
	{
		$errMsg = "SQLの作成に失敗しました。";
		if (is_array($dataArray)) {
			if (count($dataArray) > 0) {
				//すべてのフィールドが存在するか cf FieldInfo
				$nowCnt = 0;
				$rowMax = 0;
				$sqlFields = '';
				$fieldArray = array_keys($dataArray);
				foreach ($fieldArray as $fieldName) {
					if ($this->fieldInfo->fieldExists($fieldName)) {
						if (is_array($dataArray[$fieldName])) {
							$sqlFields .= "`" . $fieldName . "`, "; // SQLフィールド部分の作成([`]で囲んで[, ]で区切る)
						} else {
							// 1次元配列の場合
							throw new OD_MySQLException($errMsg . 
							"変換元の配列は、１次元目のキーにフィールド名、" . 
							"値に(キーにレコード番号、値に内容をもつ)" . 
							"配列を持った、２次元配列である必要があります。", 
							OD_MySQLException::NONEXIST_FIELD_INDICATED);
						}
					} else {
						throw new OD_MySQLException($errMsg . 
							"与えられた連想配列中のキー [" . $fieldName . 
							"] はテーブルのフィールド名に存在しません。", 
							OD_MySQLException::NONEXIST_FIELD_INDICATED);
					}

					// 各列のレコード数を比較して最大値を求める
					$nowCnt = count($dataArray[$fieldName]);
					if ($nowCnt > $rowMax) {
						$rowMax = $nowCnt;
					}
				}
				// おしりのコンマをとる
				$sqlFields = mb_substr($sqlFields, 0, mb_strlen($sqlFields) - 2);

				// 配列のデータ格納方法によってどう対処するか？
				// SQLを行ごとに作成
				$recLen = $rowMax;
				$sqlStore = array();
				$sqlValues_ALL = null;
				$rowCnt = 0;
				for ($i = 0; $i < $recLen; $i++) { // レコード方向のループ
					$sqlValues = null;
					$sqlAll = null;
					foreach ($fieldArray as $fieldName) { // フィールド方向のループ
						// debug
						// if (!isset($dataArray[$fieldName][$i])) { 
						//	echo "エラー!!!" . $fieldName . "  " . $i . "番\n";
						// }
						$nowValue = @$dataArray[$fieldName][$i];
						$this->chkValueForSQL($nowValue); // 不正データのチェックなど
						$cnvValue = $this->dataConvAndEscape($nowValue, $fieldName); // 一部の値変換
						$sqlValues .= $this->quoteProper($cnvValue, $fieldName) . ", "; // クォートしてVALUES(～)部分を構成
					}
					// おしりのコンマをとる
					$sqlValues = mb_substr($sqlValues, 0, mb_strlen($sqlValues) - 2);
					$sqlValues = '(' . $sqlValues . ')';

					$sqlValues_ALL .= $sqlValues . ", \n";
					$rowCnt++;


					switch ($outType) {
						case (self::CREATE_ALLINONE):// 1文で全部追加するSQL
							// $outType は 0
							break;
						default:
							// $outType は 1以上
							if ($rowCnt == $outType || $i == ($recLen - 1)) {
								$sqlValues_ALL = mb_substr($sqlValues_ALL, 0, mb_strlen($sqlValues_ALL) - 3);
								$sqlAll = $this->margeSQL($this->fieldInfo->getTableName(), $sqlFields, $sqlValues_ALL);
								// $sqlAll = 'INSERT INTO ' . $this->fieldInfo->getTableName() . "\n" . 
								//  		'(' . $sqlFields . ')' . "\n" . 
								//		'VALUES ' . $sqlValues  . ';';
								$sqlStore[] = $sqlAll;

								$sqlValues_ALL = null;
								$rowCnt = 0;
							}
					}
				}
				if ($outType == self::CREATE_ALLINONE) {
					$sqlValues_ALL = mb_substr($sqlValues_ALL, 0, mb_strlen($sqlValues_ALL) - 3);
					$sqlStore = $this->margeSQL($this->fieldInfo->getTableName(), $sqlFields, $sqlValues_ALL);
					//$sqlStore = 'INSERT INTO ' . $this->fieldInfo->getTableName() . "\n" . 
					//		'(' . $sqlFields . ')' . "\n" . 
					//		'VALUES ' . $sqlValues_ALL  . ';';
				}
				return $sqlStore;
			} else {
				// null が返る
			}
		} else { // 配列じゃない
			throw OD_LogicalException($errMsg . 
				"引数の型が想定外です。配列ではありません。", 
				OD_LogicalException::UNEXPECTED_PARAM_TYPE);
		}
	}

	/**
	* SQLの各パーツから、INSERT文をマージして組み立てる。
	*
	* @param string インサート対象のテーブル名
	* @param string SQLのフィールド名部分
	* @param string SQLの値部分
	* @return string 完成したSQL文
	*/
	private function margeSQL($tableName, $fieldsList, $valuesList)
	{
		$sqlAll = 'INSERT INTO ' . $this->fieldInfo->getTableName() . "\n" . 
				'(' . $fieldsList . ')' . "\n" . 
				'VALUES ' . $valuesList  . ';';
		return $sqlAll;
	}

	/**
	* SQLに変換用の値にデータの不正がないかチェック
	* とりあえずスカラ値以外の値がSQLのVALUEとして格納されてないかチェックします。
	* @param mixed チェックする値
	* @throws OD_LogicalException  -変換に失敗した場合例外をスローします。
	*/
	protected function chkValueForSQL($chkValue)
	{
		$errMsg = "SQL作成時の値チェックに引っかかりました。";
		//要素がarray型やオブジェクト型の場合はエラー
		if (!is_scalar($chkValue) && !is_null($chkValue)) {
			throw new OD_MySQLException($errMsg . 
				"SQL作成に適していない要素が発見されました。要素にスカラ値以外が含まれています。", 
				OD_MySQLException::INVALID_VALUE_MAKING_SQL);
		}
	}

	/**
	* SQLに変換する前に値を変換する必要があるデータについてはここで変換する。(エスケープや型変換も含め)
	* - 文字列へのキャスト
	* - null →  文字列NULL　へ変換
	* - 日付型のフォーマット変更
	* - 特殊文字のエスケープ
	* @param mixed 変換前の値
	* @param string 値が格納されるフィールド名
	* @return string 変換後の文字列
	* @throws OD_LogicalException  -変換に失敗した場合例外をスローします。
	*/
	protected function dataConvAndEscape($srcValue, $fieldName)
	{
		$errMsg = "SQL作成時のデータ変換に失敗しました。";
		switch (true) {
			case is_null($srcValue): // nullの場合
				return 'NULL';
				break;
			case $this->fieldInfo->is_DateCat($fieldName): // フィールドが日付型の時
				$timestamp = strtotime($srcValue);
				if ($timestamp !== false) {
					return date('Y-m-d H:i:s',$timestamp);
				} else {
					//要素が日付型に変換できない場合
					throw new OD_MySQLException($errMsg . 
						"SQL作成に適していない要素が発見されました。日付型にキャストできません。", 
						OD_MySQLException::INVALID_VALUE_MAKING_SQL);
				}
			case $this->fieldInfo->is_StrCat($fieldName): // フィールドが文字列型の時
				//　特殊文字のエスケープ
				return mysql_real_escape_string($srcValue, $this->fieldInfo->getLinkID());
			default: // 上記に当てはまらない
				if (is_numeric($srcValue)) {
					return (string)$srcValue;
				} else {
					throw new OD_MySQLException($errMsg . 
						"SQL作成に適していない要素が発見されました。" . 
						"フィールドのデータ型と値の型が一致しません。" , 
						OD_MySQLException::INVALID_VALUE_MAKING_SQL);
				}
			}
	}

	/**
	* 値を対応するフィールドのデータ型に応じてクォートする
	*
	* [注意]数値型以外は全てシングルクォテーションでクォートします。バイナリなどもクォートしてしまいます。数値型はそのまま返します。
	* @param string クォートする文字列
	* @param string 対応するフィールド名
	* @return string クォート処理後の文字列
	*/
	protected function quoteProper($valueInside, $fieldName)
	{
		switch (true) {
			case $valueInside == 'NULL': // NULL文字列の場合
				return $valueInside;
				break;
			case $this->fieldInfo->is_StrCat($fieldName): // フィールドが文字列型の時
			case $this->fieldInfo->is_DateCat($fieldName): // フィールドが日付型の時
				return "'" . $valueInside . "'"; //　シングル・クォーテーションで囲む
				break;
			default:
				return $valueInside;
		}
	}
}
?>
