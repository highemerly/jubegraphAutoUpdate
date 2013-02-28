<?php

/****************
jubegraphAutoUpdate.php
コナミのe-amusement gateにログインして，jubeatのプレイデータを取得した後，jubegraphに自動アップデートするスクリプト（saucer対応）
Date: 2012/6/4
Author: はん@highemerly
Required: php5.3.3 later, pear(HTTP_Client)

実行方法：第一引数にライバルIDを指定して実行
メモ:
・最初のdefineだけ変更すれば，どのユーザでも使えるように設定されています
・musicページは4ページに固定してあるので，適当に変更する必要があります
・特にデバッグ時コナミサーバにアクセスする回数を減らすため，htmlデータは一旦ファイルとして保存します．そのためフォルダ内の書き込み権限が必要です．
*****************/

require_once "HTTP/Client.php"; 
date_default_timezone_set("Asia/Tokyo");

//パラメタ設定
define('EAGATE_ID','*******');
define('EAGATE_PASS','********');

//ライバルIDの読み込みn設定
if ($argc == 2){
  $jubegraphFid = $argv[1];
}
else{
	die('Error: 実行引数が不正です．管理者に連絡してください．');
}

//URL設定
$eagateLoginURL = "https://p.eagate.573.jp/gate/p/login.html";
$eagatePlayerURL = "http://p.eagate.573.jp/game/jubeat/saucer/p/playdata/index.html";
$eagateMusicURLBase = "http://p.eagate.573.jp/game/jubeat/saucer/p/playdata/music.html?page=";

$jubegraphTopURL = "http://jubegraph.dyndns.org/jubeat_saucer/";
$jubegraphLoginURL = "http://jubegraph.dyndns.org/jubeat_saucer/registFile.cgi";
$jubegraphSubmitURL = "http://jubegraph.dyndns.org/jubeat_saucer/registData.cgi";


//ここから本体
$client =& new HTTP_Client();

echo "Access to e-AMUSEMENT gate Login Page...\n";
$client->get($eagateLoginURL); //ログインページにアクセス
$eagateLoginArray = array( array("KID" => EAGATE_ID, "pass" => EAGATE_PASS), "OTP", "submit");
echo "Try to Login...\n";
$client->post($eagateLoginURL,$eagateLoginArray); //ログイン（post）
//echo $client->currentResponse();
echo "Login done.\n";

//ページ読み込み
echo "Access to Player Page...\n";
$client->get($eagatePlayerURL); //プレイヤページ（get）
$eagatePlayerData = $client->currentResponse(); //プレイヤページ


if (mb_strpos($eagatePlayerData['body'],"ただいま大変混み合っております｡申し訳ございませんがしばらくたってから再度お試しください｡") === false){
	$fp = fopen($jubegraphFid."top.html", "w");
	fwrite($fp, $eagatePlayerData['body']);//注意: 他人のページでもうまくいかせるためのアレだったけど，結局あきらめた
	fclose($fp);
}

else{
	die("Error: ただいま大変混み合っております｡申し訳ございませんがしばらくたってから再度お試しください｡");
}

for($i=1;$i<=4;$i++){
	$eagateMusicURL = $eagateMusicURLBase.$i;	
	echo "Access to Music Page ".$i."...\n";

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

echo "Success (e-AMUSEMENT gate).\n";

//****ここからjubegraph****

echo "Access to jubegraph TOP Page...\n";
$client->get($jubegraphTopURL); //ログインページにアクセス
$jubegraphFile = array(
	array('playerData', $jubegraphFid."top.html", "text/html"), 
	array('musicData1', $jubegraphFid."m1.html", "text/html"),
	array('musicData2', $jubegraphFid."m2.html", "text/html"),
	array('musicData3', $jubegraphFid."m3.html", "text/html"),
	array('musicData4', $jubegraphFid."m4.html", "text/html")
);

echo "Try to submit to jubegraph...\n";
//jubegraphにsubmit
$client->post($jubegraphLoginURL,"submit","",$jubegraphFile); //jubegraphにhtmlファイルをアップロード(post)
$jubegraphCheckData = $client->currentResponse();
$jubegraphCheckData['body'] = mb_convert_encoding($jubegraphCheckData['body'],"UTF-8","auto");

//jubegraphからの出力をチェックして，データが正しいかを確認する
if (mb_strpos($jubegraphCheckData['body'], "取得できていない情報があります") === false 
	&& mb_strpos($jubegraphCheckData['body'], "曲データがありません") === false 
	&& mb_strpos($jubegraphCheckData['body'], "前回登録時とプレイTUNE数が同じです") === false
	&& mb_strpos($jubegraphCheckData['body'], "最後の登録から10分は新たにデータを登録できません") === false
	&& mb_strpos($jubegraphCheckData['body'], "データがなにかおかしいです") === false){

	echo "Success (First Submit to jubegraph).\n";

	//jubegraphのsubmitボタンを押すために，hidden属性のパラメタを取得する
	//	$pattern = "/.*<input type=\"hidden\" name=\"fid\" value=\"([0-9]+)\">.*/s";
	//	$jubegraphFid = preg_replace($pattern,"$1",$jubegraphCheckData['body']);
	$pattern = "/.*<input type=\"hidden\" name=\"time\" value=\"([0-9_]+)\">.*/s";
	$jubegraphTime = preg_replace($pattern,"$1",$jubegraphCheckData['body']);
	//	echo $jubegraphFid.",".$jubegraphTime."\n";

	$jubegraphSubmitArray = array(array("rid" => $jubegraphFid, "time" => $jubegraphTime), "submit");
	$client->post($jubegraphSubmitURL,$jubegraphSubmitArray); //submitボタンを押す

	$jubegraphSubmitData = $client->currentResponse();
	$jubegraphSubmitData['body'] = mb_convert_encoding($jubegraphSubmitData['body'],"UTF-8","auto");
	//	echo $jubegraphSubmitData['body'];

	echo "Success (Final Submit to jubegraph)\n";
	echo $jubegraphTime."更新 http://jubegraph.dyndns.org/jubeat_saucer/score.cgi?rid=".$jubegraphFid."\n";
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
	else if (!(mb_strpos($jubegraphCheckData['body'], "データがなにかおかしいです") === false)){
		$errorMessage =  'なにかがおかしいです。不明なエラーです。';
	}
	else{
		$errorMessage = '不明なエラーです．管理者に連絡してください．';
	}
	die("Error: ".$errorMessage);	

}

?>
