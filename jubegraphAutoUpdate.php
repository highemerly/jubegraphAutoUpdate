<?php

/****************
jubegraphAutoUpdate
コナミのe-amusement gateにログインして，jubeatのプレイデータを取得した後，jubegraphに自動アップデートするスクリプト

Date: 2012/6/4
Author: はん@highemerly (highemerly@me.com)
Required: php5.3.3 later, pear(HTTP_Client)

・最初のdefineだけ変更すれば，どのユーザでも使えるように設定されています
・musicページは4ページに固定してあるので，適当に変更する必要があります
・コナミサーバにアクセスする回数を減らすため，htmlデータは一旦ファイルとして保存します．そのためフォルダ内の書き込み権限が必要です．
*****************/

require_once "HTTP/Client.php"; 
date_default_timezone_set("Asia/Tokyo"); //HTTP_Clientを使うために必要．php.ini側で適切に設定して入れば必要なし

//パラメタ設定
define('EAGATE_ID','highemerly');
define('EAGATE_PASS','6144shige');

//ライバルIDの読み込みn設定
if ($argc == 2){
	$jubegraphFid = $argv[1];
}
else{
	die('Error: 実行引数が不正です．管理者に連絡してください．');
}

//URL設定
$eagateLoginURL = "https://p.eagate.573.jp/gate/p/login.html";
$eagatePlayerURL = "http://p.eagate.573.jp/game/jubeat/copious/p/playdata/index_other.html?rival_id=".$jubegraphFid;
$eagateMusicURLBase = "http://p.eagate.573.jp/game/jubeat/copious/p/playdata/music.html?rival_id=".$jubegraphFid."&page=";

$jubegraphTopURL = "http://jubegraph.dyndns.org/jubeat_copious/";
$jubegraphLoginURL = "http://jubegraph.dyndns.org/jubeat_copious/cgi/registFile.cgi";
$jubegraphSubmitURL = "http://jubegraph.dyndns.org/jubeat_copious/cgi/registData.cgi";


//ここから本体
$client =& new HTTP_Client();

echo "eagate Login Page...\n";
$client->get($eagateLoginURL); //ログインページにアクセス
$eagateLoginArray = array( array("KID" => EAGATE_ID, "pass" => EAGATE_PASS), "OTP", "submit");
echo "Login...\n";
$client->post($eagateLoginURL,$eagateLoginArray); //ログイン（post）
//echo $client->currentResponse();

//ページ読み込み
echo "Player Page...\n";
$client->get($eagatePlayerURL); //プレイヤページ（get）
$eagatePlayerData = $client->currentResponse(); //プレイヤページ


if (mb_strpos($eagatePlayerData['body'],"ただいま大変混み合っております｡申し訳ございませんがしばらくたってから再度お試しください｡") === false){
	$fp = fopen($jubegraphFid."top.html", "w");
	fwrite($fp, $eagatePlayerData['body']."<dt class=\"pd_tit2\">ライバルID</dt><dd>".$jubegraphFid."</dd>");//注意: 他人のページでもうまくいかせるためのアレ
	fclose($fp);
}
	
	//<dt class="pd_tit2">ライバルID</dt><dd>24400001958741</dd>
	
else{
	die("Error: ただいま大変混み合っております｡申し訳ございませんがしばらくたってから再度お試しください｡");
}

for($i=1;$i<=4;$i++){
	$eagateMusicURL = $eagateMusicURLBase.$i;	
	echo "Music".$i." Page...(".$eagateMusicURL.")\n";
	$client->get($eagateMusicURL);
	$eagateMusicData = $client->currentResponse();
	$filename = $jubegraphFid."m".$i.".html";

	if (mb_strpos($eagateMusicData['body'],"ただいま大変混み合っております｡申し訳ございませんがしばらくたってから再度お試しください｡") === false){
		$fp = fopen($filename, "w");
		fwrite($fp, $eagateMusicData['body']);
		fclose($fp);
	}
	else{
		die("Error: ただいま大変混み合っております｡申し訳ございませんがしばらくたってから再度お試しください｡");
	}
}//for loop



