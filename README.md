主に単語で答えるゲームにおける辞書の構文解析・直列化を行うPHPライブラリ
===================================================================
次のゲームの辞書を構文解析し、相互に変換できるようにする API を提供します。

* [主に単語で答えるゲームにおける汎用的な辞書形式] \(以下、汎用辞書形式)
* [キャッチフィーリング]、[Drawing Catch] \(*.cfq)
* [きゃっちま] \(*.dat) ※暗号化後のファイルは扱えません
* [Inteligenceω] \(*.txt, *.zip) ※暗号化後のファイルは扱えません
* [ピクトセンス]

Inteligenceωの辞書で画像・音声ファイルへのパスが含まれる場合、
そのファイルが含まれるフォルダをdatファイルと一緒にアーカイブする必要があります。
同名のファイルが含まれる辞書 (同名のファイルを別々のフォルダに分けて格納したアーカイブ) は取り扱えません。

[主に単語で答えるゲームにおける汎用的な辞書形式]: https://github.com/esperecyan/dictionary/blob/master/dictionary.md
[キャッチフィーリング]: http://www.forest.impress.co.jp/library/software/catchfeeling/
[Drawing Catch]: http://drafly.nazo.cc/games/olds/DC
[きゃっちま]: http://vodka-catchm.seesaa.net/article/115922159.html
[ピクトセンス]: http://pictsense.com/
[Inteligenceω]: http://loxee.web.fc2.com/inteli.html

例
---
```php
<?php
require_once 'vendor/autoload.php';

use esperecyan\dictionary_php as dictionary;

$file = new \SplTempFileObject();
$file->fwrite(mb_convert_encoding(<<<'EOD'
% 選択問題
Q,2,,../images/sun.mp4
A,1,地球,カロン,太陽,\seikai

Q,0,仲間外れはどれでしょう
A,1,リンゴ,\seikai,ゴリラ,ラクダ,ダチョウ,\explain=選択肢を表示しなければ問題が成立しない場合。

% 答えが複数あり、どれか1つを選択すれば正解になる場合
Q,0,食べ物はどれでしょう (答えが複数ある場合はどれが1つだけ選択)
A,1,リンゴ,\seikai,ゴリラ,ラッパ,パン,\seikai

% 答えが複数あり、すべて選択する必要がある場合
Q,0,同じ種類のものを選びましょう
A,3,リンゴ,\seikai,ゴリラ,ラッパ,パン,\seikai

% 並べ替え問題
q,0,しりとりが成立するように並べ替えてください
% 問題行と解答行の間のコメント行と空行

a,2,リンゴ,1,パン,4,ゴリラ,2,ラッパ,3
EOD
, 'Windows-31J', 'UTF-8'));

$parser = new dictionary\Parser(null, '選択・並べ替え問題.txt');
$dictionary = $parser->parse($file);

$serializer = new dictionary\serializer\GenericDictionarySerializer();
$serializer->response($dictionary);
```

上の例の出力は以下となります。

```plain
text,image,answer,answer,description,specifics,question,option,option,option,option,type,@title
太陽,local/sun.png,太陽,,,,,地球,カロン,太陽,,selection,選択・並べ替え問題
リンゴ,,リンゴ,,選択肢を表示しなければ問題が成立しない場合。,,仲間外れはどれでしょう,リンゴ,ゴリラ,ラクダ,ダチョウ,selection,
「リンゴ」か「パン」,,リンゴ,パン,,,"食べ物はどれでしょう (答えが複数ある場合はどれが1つだけ選択)",リンゴ,ゴリラ,ラッパ,パン,selection,
「リンゴ」と「パン」,,リンゴ,パン,,require-all-right=,同じ種類のものを選びましょう,リンゴ,ゴリラ,ラッパ,パン,selection,
"リンゴ → ゴリラ → ラッパ → パン",,,,,,しりとりが成立するように並べ替えてください,リンゴ,ゴリラ,ラッパ,パン,selection,

```

インストール
------------
```sh
composer require esperecyan/dictionary-php logue/igo-php:@dev
```

