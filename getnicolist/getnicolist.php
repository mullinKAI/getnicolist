<?php
// 日本語化
define("エンコード", "UTF-8");
ini_set("default_charset", エンコード);
mb_language("ja");
mb_internal_encoding(エンコード);
mb_http_output(エンコード);

// エラー表示
ini_set("display_errors", 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// inputデータのクリーニング（使うかもしれないので一応file以外全部）
$_POST = NULLバイト削除($_POST);
$_GET = NULLバイト削除($_GET);
$_REQUEST = NULLバイト削除($_REQUEST);
$_COOKIE = NULLバイト削除($_COOKIE);

function NULLバイト削除($target)
{
    if (is_array($target)) {
        return array_map('NULLバイト削除', $target);
    }
    return str_replace( "\0", "", $target);
}

// マイリストIDの取り出し
$mylistid_array = explode(",", $_GET["mylist"]);
$datalist = array();

// データ取得
foreach ($mylistid_array as $key => $mylistid) {
    if ($xml = XML配列取得($mylistid)) {
        if ($data = マイリスト情報取り出し($xml)) {
            $datalist[$key] = $data;
        }
    }
    if (!isset($datalist[$key]) OR !$datalist[$key]) {
        // データが取れなければ即終了
        exit("ID=" .$mylistid ."は取得できませんでした");
    }
}

function XML配列取得($mylistid)
{
    $return = array();
    // URLの作成とパラメータ追加
    $url = "http://www.nicovideo.jp/mylist/" .$mylistid ."?rss=2.0&nodescription=1&noinfo=1";
    // file_get_contentsで取得、simplexml_load_stringで解析、XMLを配列化
    if ($contents = @file_get_contents($url)) {
        if ($xmlobj = @simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOCDATA)) {
            if ($xml = json_decode(json_encode($xmlobj), true)) {
                $return = $xml;
            }
        }
    }
    return $return;
}

function マイリスト情報取り出し($xml)
{
    $return = array();
    $itemdata = array();
    if ($xml && isset($xml["channel"])) {
        // タイトルとかを取り出す
        $return["title"]   = $xml["channel"]["title"];
        $return["link"]    = $xml["channel"]["link"];
        $return["pubDate"] = $xml["channel"]["pubDate"];
        
        // itemの中身を取り出す
        $items = $xml["channel"]["item"];
        // １個しかない
        if (isset($items["title"])) {
            $itemdata[] = アイテムの取り出し($items);
            
        // 複数ある
        } elseif (is_array($items)) {
            foreach ($items as $item) {
                $itemdata[] = アイテムの取り出し($item);
            }
        }
        $return["items"] = $itemdata;
    }
    return $return;
}

function アイテムの取り出し($item)
{
    $return = array();
    if (isset($item["title"]) && isset($item["link"]) && isset($item["guid"]) && isset($item["description"])) {
        $return["title"]   = $item["title"];
        $return["link"]    = $item["link"];
        //guidから投稿日が取れそう
        list($tag, $tagid, $return["upDate"], $uri) = explode(",", str_replace(":", ",", $item["guid"]));
        
        // descriptionにはコメントとか色々がHTML形式で入ってるので必要なものだけ取り出す
        // [0]がマイリストコメント。[1]がサムネのイメージタグ
        $descriptions = preg_split('/<[^>]*[^\/]>/i', $item["description"], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $return["comment"] = $descriptions[0];
        
        // イメージタグにクラス属性付けて見やすくする
        $return["img_tag"] = str_replace('/>', ' class="float" />', $descriptions[1]);
    }
    return $return;
}

// outputデータのクリーニング
/**
 * htmlspecialcharsラップ
 */
function h($str){ echo htmlspecialchars($str, ENT_QUOTES, エンコード);}

/**
 * htmlspecialcharsしないでprint
 */
function p($str){ echo $str;}

/**
 * urlencodeラップ。最終的にエンティティするのでここではechoしない
 */
function ue($str){ return urlencode($str);}

?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<title>ニコニコのマイリストIDをカンマ区切りで複数渡すとリストにしてくれるやつ</title>
<style type="text/css">
.float{ float: left; margin-top: 0; margin-right: 1em;}
.floatclear{ clear: both;}
.smallitalic{ font-size:small;font-style:italic;}
article{ line-height:150%;}
</style>
</head>
<body>
<div id="bodywrapper">
<header>
    <h1>ニコニコのマイリストIDをカンマ区切りで複数渡すとリストにしてくれるやつ</h1>
    <span class="smallitalic">マイリストIDは&quot;mylist&quot;パラメータで指定する</span>
</header>
<section>
<?php foreach ($datalist as $data) {?>
    <article>
<h3><a href="<?php h($data['link']); ?>" target="_blank"><?php h($data['title']); ?></a></h3>
<span>最終更新日：<?php h(date("Y-m-d", strtotime($data['pubDate']))); ?></span><br /><br />
<?php foreach ($data['items'] as $key => $value) {
        ?><div><?php p($value['img_tag']); ?><a href="<?php h($value['link']); ?>" target="_blank"><?php h($value['title']); ?></a><br /><?php h($value['comment']); ?><span class="smallitalic"><br />投稿：<?php h($value['upDate']); ?></span><br class="floatclear"/></div>
<?php }?>
    </article>
<?php }?>
</section>
</div id="bodywrapper">
</body>
</html>