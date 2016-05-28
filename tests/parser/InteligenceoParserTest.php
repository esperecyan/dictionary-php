<?php
namespace esperecyan\dictionary_php\parser;

use esperecyan\url\URLSearchParams;
use Psr\Log\LogLevel;

class InteligenceoParserTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param string $input
     * @param string $from
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float|URLSearchParams)[][][] $jsonable
     * @param (string|string[])[] $metadata
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testParse(
        string $input,
        string $from = null,
        string $filename = null,
        string $title = null,
        array $jsonable = null,
        array $metadata = null,
        array $logLevels = []
    ) {
        $parser = new InteligenceoParser($from);
        $parser->setLogger($this);
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        $dictionary = $parser->parse($temp, $filename, $title);
        
        $this->assertEquals($jsonable, $dictionary->getWords());
        $this->assertEquals($metadata, $dictionary->getMetadata());
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                '% 選択問題
                Q,2,,../images/sun.mp4
                A,1,地球,カロン,太陽,\\seikai
                
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
                        'image' => ['local/sun.png'],
                        'option' => ['地球', 'カロン', '太陽'],
                        'answer' => ['太陽'],
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
                        'specifics' => [new URLSearchParams('require-all-right=')],
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
                        'image' => ['local/sun.png'],
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
                Q,2,,../images/sun.mp4
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
                        'image' => ['local/sun.png'],
                        'option' => ['地球', 'カロン', '太陽'],
                        'answer' => ['太陽'],
                        'type' => ['selection'],
                    ],
                ],
                [],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                'フシギダネ,ふしぎだね,1,@No.001
                フシギソウ,@dummy,ふしぎそう,@dummy2,1,@No.002
                フシギバナ,ふしぎばな,0001,1.2e3,7E-10, -12 ,@<span style="background: gray;">[リンク](https://example.jp/)</span>',
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
                ],
                [],
            ],
        ];
    }
    
    /**
     * @param string $input
     * @param string $from
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidDictionaryProvider
     */
    public function testSyntaxException(string $input, string $from)
    {
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        (new InteligenceoParser())->parse($temp);
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