//****ここからjubegraph****

//$client->setConfig(array(
//    'useragent'      => 'Zend_Http_Client, twitter: highemerly'));

echo "jubegraph TOP Page...\n";
$client->get($jubegraphTopURL); //ログインページにアクセス
$jubegraphFile = array(
	array('playerData', $jubegraphFid."top.html", "text/html"), 
	array('musicData1', $jubegraphFid."m1.html", "text/html"),
	array('musicData2', $jubegraphFid."m2.html", "text/html"),
	array('musicData3', $jubegraphFid."m3.html", "text/html"),
	array('musicData4', $jubegraphFid."m4.html", "text/html")
	);

//jubegraphにsubmit
$client->post($jubegraphLoginURL,"submit","",$jubegraphFile); //jubegraphにhtmlファイルをアップロード(post)
$jubegraphCheckData = $client->currentResponse();
$jubegraphCheckData['body'] = mb_convert_encoding($jubegraphCheckData['body'],"UTF-8","auto");
//echo $jubegraphCheckData['body'];

//jubegraphからの出力をチェックして，データが正しいかを確認する
if (mb_strpos($jubegraphCheckData['body'], "取得できていない情報があります") === false 
	&& mb_strpos($jubegraphCheckData['body'], "曲データがありません") === false 
	&& mb_strpos($jubegraphCheckData['body'], "前回登録時とプレイTUNE数が同じです") === false
	&& mb_strpos($jubegraphCheckData['body'], "最後の登録から10分は新たにデータを登録できません") === false){
	
	echo "Updating jubegraph...\n";

	//jubegraphのsubmitボタンを押すために，hidden属性のパラメタを取得する
//	$pattern = "/.*<input type=\"hidden\" name=\"fid\" value=\"([0-9]+)\">.*/s";
//	$jubegraphFid = preg_replace($pattern,"$1",$jubegraphCheckData['body']);
	$pattern = "/.*<input type=\"hidden\" name=\"time\" value=\"([0-9_]+)\">.*/s";
	$jubegraphTime = preg_replace($pattern,"$1",$jubegraphCheckData['body']);
	//	echo $jubegraphFid.",".$jubegraphTime."\n";

	$jubegraphSubmitArray = array(array("fid" => $jubegraphFid, "time" => $jubegraphTime), "submit");
	$client->post($jubegraphSubmitURL,$jubegraphSubmitArray); //submitボタンを押す

	//	$jubegraphSubmitData = $client->currentResponse();
	//	$jubegraphSubmitData['body'] = mb_convert_encoding($jubegraphSubmitData['body'],"UTF-8","auto");
	//	echo $jubegraphSubmitData['body'];

	echo $jubegraphTime."更新 jubegraph http://jubegraph.dyndns.org/jubeat_copious/user/".$jubegraphFid."/\n";
}
else{

	//エラー処理
	if (!(mb_strpos($jubegraphCheckData['body'], "前回登録時とプレイTUNE数が同じです") === false)){
		$errorMessage = '前回登録時とプレイTUNE数が同じっぽいので，ゲーセンに行ってください．';
	}
	else if (!(mb_strpos($jubegraphCheckData['body'], "取得できていない情報があります") === false)){
		$errorMessage = "取得できていない情報があります．たぶんe-amusement gateが重いです．";
	}
	else if (!(mb_strpos($jubegraphCheckData['body'], "曲データがありません") === false)){
		$errorMessage =  '曲データがありません．たぶんe-amusement gateが重いです．';
	}
	else if (!(mb_strpos($jubegraphCheckData['body'], "最後の登録から10分は新たにデータを登録できません") === false)){
		$errorMessage =  '最後の登録から10分は新たにデータを登録できません．これはjubegraphの仕様です．';
	}
	else{
		$errorMessage = '不明なエラーです．管理者に連絡してください．';
	}
	//	echo "Error: ".$errorMessage;
	die("Error: ".$errorMessage);	

}

?>