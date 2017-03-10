<?php
namespace esperecyan\dictionary_php\parser;

use Psr\Log\LogLevel;

class InteligenceoParserTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * @param string|\Closure $input
     * @param string $from
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float)[][][] $jsonable
     * @param (string|string[])[] $metadata
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testParse(
        $input,
        string $from = null,
        string $filename = null,
        string $title = null,
        array $jsonable = null,
        array $metadata = null,
        array $logLevels = []
    ) {
        $parser = new InteligenceoParser($from);
        $parser->setLogger($this);
        if ($input instanceof \Closure) {
            $archive = $input();
            $file = new \SplFileInfo($archive->filename);
            $archive->close();
            $dictionary = $parser->parse($file, $filename, $title);
        } else {
            $dictionary = $parser->parse($this->generateTempFileObject($this->stripIndents($input)), $filename, $title);
        }
        
        array_walk_recursive($jsonable, (function (&$field) {
            if (is_string($field)) {
                $field = $this->stripIndents($field);
            }
        })->bindTo($this));
        $this->assertEquals($jsonable, $dictionary->getWords());
        array_walk_recursive($metadata, (function (string &$field) {
            if (is_string($field)) {
                $field = $this->stripIndents($field);
            }
        })->bindTo($this));
        $this->assertEquals($metadata, $dictionary->getMetadata());
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                '% 選択問題
                Q,2,,../images/sun.mp4,zoom_end=10,mozaic=1,finalscore=100
                A,1,地球,カロン,太陽,\\seikai
                Q,1,,../audios/four-seasons.mp4,speed=500,magnification=1,last-magnification=1,media_start=13500,repeat=-1,length=500
                A,1,四季,\\seikai,魔王,ラデツキー行進曲
                
                Q,0,仲間外れはどれでしょう
                A,1,リンゴ,\\seikai,ゴリラ,ラクダ,ダチョウ,\\explain=選択肢を表示しなければ問題が成立しない場合。
                
                % 答えが複数あり、どれか1つを選択すれば正解になる場合
                Q,0,食べ物はどれでしょう (答えが複数ある場合はどれが1つだけ選択)
                A,1,リンゴ,\\seikai,ゴリラ,ラッパ,パン,\\seikai

                % 答えが複数あり、すべて選択する必要がある場合
                Q,0,同じ種類のものを選びましょう
                A,3,リンゴ,\\seikai,ゴリラ,ラッパ,パン,\\seikai

                % 並べ替え問題
                q,0,しりとりが成立するように並べ替えてください
                % 問題行と解答行の間のコメント行と空行
                
                a,2,リンゴ,1,パン,4,ゴリラ,2,ラッパ,3',
                'Inteligenceω クイズ',
                '選択・並べ替え問題.txt',
                null,
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['tag:pokemori.jp,2016:local:sun.mp4'],
                        'option' => ['地球', 'カロン', '太陽'],
                        'answer' => ['太陽'],
                        'specifics' => ['pixelization='],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['四季'],
                        'audio' => ['tag:pokemori.jp,2016:local:four-seasons.mp4'],
                        'option' => ['四季', '魔王', 'ラデツキー行進曲'],
                        'answer' => ['四季'],
                        'specifics' => ['speed=5&start=13.5&length=0.5'],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['リンゴ'],
                        'question' => ['仲間外れはどれでしょう'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラクダ', 'ダチョウ'],
                        'answer' => ['リンゴ'],
                        'type' => ['selection'],
                        'description' => [[
                            'lml' => '選択肢を表示しなければ問題が成立しない場合。',
                            'html' => "<p>選択肢を表示しなければ問題が成立しない場合。</p>\n",
                        ]],
                    ],
                    [
                        'text' => ['「リンゴ」か「パン」'],
                        'question' => ['食べ物はどれでしょう (答えが複数ある場合はどれが1つだけ選択)'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラッパ', 'パン'],
                        'answer' => ['リンゴ', 'パン'],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['「リンゴ」と「パン」'],
                        'question' => ['同じ種類のものを選びましょう'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラッパ', 'パン'],
                        'answer' => ['リンゴ', 'パン'],
                        'specifics' => ['require-all-right='],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['リンゴ → ゴリラ → ラッパ → パン'],
                        'question' => ['しりとりが成立するように並べ替えてください'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラッパ', 'パン'],
                        'type' => ['selection'],
                    ],
                ],
                ['@title' => '選択・並べ替え問題'],
            ],
            [
                '% 解答行でカンマの連続
                Q,2,,sun.png
                A,0,太陽,たいよう,sun,,\\explain=テスト',
                'Inteligenceω クイズ',
                null,
                null,
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['tag:pokemori.jp,2016:local:sun.png'],
                        'answer' => ['太陽', 'たいよう', 'sun'],
                        'description' => [['lml' => 'テスト', 'html' => "<p>テスト</p>\n"]],
                    ],
                ],
                [],
            ],
            [
                'q,0,部分一致
                A,0,あ,[[,お,||',
                'Inteligenceω',
                null,
                null,
                [
                    [
                        'text' => ['あ'],
                        'answer' => ['あ', '/あ.*/', '/.*お.*/'],
                        'question' => ['部分一致'],
                    ],
                ],
                [],
            ],
            [
                '% 選択問題
                Q,2,,../images/sun.mp4,score=1.0E+2
                A,1,地球,カロン,太陽,\\seikai
                
                % Wikipediaクイズ
                Q,3,問題文
                A,0,解答

                % アンサイクロペディアクイズ
                Q,4,問題文
                A,0,解答
                ',
                'Inteligenceω クイズ',
                null,
                null,
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['tag:pokemori.jp,2016:local:sun.mp4'],
                        'option' => ['地球', 'カロン', '太陽'],
                        'answer' => ['太陽'],
                        'specifics' => ['score=100'],
                        'type' => ['selection'],
                    ],
                ],
                [],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                'フシギダネ,ふしぎだね,1,@No.001
                フシギソウ,@dummy,ふしぎそう,@dummy2,1,@No.002
                フシギバナ,ふしぎばな,0001,1.2e3,7E-10, -12 ,@<span style="background: gray;">[リンク](https://example.jp/)</span>
                @test,あっとまーく
                3,すうじ
                妥当な読み方,あんーん,をーん,ゎーん,ゎーーゎ,っーっ,っをー',
                'Inteligenceω しりとり',
                null,
                null,
                [
                    [
                        'text' => ['フシギダネ'],
                        'answer' => ['ふしぎだね'],
                        'description' => [['lml' => 'No.001', 'html' => "<p>No.001</p>\n"]],
                        'weight' => [1],
                    ],
                    [
                        'text' => ['フシギソウ'],
                        'answer' => ['ふしぎそう'],
                        'description' => [['lml' => 'No.002', 'html' => "<p>No.002</p>\n"]],
                        'weight' => [1],
                    ],
                    [
                        'text' => ['フシギバナ'],
                        'answer' => ['ふしぎばな'],
                        'description' => [[
                            'lml' => '<span>[リンク](https://example.jp/)</span>',
                            'html' => "<p><span><a href=\"https://example.jp/\">リンク</a></span></p>\n",
                        ]],
                        'weight' => [0.000833],
                    ],
                    [
                        'text' => ['@test'],
                        'answer' => ['あっとまーく'],
                    ],
                    [
                        'text' => ['3'],
                        'answer' => ['すうじ'],
                    ],
                    [
                        'text' => ['妥当な読み方'],
                        'answer' => ['あんーん', 'をーん', 'ゎーん', 'ゎーーゎ', 'っーっ', 'っをー'],
                    ]
                ],
                [],
            ],
            [
                function (): \ZipArchive {
                    $archive = $this->generateArchive();
                    
                    $archive->addFromString('テスト.txt', mb_convert_encoding($this->stripIndents(
                        'Q,2,,C:\\Users\\テスト\\inteli\\画像ファイル形式\\PNG.png
                        A,0,ピン
                        Q,2,,C:\\Users\\テスト\\inteli\\画像ファイル形式\\ジェイフィフ.jpg
                        A,0,ジェイフィフ
                        Q,2,,C:\\Users\\テスト\\inteli\\画像ファイル形式\\svg.svg
                        A,0,エスブイジー
                        '
                    ), 'Shift_JIS', 'UTF-8'));
                    
                    $image = imagecreatetruecolor(1000, 1000);
                    ob_start();
                    imagepng($image);
                    $archive->addFromString('png.PNG', ob_get_clean());
                    ob_start();
                    imagejpeg($image);
                    $archive->addFromString(
                        mb_convert_encoding('画像ファイル形式/ジェイフィフ.jpg', 'Shift_JIS', 'UTF-8'),
                        ob_get_clean()
                    );
                    imagedestroy($image);
                    $archive->addFromString('svg.svg', '<?xml version="1.0" ?>
                        <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>');
                    
                    return $archive;
                },
                'Inteligenceω クイズ',
                null,
                null,
                [
                    [
                        'text' => ['ピン'],
                        'image' => ['png.png'],
                    ],
                    [
                        'text' => ['ジェイフィフ'],
                        'image' => ['jeififu.jpg'],
                    ],
                    [
                        'text' => ['エスブイジー'],
                        'image' => ['svg.svg'],
                    ],
                ],
                [],
                [],
            ],
            'tag URL' => [
                'Q,2,,tag:resource.test%2C2016:sun.png,zoom_end=10,mozaic=1,finalscore=100
                A,1,地球,カロン,太陽,\\seikai,ケレス
                Q,2,,tag:image@resource.test%2C2016-12:earth.png,zoom_end=10,mozaic=1,finalscore=100
                A,1,地球,\\seikai,カロン,太陽,ケレス
                Q,2,,tag:"%2C:@\\""@resource.test%2C2016-12-31:charon.png,zoom_end=10,mozaic=1,finalscore=100
                A,1,地球,カロン,\\seikai,太陽,ケレス
                Q,2,,http://example.ne.jp/ceres.png,zoom_end=10,mozaic=1,finalscore=100
                A,1,地球,カロン,太陽,ケレス,\\seikai',
                'Inteligenceω クイズ',
                null,
                null,
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['tag:resource.test,2016:sun.png'],
                        'option' => ['地球', 'カロン', '太陽', 'ケレス'],
                        'answer' => ['太陽'],
                        'specifics' => ['pixelization='],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['地球'],
                        'image' => ['tag:image@resource.test,2016-12:earth.png'],
                        'option' => ['地球', 'カロン', '太陽', 'ケレス'],
                        'answer' => ['地球'],
                        'specifics' => ['pixelization='],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['カロン'],
                        'image' => ['tag:",:@\\""@resource.test,2016-12-31:charon.png'],
                        'option' => ['地球', 'カロン', '太陽', 'ケレス'],
                        'answer' => ['カロン'],
                        'specifics' => ['pixelization='],
                        'type' => ['selection'],
                    ],
                    [
                        'text' => ['ケレス'],
                        'image' => ['https://example.ne.jp/ceres.png'],
                        'option' => ['地球', 'カロン', '太陽', 'ケレス'],
                        'answer' => ['ケレス'],
                        'specifics' => ['pixelization='],
                        'type' => ['selection'],
                    ],
                ],
                [],
                [],
            ],
        ];
    }
    
    /**
     * @param string $input
     * @param string $from
     * @param string|null $partOfMessage エラーメッセージの一部。
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidDictionaryProvider
     */
    public function testSyntaxException(string $input, string $from, string $partOfMessage = null)
    {
        if (isset($partOfMessage)) {
            $this->expectExceptionMessageRegExp('/' . preg_quote($partOfMessage, '/') . '/u');
        }
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        (new InteligenceoParser($from))->parse($temp);
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            [
                'カタカナ,カタカナ',
                'Inteligenceω しりとり',
            ],
            [
                'る～こと,る～こと',
                'Inteligenceω しりとり',
            ],
            [
                'ヴワル魔法図書館,ヴわるまほうとしょかん',
                'Inteligenceω しりとり',
            ],
            [
                'テスト,てすと ',
                'Inteligenceω しりとり',
            ],
            [
                'テスト',
                'Inteligenceω しりとり',
            ],
            [
                'テスト,1,てすと',
                'Inteligenceω しりとり',
            ],
            [
                '長音で始まる,ーあ',
                'Inteligenceω しりとり',
            ],
            [
                '撥音で始まる,んあ',
                'Inteligenceω しりとり',
            ],
            [
                '長音が末尾に連続する,あーー',
                'Inteligenceω しりとり',
            ],
            [
                '長音が末尾に連続する,[,あ,|,ー,],,ー',
                'Inteligenceω しりとり',
            ],
            [
                '末尾の長音の前に撥音,あんー',
                'Inteligenceω しりとり',
            ],
            [
                '末尾の長音の前に促音,っー',
                'Inteligenceω しりとり',
            ],
            [
                '末尾の長音の前に「ゎ」,ゎー',
                'Inteligenceω しりとり',
            ],
            [
                'ゐ,ゐ',
                'Inteligenceω しりとり',
                'ゐ',
            ],
            [
                'ゑ,ゑ',
                'Inteligenceω しりとり',
            ],
            [
                'Q,非数値,テスト
                A,0,てすと',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                A,非数値,てすと',
                'Inteligenceω クイズ',
            ],
            [
                'Q,1,音声ファイル未指定
                A,0,てすと',
                'Inteligenceω クイズ',
            ],
            [
                'Q,2,画像ファイル未指定
                A,0,てすと',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                A,1,\\seikai,選択肢A,選択肢B',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,正解がない
                A,1,選択肢A,選択肢B',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                A,0,正解A,\\bonus=非数値,正解B',
                'Inteligenceω クイズ',
            ],
            [
                'Q,2,テスト,local/test.mp4,score=%31%30%30
                A,0,正解A,正解B',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                A,0,\\bonus=10,せいかいA,せいかいB',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                A,0,[[,てすと,||',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                Q,0,問題行の連続
                A,0,てすと',
                'Inteligenceω クイズ',
            ],
            [
                'Q,0,テスト
                A,0,てすと
                Q,0,辞書末尾の問題行',
                'Inteligenceω クイズ',
            ],
            [
                'A,0,辞書先頭の解答行
                Q,0,テスト
                A,0,てすと',
                'Inteligenceω クイズ',
            ],
            [
                '% 内容が無い辞書
                %てすと',
                'Inteligenceω しりとり',
            ],
        ];
    }
}
