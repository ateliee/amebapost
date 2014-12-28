# アメブロ外部連動用プログラム
アメブロの外部連動が終了したので、プログラムよりログインして外部投稿するプログラムを作成致しました。

## 使い方
```
    "require": {
        "ateliee/amebapost": "dev-master"
    }
```

```
use Ameba\AmebaPost;
...

$ameba = new AmebaPost('user_id','password');
$themes = $ameba->getThemeIds();
// edit post
if($list = $ameba->getEntry()){
    $id = key($list);
    $theme_id = key($themes);
    $ameba->updateEntry($id,'これはテストです','これはテストです。',$theme_id,AmebaPost::$PUBLISH,time(),0);
}
// new post
$theme_id = key($themes);
$ameba->insertEntry('テスト','これはテストです',$theme_id,AmebaPost::$PUBLISH,time(),0);

```