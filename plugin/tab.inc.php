<?php
/*
PukiWiki - Yet another WikiWikiWeb clone.
tab.inc.php, v1.3.1 2020 M.Taniguchi
License: GPL v3 or (at your option) any later version

ページをタブ表示するプラグイン。

タブをクリックすると該当ページをロードして表示を差し替えます。
タブをダブルクリックすると該当ページのURLに遷移します。

【使い方】
#tab([ラベル1>]ページ名[,[ラベル2>]ページ名2][,...])

ラベルとページ名の組をカンマで区切って必要なだけ羅列する。
ページ名はタブ表示したいページの名前（Hoge、Fuga/Piyo等）。
ラベルを省略するとページ名がタブのラベルとなる。

【使用例】
#tab(プロフィール>Profile,履歴>History,連絡先>Contact)

【制約】
・本プラグインを挿入できるのは1ページにつき1箇所のみです。
・ループする恐れがあるため、タブの入れ子、つまりタブで読み込むページ内にさらにタブを表示することはできません（強制的に無効化される）。
・注釈表示領域にはタブで読み込まれたページの注釈が表示され、本プラグインを挿入した元ページの注釈は表示されません。
・JavaScriptが有効でないと動作しません。
*/

/////////////////////////////////////////////////
// タブ表示プラグイン設定（tab.inc.php）
if (!defined('PLUGIN_TAB_RESTRICT'))           define('PLUGIN_TAB_RESTRICT',           0);     // 本プラグインの実行を凍結／編集制限ページ内またはPKWK_READONLY下に制限する
if (!defined('PLUGIN_TAB_ALLOW_DOUBLECLICK'))  define('PLUGIN_TAB_ALLOW_DOUBLECLICK',  1);     // 該当ページのURLに遷移するタブダブルクリック機能を許可
if (!defined('PLUGIN_TAB_TIMEOUT'))            define('PLUGIN_TAB_TIMEOUT',            10000); // ページをロードする際のタイムアウト時間（ミリ秒）。0なら設定せず
if (!defined('PLUGIN_TAB_ALLOW_DEFAULTSTYLE')) define('PLUGIN_TAB_ALLOW_DEFAULTSTYLE', 1);     // タブに既定のスタイルを適用
if (!defined('PLUGIN_TAB_NOTEID'))             define('PLUGIN_TAB_NOTEID',            'note'); // 注釈表示ブロック要素のID
if (!defined('PLUGIN_TAB_NOCACHE'))            define('PLUGIN_TAB_NOCACHE',            1);     // ロードするページ情報のブラウザーキャッシュを明示的にオフ


