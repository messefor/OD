<?php

/**
* FieldInfoMySQLクラス
*/
require_once("OD/FieldInfoMySQL.php");

/**
* LogicalExceptionクラス
*/
require_once("OD/LogicalException.php");


/**
* HTMLコードの生成クラス(codingHTML)
* 
* 今のところstaticなメソッドのみ
* @package OD_HTML
* @author ODA Daisuke
* @copyright 2012 althi.Inc
* @version
*/
class OD_cHTML
{

	const USE_NUMBER_INDEX = 1;
	const IGNORE_NUMBER_INDEX = 2;
	

	/**
	* 配列からHTMLテーブルタグを生成する
	* 属性値を設定する連想配列の仕様は以下
	* - "AttrName" => "属性名",
	* - "AttrValue" => "属性値",
	* - "CallBack" => "フィルタ用コールバック関数",
	* @param array 変換する配列
	* @param array 属性値(Attribute)を指定するための連想配列
	* @return string htmlコード
	* @throws OD_LogicalException -- 変換に失敗した場合は、例外をスローします。
	*/
	public static function getTable($inputArray, $attribArray = null)
	{

		$errMsg = "配列をHTMLテーブルに変換できません。";

		if (is_array($inputArray)) {

			// 配列の構造チェックとラベル部分<th>の作成
			if (count($inputArray) > 0) {

				$keyArray = array_keys($inputArray);
				$colCnt = 0;
				$lblHTML = null;
				$nowAttrArray = null; // 属性設定用配列
				$rowIdx = null;		// 行番号
				$newLine = true;	// 改行設定

				foreach ($keyArray as $key) {
					if (is_array($inputArray[$key])) {
						// 列毎に行数が異ならないか確認
						$nowRowLen = count($inputArray[$key]);
						if ($colCnt == 0) {
							$rowLen = $nowRowLen;
						} else {
							if ($nowRowLen != $rowLen) {
								//列毎に行数が異なる場合
								throw new OD_LogicalException($errMsg . 
									"各要素配列の要素数が同じではありません。", 
									OD_LogicalException::UNEXPECTED_ARRAY_SCHEME);
							}
						}
						$colCnt++;
					} else {
						//要素が配列ではない
						throw new OD_LogicalException($errMsg . 
							"与えられた引数が多次元(二次元)配列ではありません。", 
							OD_LogicalException::UNEXPECTED_ARRAY_SCHEME);
					}
					
					// thタグを作成
					$rowIdx = 0;
					$nowAttrArray = self::getNowAttribArray($attribArray, "th", $rowIdx, $key);// 属性設定用配列
					$lblHTML .= self::tags("th", $key, $nowAttrArray);
				}
				
				// tr タグ作成(ラベルパート)
				$rowIdx = 0;
				$nowAttrArray = self::getNowAttribArray($attribArray, "tr", $rowIdx); //属性設定用配列
				$lblHTML = self::tags("tr", $lblHTML, $nowAttrArray, $newLine);

				// HTMLタグに変換
				$allHTML = null;
				$rowHTML = null;

				for ($i = 0; $i < $rowLen; $i++) { // 行方向のループ$
					$rowPart = null;
					foreach ($inputArray as $colArray) {
					
						// td を作成
						$nowAttrArray = self::getNowAttribArray($attribArray, "td", $i + 1, $colArray[$i]); //タ属性設定用配列
						$rowPart .= self::tags("td", $colArray[$i], $nowAttrArray);
					}

					// trタグ作成(データパート)
					$nowAttrArray = self::getNowAttribArray($attribArray, "tr", $i + 1);// 属性設定用配列
					$rowHTML .= self::tags("tr", $rowPart, $nowAttrArray, $newLine);
				}

				// tableタグ作成
				$nowAttrArray = self::getNowAttribArray($attribArray, "table"); //属性設定用配列
				$allHTML = self::tags("table", $lblHTML . $rowHTML, $nowAttrArray, $newLine);
				
				return $allHTML;

			} else {
				// 引数が空配列
				throw new OD_LogicalException($errMsg . 
					"与えられた引数は空配列です。", 
					OD_LogicalException::INVALID_PARAM );
			}
		} else {
			// 引数が配列じゃない
			throw new OD_LogicalException($errMsg . 
				"与えられた引数が配列ではありません。", 
				OD_LogicalException::UNEXPECTED_PARAM_TYPE);
		}
	}

