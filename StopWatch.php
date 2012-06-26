<?php
/**
* ストップウォッチ(処理時間計測用)のクラス
*
* startメソッドからstopメソッドまでの時間を秒(小数点以下5桁まで)で返します。
*@author ODA Daiuke
*@copyright 2012 althi.Inc
*@version
*/

class OD_StopWatch
{
	protected $strTime = array();
	protected $endTime = array();

	/**
	 * 現在の時刻(Unixタイムスタンプ)を秒で返す
	 * @return float 現在のタイムスタンプ
	 */
	protected function getMicrotime()
	{
		list($msec, $sec) = explode(" ", microtime());
		return (float)$sec + (float)$msec;
	}

	/**
	 * 測定開始
	 * @param string ラベル。指定する事で複数チャンネルでの測定が可能です。
	 */
	public function start($lapLabel = null)
	{
		static $srtLapCnt = -1;
		if (is_null($lapLabel)) {
			$srtLapCnt++;
			$lapLabel =  $srtLapCnt;
		}
		$this->strTime[$lapLabel] = $this->getMicrotime();
	}

	/**
	 * 測定終了
	 * @param string ラベル。指定されたチャンネル(ラベル)の測定を終了します。指定する事で複数チャンネルでの測定が可能です。
	 */
	public function stop($lapLabel = null)
	{
		static $endLapCnt = -1;
		if (is_null($lapLabel)) {
			$endLapCnt++;
			$lapLabel =  $endLapCnt;
		}
		$this->endTime[$lapLabel] = $this->getMicrotime();
	}

	/**
	 * 測定結果を取得
	 * @param string ラベル。指定されたチャンネル(ラベル)の測定を終了します。指定する事で複数チャンネルでの測定が可能です。
	 * @return float 時間をミリ秒(小数点5桁で)返します。
	 */
	public function getTime($lapLabel = null)
	{
		static $getLapCnt = -1;
		if (is_null($lapLabel)) {
			$getLapCnt++;
			$lapLabel =  $getLapCnt;
		}
		if (isset($this->strTime[$lapLabel]) && isset($this->endTime[$lapLabel])){
			$strSec = $this->strTime[$lapLabel];
			$endSec = $this->endTime[$lapLabel];
			return sprintf("%.5f",$endSec - $strSec);
		} else {
			return null;
		}

	}

	/**
	* startとstopがセットされているlapラベルを配列で返す
	* @return array セットされているlapラベル
	*/
	public function getLapLabel()
	{
		$ret = array();
		foreach ($strTime as $key => $value) {
			if (array_key_exists($key, $endTime)) {
				$ret[] = $key;
			}
		}
		return $ret;
	}

}
?>

