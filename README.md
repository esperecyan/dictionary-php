主に単語で答えるゲームにおける汎用的な辞書形式に関するPHPライブラリ
===============================================================
次のゲームの辞書を[主に単語で答えるゲームにおける汎用的な辞書形式] \(以下、汎用辞書形式)に変換する API を提供します。
また、汎用辞書形式のファイルを与えた場合、校正可能なら校正して返します。

* [キャッチフィーリング]、[Drawing Catch] \(*.cfq)
* [きゃっちま] \(*.dat) ※暗号化後のファイルは扱えません
* [Inteligenceω] \(*.txt) ※暗号化後のファイルは扱えません

Inteligenceωの辞書で画像・音声ファイルへのパスが含まれる場合、単にファイル名を抽出します。
汎用辞書形式のZIPファイルをPOSTした場合を除き、当APIはCSVファイルのみを返し、ZIPファイル化などは行いません。

なお[ピクトセンス]の辞書は、辞書のテキストをそのまま utf-8 (改行コードはCRLF) で保存すれば、汎用辞書形式になります。

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
composer require esperecyan/dictionary-php
```

Composer のインストール方法については、[Composer のグローバルインストール - Qiita]などをご覧ください。

[Composer のグローバルインストール - Qiita]: http://qiita.com/100/items/a1d73544c70fbfa7a643 "Composer は PEAR、および Pyrus に代わる新しい PHP ライブラリ管理システムです。"

要件
----
* PHP 7.0 以上

パブリックAPI
-------------

### [class esperecyan\dictionary_php\Parser(string $from = null, string $filename = null, string $title = null)](./src/Parser.php)
構文解析器。

#### `string $from = null`
変換元の辞書形式。`キャッチフィーリング` `きゃっちま` `Inteligenceω クイズ` `Inteligenceω しりとり` `汎用辞書` のいずれか。

指定されていないか間違った値が指定されていれば、`$filename` から判断します。
その場合のInteligenceωについては、コメント行、空行を除く最初の行が `Q,` で始まるか否かで、クイズとしりとりを判別します。

#### `string $filename = null`
変換元のファイル名。

#### `string $title = null`
辞書のタイトル。

指定されていなければ、`$filename` から判断します。

汎用辞書形式で `@title` フィールドが存在する場合、この指定は無視されます。

#### [Dictionary esperecyan\dictionary_php\Parser#parse(SplFileInfo $file, bool $header = null)](./src/Parser.php#L76-125)
##### `SplFileInfo $file`
変換元のファイルを[SplFileInfo]、またはその派生クラスで与えます。

[SplFileInfo]: http://jp2.php.net/manual/class.splfileinfo

##### `bool $header = null`
変換元のファイルが `汎用辞書` の場合、ヘッダ行が存在すれば `true`、存在しなければ `false`、不明なら `null` を指定します。

### [class esperecyan\dictionary_php\serializer\GenericDictionarySerializer()](./src/serializer/GenericDictionarySerializer.php)
直列化器。

#### [void esperecyan\dictionary_php\serializer\GenericDictionarySerializer#response(Dictionary $dictionary)](./src/serializer/GenericDictionarySerializer.php#L106-136)
content-type応答ヘッダをともなって辞書を出力します。

### [class esperecyan\dictionary_php\internal\Dictionary](./src/internal/Dictionary.php)
辞書データ。

### Parser#parse() における例外 [esperecyan\dictionary_php\SyntaxException](./src/SyntaxException.php)
SyntaxException#getMessage() から、ユーザーに示すエラーメッセージを取得できます。

| `$from`                  | 説明・例                                                             |
|--------------------------|----------------------------------------------------------------------|
| 共通                     | ファイル形式、符号化方式が間違っている。                             |
| `汎用辞書`               | 辞書全体の容量が大き過ぎる。                                         |
| `汎用辞書`               | 空のCSVファイルである。                                              |
| `汎用辞書`               | ヘッダ行に `text` というフィールドが存在しない。                     |
| `汎用辞書`               | ヘッダ行を超えるフィールド数の行が存在する。                         |
| `汎用辞書`               | textフィールドが存在しない行がある。                                 |
| `汎用辞書`               | 符号化方式の検出に失敗した。                                         |
| `汎用辞書`               | 画像ファイルの容量が大き過ぎる。                                     |
| `汎用辞書`               | 音声ファイルの容量が大き過ぎる。                                     |
| `汎用辞書`               | 動画ファイルの容量が大き過ぎる。                                     |
| `汎用辞書`               | アーカイブに `dictionary.csv` という名前のファイルが含まれていない。 |
| `汎用辞書`               | アーカイブに含まれるファイルの名前が妥当でない。                     |
| `汎用辞書`               | アーカイブに含まれるファイルの形式が間違っている。                   |
| `汎用辞書`               | アーカイブに含まれるファイルの拡張子が正しくない。                   |
| `キャッチフィーリング`   | 空行がある。                                                         |
| `キャッチフィーリング`   | すべてのお題が制御文字や空白文字のみで構成されている。               |
| `きゃっちま`             | コメントの前にスペースがある。                                       |
| `きゃっちま`             | 空の辞書。                                                           |
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
| `Inteligenceω クイズ`   | 汎用辞書に変換可能な行が一つも存在しなかった。                       |

### ロギング
[esperecyan\dictionary_php\Parser]は[PSR-3: Logger Interface]の[Psr\Log\LoggerAwareInterface]を実装しています。

|`$from`     | ログレベル               | 説明・例                     |
|------------|--------------------------|------------------------------|
| `汎用辞書` | Psr\Log\LogLevel::ERROR  | 符号化方式がUTF-8でない。    |
| `汎用辞書` | Psr\Log\LogLevel::NOTICE | 辞書全体の容量が大きい。     |
| `汎用辞書` | Psr\Log\LogLevel::NOTICE | 画像ファイルの容量が大きい。 |
| `汎用辞書` | Psr\Log\LogLevel::NOTICE | 音声ファイルの容量が大きい。 |
| `汎用辞書` | Psr\Log\LogLevel::NOTICE | 動画ファイルの容量が大きい。 |

[esperecyan\dictionary_php\Parser]: ./src/Parser.php
[PSR-3: Logger Interface]: http://guttally.net/psr/psr-3/ "この文書では，ロギングライブラリのための共通インタフェースについて記述します。"
[Psr\Log\LoggerAwareInterface]: https://github.com/php-fig/log/blob/master/Psr/Log/LoggerAwareInterface.php

Contribution
------------
Pull Request、または Issue よりお願いいたします。

セマンティック バージョニング
----------------------------
当ライブラリは[セマンティック バージョニング]を採用しています。
パブリックAPIは、[上記のとおり](#パブリックAPI)です。

[セマンティック バージョニング]: http://semver.org/lang/ja/

ライセンス
----------
当スクリプトのライセンスは [Mozilla Public License Version 2.0] \(MPL-2.0) です。

ただし、[tests/resources/inteligenceo/quiz-input.txt] および [tests/resources/inteligenceo/shiritori-input.txt] は
MPL-2.0 ではないフリーのファイルであり、著作権は[ろくしー様]にあります。

[Mozilla Public License Version 2.0]: https://www.mozilla.org/MPL/2.0/
[tests/resources/inteligenceo/quiz-input.txt]: tests/resources/inteligenceo/quiz-input.txt
[tests/resources/inteligenceo/shiritori-input.txt]: tests/resources/inteligenceo/shiritori-input.txt
[ろくしー様]: https://twitter.com/loxeee
