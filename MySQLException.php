<?php


/**
* MySQL カスタム例外クラス
*
* エラーコード
* - -------------------------------------------------------
* - DB_NOT_CONNECTED	データベースへ接続が確立されていない
* - DB_LINK_INVALID	コネクションリンクが不正
* - REC_COUNT_INVALID	結果のレコード数が不正
* - REC_COUNT_ZERO	結果のレコード数がゼロ
* - REC_COUNT_NOTONE	結果のレコード数が複数
* - SQL_KINDS_INVALID	SQLの種類(SELECT、INSERTなど)が不正
* - UNKNOWN_DATATYPE_NAME	リストにないデータタイプ
* - -------------------------------------------------------
*
* @author	Daisuke ODA
* @copyright	2012 althi.Inc
* @version	バージョン
* @link	URL キャプション
* @uses	elementName
*/

class OD_MySQLException extends Exception
{

	/**
	* カスタム・エラーコード
	*/
	// ---------------------------------------
	// 接続関連
	// ---------------------------------------
	const DB_NOT_CONNECTED = 4000;
		//データベースへ接続が確立されていません。
	const DB_LINK_INVALID = 4010;
		//コネクション・リンクが不正です。

	const CANT_SELECT_DB = 4020;
		// データベースが選択できません。データベース名が指定されていません。
	// ---------------------------------------
	// レコードセット関連
	// ---------------------------------------
	const REC_COUNT_INVALID = 4100; 
		//結果のレコード数が不正です。

	const REC_COUNT_ZERO = 4110;
		//"結果のレコード数が0です。

	const REC_COUNT_NOTONE = 4120;
		//"結果のレコード数が1ではありません。"
	
	// ---------------------------------------
	// SQL関連
	// ---------------------------------------
	const SQL_KINDS_INVALID = 4200; 
		//SQLの種類(SELECT、INSERTなど)が不正です。
		//SQLの種類(SELECT、INSERTなど)が不正です。
		//SELECT文ではないSQLが発行された可能性があります。

	const INVALID_VALUE_MAKING_SQL = 4210; 
		// SQL作成適していない要素が発見されました。キャストできません。
	// ---------------------------------------
	// データ型関連
	// ---------------------------------------
	const UNKNOWN_DATATYPE_NAME = 4300;
		// リストにない(想定されてない)データタイプです。
		// データ型を判別できません。

	// ---------------------------------------
	// フィールド関連
	// ---------------------------------------
	const NONEXIST_FIELD_INDICATED  = 4400;
		//  "指定されたフィールドが存在しません。"
		// "結果がresource(mysql result)型ではありません。SELECT文ではないSQLが発行された可能性があります。",

	const NESS_FIELD_LACKS = 4410;
		// "必須フィールドが欠けています。"
		// "与えられた結果レコードに必須フィールドが欠けています。"

	// ---------------------------------------
	// 値関連
	// ---------------------------------------
	const UNEXPECTED_VALUE_FETCHED = 4500;
		// "取得した値が、YES/NO以外です。"

	// ---------------------------------------
	// その他
	// ---------------------------------------
	const INTERNAL_AND_FETCHED_MISMATCH  = 4900;
		// 内部データと取得データが一致しません。 
		// 取得したデータベース名と現在のプロパティの値が一致しません。




}
?>