Composer のインストール方法については、[Composer のグローバルインストール - Qiita]などをご覧ください。

[Composer のグローバルインストール - Qiita]: http://qiita.com/100/items/a1d73544c70fbfa7a643 "Composer は PEAR、および Pyrus に代わる新しい PHP ライブラリ管理システムです。"

要件
----
* PHP 7.1 以上
* php-mbstring ([mbstring拡張モジュール])
* php-intl ([Intl拡張モジュール])
* php-pecl-zip ([Zip拡張モジュール])
* php-pecl-imagick ([imagick (PECL拡張モジュール)])
* [logue/igo-php:@dev] ※[minimum-stability]が`dev`以外 (既定値は`stable`) の場合、依存関係は自動的に解決されないため、[上記インストール手順]のように手動でインストールする必要があります。

[mbstring拡張モジュール]: https://secure.php.net/manual/book.mbstring "mbstring はマルチバイト対応の文字列関数を提供し、PHP でマルチバイトエンコーディングを処理することを容易にします。"
[Intl拡張モジュール]: https://secure.php.net/manual/book.intl.php "国際化用拡張モジュール (Intl と略します) は ICU ライブラリのラッパーです。 PHP プログラマが、UCA 準拠の照合順序 (collation) や日付/時刻/数値/通貨のフォーマットを扱えるようにします。"
[Zip拡張モジュール]: https://secure.php.net/manual/book.zip "この拡張モジュールにより、ZIP 圧縮されたアーカイブとその内部のファイルに対する透過的な読み書きが可能となります。"
[imagick (PECL拡張モジュール)]: https://secure.php.net/manual/book.imagick "Imagick は、ImageMagick API を使用して画像の作成や修正を行う ネイティブ PHP 拡張モジュールです。"
[logue/igo-php:@dev]: https://packagist.org/packages/logue/igo-php "Morphological analysis engine 'Igo' porting and changed for PHP 5.3 and composer."
[minimum-stability]: http://kohkimakimoto.hatenablog.com/entry/2014/04/04/102125 "スタビリティの判断はルートパッケージのminimum-stabilityフィールドに基づいて行われる。これはルートオンリーだ。スタビリティフラグのデフォルト値を定義し、下限として振る舞う。"
[上記インストール手順]: #%E3%82%A4%E3%83%B3%E3%82%B9%E3%83%88%E3%83%BC%E3%83%AB

