<?php


/**
* カスタム例外クラス
*
* クラスの説明(詳細)
* @author	Daisuke ODA
* @copyright	2012 althi.Inc
* @version	バージョン
* @link	URL キャプション
* @uses	elementName
*/

class OD_LogicalException extends Exception
{

	/**
	* カスタム・エラーコード
	* @const HAS_UNSETED_PARAM = 1000
	*/
	const HAS_UNSETED_PARAM = 1000; // セットされてない引数がある
	const UNEXPECTED_PARAM_TYPE = 1010; // 引数型が想定外
	const INVALID_PARAM = 1020; // 引数が不正
	const UNEXPECTED_ARRAY_SCHEME = 1030; // 配列の構造が不正


	const CANT_CREATE_ARRAY = 2000; // 配列を作成できない
	const FUNCTION_NOT_EXISTED = 3000; // 存在しない関数を指定した
}

?>

