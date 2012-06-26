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

class OD_FileDirException extends Exception
{

	/**
	* カスタム・エラーコード
	*/
	const CANNOT_OPEN_FILE = 1000; // ファイルを開けない
	const UNEXPECTED_FILE_TYPE = 2000; // ファイルの種類が不正
}

?>