	// ※配列タイプ２
	// 第一階層が、レコード数をインデックスとする配列で、第二階層の配列を値としてもつ。
	// 第二階層が、フィールド名をインデックスとする配列で、実際の値をもつ。
	// 配列でない場合は、文字列にキャストしてボックスに格納
	// 多次元配列/一次元配列/それ以外の挙動について
	public static function getTable2(array $inputArray, $attribArray = null, $index_option = self::USE_NUMBER_INDEX)
	{

		$errMsg = "配列をHTMLテーブルに変換できません。";

		// フィールド用配列を作成
		$fieldArray = null;
		$rowMaxIdx = count($inputArray);

		if ($rowMaxIdx > 0) {
			$fieldArray = array();
			// レコード(行)方向のループ
			$isArrayExists = false;
			foreach ($inputArray as $rowArray) {
				if (is_array($rowArray)) {
					$isArrayExists = true;
					// フィールド(列)方向のループ
					foreach ($rowArray as $nowField => $nowValue) {
						// フィールド名が存在しない場合はフィールド用配列へ追加
						if (
							($index_option === self::IGNORE_NUMBER_INDEX && !is_numeric($nowField)) ||
							($index_option === self::USE_NUMBER_INDEX))
							 { 
								if (array_search($nowField, $fieldArray, true) === false) {
									$fieldArray[] = $nowField;
								}
							}
					}
				} else {
					// 値が配列で無い場合は、全フィールド値がNULL(空)の行として扱う
				}
			}

			// タグの生成
			$allHTML = null;
			$newLine = true;

			if ($isArrayExists) {	// 多次元配列の場合

				// ラベルパートのタグの作成
				$lblHTML = null;

				// thの属性設定用
				$rowIdx = 0;
				$nowAttrArray = self::getNowAttribArray($attribArray, "th", $rowIdx);

				// th タグの作成
				foreach ($fieldArray as $nowField) {
					$lblHTML .= self::tags("th", $nowField, $nowAttrArray);
				}
				
				// trタグを作成(ラベルパート)
				$rowIdx = 0;
				$nowAttrArray = self::getNowAttribArray($attribArray, "tr", $rowIdx);	//属性設定用配列
				$lblHTML = self::tags("tr", $lblHTML, $nowAttrArray, $newLine);

				$allHTML .= $lblHTML;

				$fieldMaxIdx = count($fieldArray);
				for ($rowCnt = 0; $rowCnt < $rowMaxIdx; $rowCnt++) {

					$rowHTML = null;
					$rowArray = $inputArray[$rowCnt];

					// tdタグの属性設定用の配列作成
					$nowAttrArray = self::getNowAttribArray($attribArray, "td", $rowCnt + 1);

					// td タグの作成
					if (is_array($rowArray)) {
						foreach ($fieldArray as $nowField) {

							// 値の設定
							if (array_key_exists($nowField, $rowArray)) {
								$nowValue = $rowArray[$nowField];
							} else {
								$nowValue = "";
							}

							// td タグの作成
							$rowHTML .= self::tags("td", $nowValue, $nowAttrArray);

						}
					} else {
						// 値が配列で無い場合は、全フィールド値がNULL(空)の行として扱う
						for ($fieldCnt = 0; $fieldCnt < $fieldMaxIdx; $fieldCnt++) {
							$rowHTML .= self::tags("td", "", $nowAttrArray);
						}
					}

					// trタグの作成
					$nowAttrArray = self::getNowAttribArray($attribArray, "tr", $rowCnt + 1); //属性設定用配列
					$rowHTML = self::tags("tr", $rowHTML, $nowAttrArray, $newLine);
					
					$allHTML .= $rowHTML;

				}
				
			} else {
			
				//　一次元配列 の場合

				$rowCnt = 0;
				foreach ($inputArray as $nowField => $nowValue) {

					// td の作成
					$rowHTML = null;
					$nowAttrArray = self::getNowAttribArray($attribArray, "td", $rowCnt); //属性設定配列
					$rowHTML .= self::tags("td", $nowField, $nowAttrArray);
					$rowHTML .= self::tags("td", $nowValue, $nowAttrArray);

					// tr の作成
					$nowAttrArray = self::getNowAttribArray($attribArray, "tr", $rowCnt); //属性設定配列
					$allHTML .= self::tags("tr", $rowHTML, $nowAttrArray, $newLine);

					$rowCnt++;

				}
				
			}

			// table の作成
			$nowAttrArray = self::getNowAttribArray($attribArray, "table"); // 属性設定配列
			$allHTML = self::tags("table", $allHTML, $nowAttrArray, $newLine);

			return $allHTML;

		} else {
			// 引数が空配列
			throw new OD_LogicalException($errMsg . 
				"与えられた引数は空配列です。", 
				OD_LogicalException::INVALID_PARAM );
		}





	} // getTable終了



