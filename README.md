# PukiWiki用プラグイン<br>タブ表示 tab.inc.php

ページをタブ表示する[PukiWiki](https://pukiwiki.osdn.jp/)用プラグイン。  
タブをクリックすると該当ページをロードして表示を差し替えます。タブをダブルクリックすると該当ページのURLに遷移します。

|対象PukiWikiバージョン|対象PHPバージョン|
|:---:|:---:|
|PukiWiki 1.5.3 ~ 1.5.4RC (UTF-8)|PHP 7.4 ~ 8.1|

## インストール

下記GitHubページからダウンロードした tab.inc.php を PukiWiki の plugin ディレクトリに配置してください。

[https://github.com/ikamonster/pukiwiki-tab](https://github.com/ikamonster/pukiwiki-tab)

## 使い方

```
#tab([ラベル1>]ページ名[,[ラベル2>]ページ名2][,...])
```

ラベルとページ名の組をカンマで区切って必要なだけ羅列する。  
ページ名はタブ表示したいページの名前（Hoge、Fuga/Piyo等）。  
ラベルを省略するとページ名がタブのラベルとなる。

## 使用例

```
#tab(プロフィール>Profile,履歴>History,連絡先>Contact)
```

## ご注意

- 本プラグインを挿入できるのは1ページにつき1箇所のみです。
- ループする恐れがあるため、タブの入れ子、つまりタブで読み込むページ内にさらにタブを表示することはできません（強制的に無効化される）。
- 注釈表示領域にはタブで読み込まれたページの注釈が表示され、本プラグインを挿入した元ページの注釈は表示されません。

## 設定

ソース内の下記の定数で動作を制御することができます。

|定数名|値|既定値|意味|
|:---|:---:|:---|:---|
|PLUGIN_TAB_RESTRICT|0 or 1|0|本プラグインの実行を凍結／編集制限ページ内または PKWK_READONLY 下に制限する|
|PLUGIN_TAB_ALLOW_DOUBLECLICK|0 or 1|1|該当ページのURLに遷移するタブダブルクリック機能を許可|
|PLUGIN_TAB_TIMEOUT|数値|10000|ページをロードする際のタイムアウト時間（ミリ秒）。0なら設定せず|
|PLUGIN_TAB_ALLOW_DEFAULTSTYLE|0 or 1|1|タブに既定のスタイルを適用|
|PLUGIN_TAB_NOTEID|文字列|'note'|注釈表示ブロック要素のID|
|PLUGIN_TAB_NOCACHE|0 or 1|1|ロードするページ情報のブラウザーキャッシュを明示的にオフ|