function plugin_tab_convert() {
	global	$vars, $defaultpage, $foot_explain;

	// JavaScript無効なら中断
	if (!PKWK_ALLOW_JAVASCRIPT) return '';

	// 引数がなければ中断
	$arg = func_get_args();
	if (!$arg) return '';

	// 二重起動なら中断
	static	$included = false;
	if ($included) return '';
	$included = true;

	// 制限あり？
	if (PLUGIN_TAB_RESTRICT) {
		global $auth_user;
		$backup = $auth_user;
		$auth_user = '';	// 非認証ユーザーのふり
		$result = (PKWK_READONLY || !is_editable($vars['page']) || !is_page_writable($vars['page']));	// 制限付きページか判定
		$auth_user = $backup;
		if (!$result) return '';	// 誰でも編集可能なページなら中断
	}

	// 引数を走査してタブページ名を取得
	$page = '';
	$tabs = '';
	foreach ($arg as $v) {
		if (strpos($v, '>') === false) {
			$v = trim($v);
			$v = array($v, $v);
		} else {
			$v = explode('>', $v);
			$v[0] = trim($v[0]);
			$v[1] = trim($v[1]);
		}
		$id = urlencode($v[1]);
		$tabs .= '<li id="PluginTab-' . $id . '" class="PluginTab" data-page="' . $id . '" onclick="__pluginTab__.change(this);" onkeypress="__pluginTab__.change(this);"' . ((PLUGIN_TAB_ALLOW_DOUBLECLICK)? ' ondblclick="__pluginTab__.move(this);"' : '') . ((!$page)? ' data-active="1"' : '') . ' tabindex="0">' . htmlsc(trim($v[0])) . '</li>';
		if (!$page) $page = $v[1];
	}
	$tabs = '<ul id="PluginTabs">' . $tabs . '</ul>';

	$page = urlencode($page);
	$noteId = (PLUGIN_TAB_NOTEID)? PLUGIN_TAB_NOTEID : 'note';

	// スタイル定義
	$style = <<<EOT
<style>
/* タブ領域 */
#PluginTabs {
	display: block;
	margin-bottom: 0;
	padding: 0;
	-webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
}
/* タブ */
.PluginTab {
	list-style: none;
	display: inline-block;
	min-width: 5em;
	padding: .3em .5em;
	box-sizing: border-box;
	border: 0 solid #808080;
	border-width: 1px 1px 0;
	border-radius: 8px 8px 0 0;
	text-align: center;
	cursor: pointer;
}
/* マウスオーバー */
.PluginTab:hover {
	text-decoration-line: underline;
	text-decoration-style: solid;
}
/* 選択中タブ */
.PluginTab[data-active='1'] {
	font-weight: bold;
	cursor: auto;
}
/* ページ表示領域 */
#PluginTabContent {
	margin-top: 1.5em;
}
/* その他調整 */
#{$noteId} { display:none };
</style>
EOT;

	// JavaScriptコード
	$script = get_script_uri();
	$method = 'GET';
	$timeout = PLUGIN_TAB_TIMEOUT;
	$jscode = <<<EOT
<script>/*<!--*/
'use strict';

const __PluginTab__ = function() {
	const	self = this;
	this.content = document.getElementById('PluginTabContent');	// ページ表示領域要素
	this.tabs = document.getElementsByClassName('PluginTab');	// タブ要素
	this.note = null;	// 注釈表示領域要素
	this.data = [];		// ページ情報

	// 最初のタブのページ情報はあらかじめ設定
//	this.data['{$page}'] = {$data};

	window.addEventListener('DOMContentLoaded', function(){
		// URLに「#タブページ名」指定あり？
		const path = location.href.split('#');
		if (path[1] != undefined && path[1]) {
			// ありならタブ切り替え
			const	ele = document.getElementById('PluginTab-' + path[1]);
			self.change(ele);
		} else {
			// ありならデフォルトタブページ表示
			self.loadPage('{$page}');
		}
		self.note = document.getElementById('{$noteId}');
		self.note.style.display = 'none';
	});
};

// タブ切り替え（タブクリックハンドラ）
__PluginTab__.prototype.change = function(ele) {
	if (!ele) return false;
	const	self = this;

	if (ele.getAttribute('data-active')) return;	// すでに選択中のタブなら無視
	const	page = ele.getAttribute('data-page');	// クリックされたタブに対応するページ名を取得

	// URLに「#タブページ名」を設定
	window.location.href = '#' + ((page != '{$page}')? page : '');

	// タブに選択中属性を設定
	for (let i = self.tabs.length - 1; i >= 0; --i) self.tabs[i].removeAttribute('data-active');
	ele.setAttribute('data-active', '1');

	this.loadPage(page);
};

// タブ表示
__PluginTab__.prototype.loadPage = function(page) {
	if (!page) return false;
	const	self = this;

	// ロード済みのページか？
	if (self.data[page] !== undefined) {
		// ロード済みページ情報を表示
		self.makeHTML(self.content, self.data[page]['body']);
		self.changeNote(self.data[page]['explain']);
	} else {
		// ページ情報をロードして表示
		const xhr = new XMLHttpRequest();
		xhr.open('{$method}', '{$script}?plugin=tab&refer=' + page);	// plugin_tab_action()へ要求
		xhr.responseType = 'json';
		if ({$timeout} > 0) xhr.timeout = Math.max({$timeout}, 1000);
		xhr.onload = function() {
			if (xhr.status == 200 && xhr.response) {
				self.data[page] = xhr.response;	// ページ情報を記憶しておき、次回からロードを省略する
				if (typeof self.data[page] === 'string') self.data[page] = JSON.parse(self.data[page]);	// IE対策
				self.makeHTML(self.content, self.data[page]['body']);
				self.changeNote(self.data[page]['explain']);
			}
		};
		xhr.send();
	}
};