	/**
	* 属性設定配列(statusArray)の取得
	* @param array 
	*/
	private static function getNowAttribArray($attribArray, $tagname, $rowindex = null, $value = null)
	{

		$errMsg = "属性設定用配列の生成に失敗しました。";

		if (!is_null($attribArray)) {
			if (is_array($attribArray)) {
				$statusArray = self::getStatusArray($tagname, $rowindex, $value);
				return self::getAttributeArray($statusArray, $attribArray);
			} else {
				// 例外発生(値が不正) 引数が配列じゃない
				throw new OD_LogicalException($errMsg . 
					"与えられた引数が配列ではありません。", 
					OD_LogicalException::UNEXPECTED_PARAM_TYPE);
			}
		} else {
			// $attribArrayが設定されてないなど
			return null;
		}
	}


	/**
	* 属性設定配列(statusArray)の取得
	* @param array 
	*/
	private static function getStatusArray($tagname, $rowindex = null, $value = null)
	{
			$statusArray = array(
							'rowindex' => $rowindex,
							'tagname' => $tagname,
							'value' => $value,
							);
			return $statusArray;
	}

	/**
	* コールバック関数を実行して、属性設定用配列をフィルタリング
	* @param array テーブルのステータス情報を格納した配列
	* @param array 属性設定用(フィルタリング用や設定値)の配列
	* @return array フィルタリング後の配列
	* @throws OD_LogicalException
	*/
	private static function getAttributeArray($statusArray, $attrArray)
	{
		$ret = null;
		$errMsg = "属性設定用配列によるフィルタリングに失敗しました。";
		if (is_array($statusArray) && is_array($attrArray)) {
			foreach ($attrArray as $nowArray){
				if (is_array($nowArray)) {
					if (function_exists($nowArray["CallBack"])) {
						if (call_user_func($nowArray["CallBack"], $statusArray) !== false) {
							$ret[$nowArray["AttrName"]] = $nowArray["AttrValue"];
						}
					} else {
						// コールバック関数が存在しない
						throw new OD_LogicalException($errMsg . 
							"指定されたコールバック関数は存在しません。" .
							"関数名：" . $nowArray["CallBack"], 
							OD_LogicalException::FUNCTION_NOT_EXISTED);
					}
				} else {
					// 引数が配列じゃない
					throw new OD_LogicalException($errMsg . 
						"引数の配列構造が不正です。" . 
						"Attribute設定用配列は、多次元配列である必要があります。"
						, OD_LogicalException::INVALID_PARAM);
				}
			}
		}
		return $ret;
	}



	/**
	* htmlタグのペアを作成する
	* タグ名やテキストからhtmlタグを作成します。属性部分の引数が型に沿ってない場合、無視されます。
	* @param string タグ名
	* @param string タグに挟まれる内部テキスト
	* @param string 属性を表す配列
	* @param boolean 開始タグ、終了タグ、内部テキストで改行・インデントするかどうか
	* @return string htmlコード
	*/
	public static function tags($tagName, $innerText, $attrArray = null, $multiLine = false)
	{
		// 改行部分
		if ($multiLine) {
			$newLine = "\n";
			// タブの追加
			$innerText = preg_replace("/\n([^\n]+)/", "\n\t$1", $innerText);
			$innerText = "\t" . $innerText;
		} else {
			$newLine = "";
		}

		// 属性部分
		$attrStr = '';
		if (!is_null($attrArray)) {
			if (is_array($attrArray)) {
				// 配列の場合
				foreach ($attrArray as $key => $value) {
					$attrStr .= $key . '="' . $value . '" ';
				}
				$attrStr = mb_substr($attrStr, 0, mb_strlen($attrStr) - 1);
				$attrStr = " " . $attrStr;
			} elseif (is_string($attrArray)) {
				//　文字列の場合
				$attrStr = " " . $attrArray;
			}
		}

		// コード作成
		$retCode = "<" . $tagName . $attrStr . ">" . $newLine . $innerText . "</" . $tagName .">\n";

		return $retCode;
	}

} // end of class
?>