### 依存するライブラリ由来の要件
* PHP 64bit — [nelexa/zip](https://packagist.org/packages/nelexa/zip)

パブリックAPI
-------------
### [class esperecyan\dictionary_php\Parser(string $from = null, string $filename = null, string $title = null)](./src/Parser.php)
構文解析器。

#### `string $from = null`
変換元の辞書形式。`キャッチフィーリング` `きゃっちま` `Inteligenceω クイズ` `Inteligenceω しりとり` `ピクトセンス` `汎用辞書` のいずれか。

指定されていないか間違った値が指定されていれば、`$filename` から判断します。
その場合のInteligenceωについては、コメント行、空行を除く最初の行が `Q,` で始まるか否かで、クイズとしりとりを判別します。

#### `string $filename = null`
変換元のファイル名。

#### `string $title = null`
辞書のタイトル。

指定されていなければ、`$filename` から判断します。

汎用辞書形式で `@title` フィールドが存在する場合、この指定は無視されます。

#### [Dictionary esperecyan\dictionary_php\Parser#parse(SplFileInfo $file, bool $header = null, string\[\] $filenames = \[\])](./src/Parser.php#L78-L141)
##### `SplFileInfo $file`
変換元のファイルを[SplFileInfo]、またはその派生クラスで与えます。

[SplFileInfo]: https://secure.php.net/manual/class.splfileinfo

##### `bool $header = null`
変換元のファイルが `汎用辞書` の場合、ヘッダ行が存在すれば `true`、存在しなければ `false`、不明なら `null` を指定します。

##### `string[] $filenames = []`
変換元のファイルが `汎用辞書` の場合、`$file` にZIPファイルを与える代わりに、`$file` にCSVファイルを与えこの引数にファイル名のリストを与えることで、
「画像・音声・動画ファイルを含む場合のファイル形式」の構文解析できます。

#### 例外 [esperecyan\dictionary_php\exception\SyntaxException](./src/exception/SyntaxException.php)
SyntaxException#getMessage() から、ユーザーに示すエラーメッセージを取得できます。

| `$from`                  | 説明・例                                                             |
|--------------------------|----------------------------------------------------------------------|
| 共通                     | ファイル形式、符号化方式が間違っている。                             |
| 共通                     | 1つのフィールドの文字数が多過ぎる。                                  |
| 共通 (`汎用辞書`以外)    | `汎用辞書` に直列化可能なお題が一つも存在しなかった。                |
| `汎用辞書`               | 辞書全体の容量が大き過ぎる。                                         |
| `汎用辞書`               | 空のCSVファイルである。                                              |
| `汎用辞書`               | ヘッダ行に `text` というフィールドが存在しない。                     |
| `汎用辞書`               | ヘッダ行を超えるフィールド数の行が存在する。                         |
| `汎用辞書`               | textフィールドが存在しない行がある。                                 |
| `汎用辞書`               | 画像ファイルの容量が大き過ぎる。                                     |
| `汎用辞書`               | 音声ファイルの容量が大き過ぎる。                                     |
| `汎用辞書`               | 動画ファイルの容量が大き過ぎる。                                     |
| `汎用辞書`               | アーカイブに `dictionary.csv` という名前のファイルが含まれていない。 |
| `汎用辞書`               | アーカイブに含まれるファイルの名前が妥当でない。                     |
| `汎用辞書`               | アーカイブに含まれるファイルの形式が間違っている。                   |
| `汎用辞書`               | アーカイブに含まれるファイルの拡張子が正しくない。                   |
| `汎用辞書`               | アーカイブに含まれるファイルの数が多過ぎる。                         |
| `汎用辞書` <br> `ピクトセンス` | 符号化方式の検出に失敗した。                                   |
| `キャッチフィーリング`   | 空行がある。                                                         |
| `きゃっちま`             | コメントの前にスペースがある。                                       |
| `Inteligenceω しりとり` | 表示名の直後に難易度 (数値) が存在する。                             |
| `Inteligenceω しりとり` | 読み方にひらがな以外が含まれている。                                 |
| `Inteligenceω しりとり` | 読み方が設定されていないお題がある。                                 |
| `Inteligenceω クイズ`   | 出題の種類が数値になっていない。                                     |
| `Inteligenceω クイズ`   | 解答の種類が数値になっていない。                                     |
| `Inteligenceω クイズ`   | 画像クイズ、音声クイズでファイルが指定されていない。                 |
| `Inteligenceω クイズ`   | 問題オプションの値が数値になっていない。                             |
| `Inteligenceω クイズ`   | 選択問題で `\seikai` の前に選択肢が存在しない。                      |
| `Inteligenceω クイズ`   | 解答オプション `\bonus` の値が数値になっていない。                   |
| `Inteligenceω クイズ`   | 解答オプションの前に解答本体が存在しない。                           |
| `Inteligenceω クイズ`   | 記述問題で、`||` `[[` の前に解答本体が存在しない。                   |
| `Inteligenceω クイズ`   | 選択問題で `\seikai` が設定されていない。                            |
| `Inteligenceω クイズ`   | 問題行に対応する解答行が存在しない。                                 |
| `Inteligenceω クイズ`   | 解答行より前に問題行が存在する。                                     |
| `Inteligenceω クイズ`   | コメント、問題、解答のいずれにも該当しない行が存在する。             |
| `Inteligenceω クイズ`   | アーカイブ中に同名のファイルが含まれている。                         |
| `Inteligenceω クイズ`   | アーカイブ中に、拡張子が `.txt` のファイルが2つ以上含まれている。    |
| `Inteligenceω クイズ`   | アーカイブ中に、拡張子が `.txt` のファイル含まれていない。           |
| `Inteligenceω クイズ`   | アーカイブに含まれるファイルの形式が、汎用辞書と互換性がない。       |
| `Inteligenceω クイズ`   | アーカイブに含まれるファイルの拡張子が、汎用辞書と互換性がない。     |
| `Inteligenceω クイズ`   | アーカイブに含まれるファイルの数が多過ぎる。                         |
| `ピクトセンス`           | ワードにひらがな以外が含まれている。                                 |
| `ピクトセンス`           | 1ワードの文字数が多過ぎる。                                          |
| `ピクトセンス`           | 辞書のワード数が少な過ぎる、または多過ぎる。                         |
| `ピクトセンス`           | 辞書全体の文字数が多過ぎる。                                         |

#### ロギング
[esperecyan\dictionary_php\Parser]は[PSR-3: Logger Interface]の[Psr\Log\LoggerAwareInterface]を実装しています。

|`$from`                | ログレベル                | 説明・例                                                |
|-----------------------|---------------------------|---------------------------------------------------------|
| 共通 (`汎用辞書`以外) | Psr\Log\LogLevel::ERROR   | 1つのお題が `汎用辞書` に直列化可能な形式ではなかった。 |
| `汎用辞書`            | Psr\Log\LogLevel::ERROR   | 符号化方式がUTF-8でない。                               |
| `汎用辞書`            | Psr\Log\LogLevel::WARNING | 辞書全体の容量が大きい。                                |
| `汎用辞書`            | Psr\Log\LogLevel::WARNING | 画像ファイルの容量が大きい。                            |
| `汎用辞書`            | Psr\Log\LogLevel::WARNING | 音声ファイルの容量が大きい。                            |
| `汎用辞書`            | Psr\Log\LogLevel::WARNING | 動画ファイルの容量が大きい。                            |
| `汎用辞書`            | Psr\Log\LogLevel::WARNING | アーカイブに含まれるファイルの数が多い。                |
| `ピクトセンス`        | Psr\Log\LogLevel::ERROR   | 辞書名が空文字列である。                                |
| `ピクトセンス`        | Psr\Log\LogLevel::ERROR   | 辞書名が長過ぎる。                                      |

[esperecyan\dictionary_php\Parser]: ./src/Parser.php
[PSR-3: Logger Interface]: http://guttally.net/psr/psr-3/ "この文書では，ロギングライブラリのための共通インタフェースについて記述します。"
[Psr\Log\LoggerAwareInterface]: https://github.com/php-fig/log/blob/master/Psr/Log/LoggerAwareInterface.php

### [class esperecyan\dictionary_php\Serializer(string $to = '汎用辞書')](./src/Serializer.php)
直列化器。

#### `string $to = '汎用辞書'`
変換先の辞書形式。`キャッチフィーリング` `きゃっちま` `Inteligenceω クイズ` `Inteligenceω しりとり` `ピクトセンス` `汎用辞書` のいずれか。

指定されていないか間違った値が指定されていれば、`汎用辞書` になります。

#### [string\[\] esperecyan\dictionary_php\Serializer#serialize(Dictionary $dictionary, bool|string $csvOnly = false)](./src/Serializer.php#L21-L52)
次のような構造の連想配列で直列化したデータを返します。

- \[bytes] => 直列化したデータのバイナリ文字列
- \[type] => [MIME型] \(charsetパラメータなどをともなう)
- \[name] => ファイル名

[MIME型]: https://mimesniff.spec.whatwg.org/#mime-type

#### `Dictionary $dictionary`
辞書。

#### `bool|string $csvOnly = false`
`汎用辞書` `Inteligenceω クイズ` の場合、ZIPファイルの代わりにCSVファイル、txtファイルのみを返すときに真に設定します。
真の代わりに `https://example.ne.jp/dictionaries/1/files/%s` のような文字列を設定することで、`%s` をファイル名に置き換えて辞書ファイル中に記述します。

#### 例外 [esperecyan\dictionary_php\exception\SerializeExceptionInterface](./src/exception/SerializeExceptionInterface.php)
SerializeExceptionInterface#getMessage() から、ユーザーに示すエラーメッセージを取得できます。
以下の例外はいずれも SerializeExceptionInterface を実装しています。

| `$to`                 | 例外                                                          | 説明・例                                               |
|-----------------------|---------------------------------------------------------------|--------------------------------------------------------|
| 共通 (`汎用辞書`以外) | [esperecyan\dictionary_php\exception\EmptyOutputException]    | 該当の辞書形式に変換可能なお題が一つも存在しなかった。 |
| `汎用辞書`            | [esperecyan\dictionary_php\exception\TooLargeOutputException] | 辞書全体の容量が大き過ぎる。                           |
| `汎用辞書`            | [BadMethodCallException] | `$csvOnly` が偽、かつ「画像・音声・動画ファイルを含む場合のファイル形式」をCSVファイルのみで構文解析していた場合。 |

[esperecyan\dictionary_php\exception\EmptyOutputException]: ./src/exception/EmptyOutputException.php
[esperecyan\dictionary_php\exception\TooLargeOutputException]: ./src/exception/TooLargeOutputException.php
[BadMethodCallException]: https://secure.php.net/manual/class.badmethodcallexception

#### ロギング
|`$to`                  | ログレベル                 | 説明・例                                                |
|-----------------------|----------------------------|---------------------------------------------------------|
| 共通 (`汎用辞書`以外) | Psr\Log\LogLevel::ERROR    | 1つのお題が `汎用辞書` に直列化可能な形式ではなかった。 |
| `汎用辞書` <br> `ピクトセンス` | Psr\Log\LogLevel::ERROR | 符号化方式がUTF-8でない。                         |
| `ピクトセンス`        | Psr\Log\LogLevel::CRITICAL | 辞書のワード数が少な過ぎる、または多過ぎる。            |
| `ピクトセンス`        | Psr\Log\LogLevel::CRITICAL | 辞書全体の文字数が多過ぎる。                            |
| `ピクトセンス`        | Psr\Log\LogLevel::ERROR    | 辞書名が長過ぎる。                                      |

### [class esperecyan\dictionary_php\Dictionary](./src/Dictionary.php)
辞書データ。

#### [(string|string\[\]|float)\[\]\[\]\[\] esperecyan\dictionary_php\Dictionary#getWords()](./src/Dictionary.php#L71-76)
次のような構造の多次元配列で表されたお題の一覧を返します。

- \[0] => 
	- \[text] => array(文字列)
	- \[image] => array(文字列)
	- \[image-source] =>
		- \[0] => 
			- \[lml] => CommonMark (文字列)
			- \[html] => HTML (文字列)
	- \[audio] => array(文字列)
	- \[audio-source] =>
		- \[0] => 
			- \[lml] => CommonMark (文字列)
			- \[html] => HTML (文字列)
	- \[video] => array(文字列, ……)
	- \[video-source] =>
		- \[0] => 
			- \[lml] => CommonMark (文字列)
			- \[html] => HTML (文字列)
	- \[answer] => array(文字列, ……)
	- \[description] => array(文字列)
	- \[weight] => array(浮動小数点数)
	- \[specifics] => array(文字列)
	- \[question] => array(文字列)
	- \[option] => array(文字列, ……)
	- \[type] => array(文字列)
- \[1] => ……
- \[2] => ……
- ……

#### [(string|string\[\])\[\] esperecyan\dictionary_php\Dictionary#getMetadata()](./src/Dictionary.php#L93-L101)
次のような構造の多次元配列で表されたメタフィールドの一覧を返します。

- \[@title] => 文字列
- \[@summary] =>
	- \[lml] => CommonMark (文字列)
	- \[html] => HTML (文字列)
- \[@regard] => 文字列

#### [FilesystemIterator esperecyan\dictionary_php\Dictionary#getFiles()](./src/Dictionary.php#L102-L110)
辞書に同梱されるファイルを返します。

#### [esperecyan\dictionary_php\Dictionary#setFiles(FilesystemIterator $files)](./src/Dictionary.php#L112-L119)
辞書に同梱されるファイルを設定します。それぞれ同梱されるファイルとして妥当で、
かつすべてのファイル名と[Parser#parse()]の第3引数[$filenames]に与えたファイル名が一致している必要があります。

[Parser#parse()]: #dictionary-esperecyandictionary_phpparserparsesplfileinfo-file-bool-header--null-string-filenames--
[$filenames]: #string-filenames--

### [class esperecyan\dictionary_php\Validator()](./src/Validator.php)
辞書に同梱されるファイルのバリデータ。

#### [string\[\] esperecyan\dictionary_php\Validator#correct(string|SplFileInfo $file, string $filename)](./src/Validator.php#L124-L161)
[Serializer#serialize()]の戻り値と同じ構造の戻り値を返します。

[Serializer#serialize()]: #string-esperecyandictionary_phpserializerserializedictionary-dictionary-boolstring-csvonly--false

##### `string|SplFileInfo $file`
ファイルをバイナリ文字列、[SplFileInfo]、その派生クラスのいずれかで与えます。

##### `string $filename`
ファイル名。

#### 例外 [esperecyan\dictionary_php\exception\SyntaxException](./src/exception/SyntaxException.php)
SyntaxException#getMessage() から、ユーザーに示すエラーメッセージを取得できます。

| 説明・例                         |
|----------------------------------|
| 画像ファイルの容量が大き過ぎる。 |
| 音声ファイルの容量が大き過ぎる。 |
| 動画ファイルの容量が大き過ぎる。 |
| ファイルの名前が妥当でない。     |
| ファイルの形式が間違っている。   |
| ファイルの拡張子が正しくない。   |

#### ロギング
[esperecyan\dictionary_php\Validator]は[PSR-3: Logger Interface]の[Psr\Log\LoggerAwareInterface]を実装しています。

| ログレベル                | 説明・例                     |
|---------------------------|------------------------------|
| Psr\Log\LogLevel::WARNING | 画像ファイルの容量が大きい。 |
| Psr\Log\LogLevel::WARNING | 音声ファイルの容量が大きい。 |
| Psr\Log\LogLevel::WARNING | 動画ファイルの容量が大きい。 |

[esperecyan\dictionary_php\Validator]: ./src/Validator.php

Contribution
------------
Pull Request、または Issue よりお願いいたします。

セマンティック バージョニング
----------------------------
当ライブラリは[セマンティック バージョニング]を採用しています。
パブリックAPIは、[上記のとおり](#%E3%83%91%E3%83%96%E3%83%AA%E3%83%83%E3%82%AFapi)です。

[セマンティック バージョニング]: http://semver.org/lang/ja/

ライセンス
----------
当スクリプトのライセンスは [Mozilla Public License Version 2.0] \(MPL-2.0) です。

[Mozilla Public License Version 2.0]: https://www.mozilla.org/MPL/2.0/

### [naist-jdic](naist-jdic)
当ディレクトリに含まれるファイルは、[修正BSDライセンス]の[NAIST-jdic] \(ライセンス全文は[naist-jdic/COPYNG]) を
[Igo]解析用バイナリ辞書に変換したものです。

[修正BSDライセンス]: https://ja.osdn.net/projects/opensource/wiki/licenses%2Fnew_BSD_license
[NAIST-jdic]: https://ja.osdn.net/projects/naist-jdic/wiki/FrontPage "NAIST-jdic は、IPAdic の後継です。 IPAdic の固有名詞以外の全エントリをチェック（可能性に基づく品詞の整理）し、 表記ゆれ情報を付与し、複合語の構造を付与する作業を行っています。 固有名詞については不要な語、新規追加などの整理を随時行っていきます。 この作業により IPAdic のライセンスで問題となっていた ICOT 条項を削除し、 広告条項無しの BSD ライセンスに変更致しました。"
[naist-jdic/COPYNG]: naist-jdic/COPYNG
[Igo]: https://igo.osdn.jp/ "Javaで実装された形態素解析器"