// Script実行付きinnerHTML（注：document.write()には非対応）
__PluginTab__.prototype.makeHTML = function(element, html) {
	const regexp = /<script[^>]+?\/>|<script(.|\s)*?\/script>/gi;
	const scripts = html.match(regexp);
	if (scripts) {
		element.innerHTML = html.replace(regexp, '');
		scripts.forEach(function(script) {
			const	scriptElement = document.createElement('script');
			const	src = script.match(/<script[^>]+src=['"]?([^'"\s]+)[\s'"]?/i);
			if (src && src.length >= 1) {
				scriptElement.src = src[1];
				scriptElement.setAttribute('defer', 'defer');
			} else {
				scriptElement.text = script.replace(/<[\/]*?script>/gi, '');
			}
			element.appendChild(scriptElement);
		});
	} else {
		element.innerHTML = html;
	}
};

// ページ遷移（タブダブルクリックハンドラ）
__PluginTab__.prototype.move = function(ele) {
	const	page = ele.getAttribute('data-page');	// ダブルクリックされたタブに対応するページ名を取得
	window.location.href = '{$script}?' + page;	// 画面遷移
};

// 注釈書き換え
__PluginTab__.prototype.changeNote = function(data) {
	const	self = this;

	if (self.note) {
		let	explain = '';
		if (data) data.forEach(function(v){ explain += v; });
		if (explain) {
			self.note.innerHTML = '<hr class="note_hr"/>' + explain;
			self.note.style.display = 'block';
		} else {
			self.note.style.display = 'none';
			self.note.innerHTML = '';
		}
	}
};

const __pluginTab__ = new __PluginTab__();
/*-->*/</script>
EOT;

	$foot_explain = array(1 => '&#8203;');	// 注釈表示ブロックを生成させるためダミーの注釈を設定

	return ((PLUGIN_TAB_ALLOW_DEFAULTSTYLE)? $style : '') . $tabs . '<section id="PluginTabContent"></section>' . $jscode;
}


// URL指定呼び出し（タブ切り替え時にクライアントから呼ばれる）
function plugin_tab_action() {
	global $vars;
	header('Content-Type: application/json');
	if (PLUGIN_TAB_NOCACHE) header('Cache-Control: no-cache');
	echo plugin_tab_getPage($vars['refer']);
	exit;
}


// ページ情報JSON取得
function plugin_tab_getPage($page) {
	global $vars, $defaultpage, $foot_explain, $auth_type, $auth_user;

	$page = trim($page);
	if (!$page) $page = &$defaultpage;	// ページ名が空ならトップページ

	// 有効かつ権限があればページ内容を取得
	$body = '';
	if (is_page($page)) {
		if (check_readable($page, false, false)) {
			// 現在ページと取得ページが異なる？
			if ($page != $vars['page']) {
				$backup = unserialize(serialize($vars));	// HTTP引数をディープコピーで待避
				$vars['page'] = $page;	// 現在ページ名を変更してシステムを騙す
			}

			$body = get_source($page);	// ソースを取得
			foreach ($body as $i => $row) if (strpos($row, '#tab(') === 0) $body[$i] = '';	// ループ防止のため自プラグイン記述を探して無効化
			$body = convert_html($body);	// HTMLに変換

			if ($backup) $vars = $backup;	// HTTP引数を元に戻す
		} else
		if (exist_plugin_action('loginform') && (AUTH_TYPE_FORM === $auth_type || AUTH_TYPE_EXTERNAL === $auth_type || AUTH_TYPE_SAML === $auth_type) && !$auth_user) {
			$body = '<a href="./?plugin=loginform&pcmd=login&page=' . $page . '">Login required</a>';
		}
	}

	// JSONエンコード
	$json = json_encode(array('body' => $body, 'explain' => array_values($foot_explain)));
	return $json;
}
