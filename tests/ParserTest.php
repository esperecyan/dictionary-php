<?php
namespace esperecyan\dictionary_php;

use Psr\Log\LogLevel;

class ParserTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
{
    use LogLevelLoggerTrait;
    use PreprocessingTrait;
    
    /**
     * @param \Closure $generateFile
     * @param string $binary
     * @dataProvider binaryProvider
     */
    public function testGetBinary(\Closure $generateFile, string $binary)
    {
        $this->assertSame($binary, (new Parser())->getBinary($generateFile()));
    }
    
    public function binaryProvider(): array
    {
        return [
            [
                function (): \SplFileObject {
                    return new \SplFileObject(
                        __DIR__ . '/resources/formats-output-dictionary.csv'
                    );
                },
                file_get_contents(__DIR__ . '/resources/formats-output-dictionary.csv'),
            ],
            [
                function (): \SplFileObject {
                    $file = new \SplTempFileObject();
                    $file->fwrite('𩸽');
                    return $file;
                },
                '𩸽',
            ],
        ];
    }
    
    /**
     * @param string|\Closure $input
     * @param string|null $from
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float)[][][] $jsonable
     * @param (string|string[])[] $metadata
     * @param string[] $logLevels
     * @param string[] $inputFilenames
     * @dataProvider fileProvider
     */
    public function testParse(
        $input,
        string $from = null,
        string $filename = null,
        string $title = null,
        array $jsonable = [],
        array $metadata = [],
        array $logLevels = [],
        array $inputFilenames = []
    ) {
        $parser = new Parser($from, $filename, $title);
        $parser->setLogger($this);
        $dictionary = $parser->parse(
            $input instanceof \Closure ? new \SplFileInfo($input()) : $this->generateTempFileObject($input),
            null,
            $inputFilenames
        );
        
        $this->assertEquals($jsonable, $dictionary->getWords());
        $this->assertEquals($metadata, $dictionary->getMetadata());
        $this->assertEquals($logLevels, $this->logLevels);
        unset($GLOBALS['GETID3_ERRORARRAY']);
    }
    
    public function fileProvider(): array
    {
        return [
            [
                mb_convert_encoding($this->stripIndents('たいよう//太陽
                ちきゅう    // 地球
                カロン
                '), 'Windows-31J', 'UTF-8'),
                'キャッチフィーリング',
                '天体 [dummy].cfq',
                null,
                [
                    [
                        'text' => ['たいよう'],
                        'description' => [['lml' => '太陽', 'html' => "<p>太陽</p>\n"]],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => [['lml' => '地球', 'html' => "<p>地球</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
                [
                    '@title' => '天体',
                ],
            ],
            [
                mb_convert_encoding($this->stripIndents('たいよう//太陽
                ちきゅう    // 地球
                カロン
                '), 'Windows-31J', 'UTF-8'),
                null,
                '天体 [dummy].cfq',
                null,
                [
                    [
                        'text' => ['たいよう'],
                        'description' => [['lml' => '太陽', 'html' => "<p>太陽</p>\n"]],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => [['lml' => '地球', 'html' => "<p>地球</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
                [
                    '@title' => '天体',
                ],
            ],
            [
                mb_convert_encoding($this->stripIndents(
                    "あ/安
                    い  [ 以
                    う　　; 宇
                    /コメント
                    [コメント
                    ;コメント
                    ]非コメント
                    え\t\t// 衣
                    お  [ 於 ]
                    "
                ), 'Windows-31J', 'UTF-8'),
                null,
                '天体 [dummy].dat',
                null,
                [
                    [
                        'text' => ['あ'],
                        'description' => [['lml' => '安', 'html' => "<p>安</p>\n"]],
                    ],
                    [
                        'text' => ['い'],
                        'description' => [['lml' => '以', 'html' => "<p>以</p>\n"]],
                    ],
                    [
                        'text' => ['う'],
                        'description' => [['lml' => '宇', 'html' => "<p>宇</p>\n"]],
                    ],
                    [
                        'text' => [']非コメント'],
                    ],
                    [
                        'text' => ['え'],
                        'description' => [['lml' => '衣', 'html' => "<p>衣</p>\n"]],
                    ],
                    [
                        'text' => ['お'],
                        'description' => [['lml' => '於', 'html' => "<p>於</p>\n"]],
                    ],
                ],
                [
                    '@title' => '天体 [dummy]',
                ],
            ],
            [
                mb_convert_encoding($this->stripIndents('
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

                    a,2,リンゴ,1,パン,4,ゴリラ,2,ラッパ,3'), 'Windows-31J', 'UTF-8'),
                null,
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
                [
                    '@title' => '選択・並べ替え問題',
                ],
            ],
            [
                mb_convert_encoding($this->stripIndents('
                    フシギダネ,ふしぎだね,1,@No.001
                    フシギソウ,@dummy,ふしぎそう,@dummy2,1,@No.002
                    フシギバナ,ふしぎばな,0001,1.2e3,7E-10, -12 ,@<span style="background: gray;">[リンク](https://example.jp/)</span>'), 'Windows-31J', 'UTF-8'),
                null,
                'ポケモン.txt',
                null,
                [
                    [
                        'text' => ['フシギダネ'],
                        'answer' => ['ふしぎだね'],
                        'description' => [['lml' => 'No.001', 'html' => "<p>No.001</p>\n"]],
                        'weight' => [1.0],
                    ],
                    [
                        'text' => ['フシギソウ'],
                        'answer' => ['ふしぎそう'],
                        'description' => [['lml' => 'No.002', 'html' => "<p>No.002</p>\n"]],
                        'weight' => [1.0],
                    ],
                    [
                        'text' => ['フシギバナ'],
                        'answer' => ['ふしぎばな'],
                        'description' => [[
                            'lml' => '<span>[リンク](https://example.jp/)</span>',
                            'html' => '<p><span><a href="https://example.jp/">リンク</a></span></p>' . "\n",
                        ]],
                        'weight' => [0.000833],
                    ],
                ],
                [
                    '@title' => 'ポケモン',
                ],
            ],
            [
                $this->stripIndents('text,image,answer,answer,description,@title,@summary
                太陽,sun.png,たいよう,おひさま,恒星。,天体,恒星、惑星、衛星などのリスト。
                地球,earth.png,,ちきゅう,惑星。,,
                カロン,charon.png,,,"冥王星の衛星。

                > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                > その後、冥王星が冥府の王プルートーの名に因むことから、
                > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                > なおクリスティーは当初から一貫してCharonの「char」を
                > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))",,
                '),
                null,
                null,
                null,
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['たいよう', 'おひさま'],
                        'description' => [['lml' => '恒星。', 'html' => "<p>恒星。</p>\n"]],
                    ],
                    [
                        'text' => ['地球'],
                        'image' => ['local/earth.png'],
                        'answer' => ['ちきゅう'],
                        'description' => [['lml' => '惑星。', 'html' => "<p>惑星。</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                        'image' => ['local/charon.png'],
                        'description' => [[
                            'lml' => $this->stripIndents('冥王星の衛星。

                                > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                                > その後、冥王星が冥府の王プルートーの名に因むことから、
                                > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                                > なおクリスティーは当初から一貫してCharonの「char」を
                                > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                                > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                                引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))'),
                            'html' => $this->stripIndents('<p>冥王星の衛星。</p>
                                <blockquote>
                                <p>カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                                その後、冥王星が冥府の王プルートーの名に因むことから、
                                この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                                なおクリスティーは当初から一貫してCharonの「char」を
                                妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                                これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                                引用元: <a href="https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F)">カロン (衛星) - Wikipedia</a></p>
                                </blockquote>
                                '),
                        ]],
                    ],
                ],
                [
                    '@title' => '天体',
                    '@summary' => ['lml' => '恒星、惑星、衛星などのリスト。', 'html' => "<p>恒星、惑星、衛星などのリスト。</p>\n"],
                ],
                [LogLevel::ERROR, LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                $this->stripIndentsAndToCRLF('太陽,たいよう,おひさま
                地球,ちきゅう
                カロン
                '),
                '汎用辞書',
                'ヘッダが省略された辞書.txt',
                null,
                [
                    [
                        'text' => ['太陽'],
                        'answer' => ['たいよう', 'おひさま'],
                    ],
                    [
                        'text' => ['地球'],
                        'answer' => ['ちきゅう'],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
                [
                    '@title' => 'ヘッダが省略された辞書',
                ],
            ],
            [
                function (): string {
                    $archive = $this->generateArchive();
                    
                    $archive->addFromString('dictionary.csv', $this->stripIndents(
                        'text,image,audio,video
                        ピン,png.png,,
                        ジェイフィフ,jfif.jpg,,
                        エスブイジー,svg.svg,,
                        エーエーシー,,mpeg4-aac.m4a,
                        エムピースリー,,mpeg1-audio-layer3.mp3,
                        エイチニーロクヨン,,,mpeg4-h264.mp4
                        ウェブエム,,,webm-vb8.webm
                        '
                    ));
                    
                    $image = imagecreatetruecolor(1000, 1000);
                    ob_start();
                    imagepng($image);
                    $archive->addFromString('png.png', ob_get_clean());
                    ob_start();
                    imagejpeg($image);
                    $archive->addFromString('jfif.jpg', ob_get_clean());
                    imagedestroy($image);
                    $archive->addFromString('svg.svg', '<?xml version="1.0" ?>
                        <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>');
                    
                    $archive->addFile(__DIR__ . '/resources/mpeg4-aac.m4a', 'mpeg4-aac.m4a');
                    $archive->addFile(__DIR__ . '/resources/mpeg1-audio-layer3.mp3', 'mpeg1-audio-layer3.mp3');
                    
                    $archive->addFile(__DIR__ . '/resources/mpeg4-h264.mp4', 'mpeg4-h264.mp4');
                    
                    $path = $archive->filename;
                    $archive->close();
                    return $path;
                },
                null,
                null,
                null,
                [
                    [
                        'text' => ['ピン'],
                        'image' => ['png.png'],
                    ],
                    [
                        'text' => ['ジェイフィフ'],
                        'image' => ['jfif.jpg'],
                    ],
                    [
                        'text' => ['エスブイジー'],
                        'image' => ['svg.svg'],
                    ],
                    [
                        'text' => ['エーエーシー'],
                        'audio' => ['mpeg4-aac.m4a'],
                    ],
                    [
                        'text' => ['エムピースリー'],
                        'audio' => ['mpeg1-audio-layer3.mp3'],
                    ],
                    [
                        'text' => ['エイチニーロクヨン'],
                        'video' => ['mpeg4-h264.mp4'],
                    ],
                    [
                        'text' => ['ウェブエム'],
                        'video' => ['local/webm-vb8.mp4'],
                    ],
                ],
                [],
                [LogLevel::ERROR],
            ],
            [
                $this->stripIndents('たいよう
                ちきゅう
                かろん
                けれす
                はれーすいせい
                '),
                'ピクトセンス',
                null,
                null,
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['かろん']],
                    ['text' => ['けれす']],
                    ['text' => ['はれーすいせい']],
                ],
                [],
            ],
            [
                $this->stripIndentsAndToCRLF(
                    'text,image,audio,video
                    ピン,png.png,,
                    ジェイフィフ,jfif.jpg,,
                    エスブイジー,svg.svg,,
                    エーエーシー,,mpeg4-aac.m4a,
                    エムピースリー,,mpeg1-audio-layer3.mp3,
                    エイチニーロクヨン,,,mpeg4-h264.mp4
                    '
                ),
                null,
                null,
                null,
                [
                    [
                        'text' => ['ピン'],
                        'image' => ['png.png'],
                    ],
                    [
                        'text' => ['ジェイフィフ'],
                        'image' => ['jfif.jpg'],
                    ],
                    [
                        'text' => ['エスブイジー'],
                        'image' => ['svg.svg'],
                    ],
                    [
                        'text' => ['エーエーシー'],
                        'audio' => ['mpeg4-aac.m4a'],
                    ],
                    [
                        'text' => ['エムピースリー'],
                        'audio' => ['mpeg1-audio-layer3.mp3'],
                    ],
                    [
                        'text' => ['エイチニーロクヨン'],
                        'video' => ['mpeg4-h264.mp4'],
                    ],
                ],
                [],
                [],
                ['png.png', 'jfif.jpg', 'svg.svg', 'mpeg4-aac.m4a', 'mpeg1-audio-layer3.mp3', 'mpeg4-h264.mp4'],
            ],
            [
                function (): string {
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
                    
                    $path = $archive->filename;
                    $archive->close();
                    return $path;
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
            [
                $this->stripIndentsAndToCRLF(
                    'text,description
                    ピン,"![ピン](test/png.png) <audio src=""test/mp4.m4a""></audio> <video src=""test/mp4.mp4""></video>"
                    '
                ),
                null,
                null,
                null,
                [
                    [
                        'text' => ['ピン'],
                        'description' => [[
                            'lml' => '![ピン](test/png.png) <audio src="test/mp4.m4a"></audio> <video src="test/mp4.mp4"></video>',
                            'html' => '<p><img src="test/png.png" alt="ピン" /> <audio src="test/mp4.m4a"></audio> <video src="test/mp4.mp4"></video></p>' . "\n",
                        ]],
                    ],
                ],
                [],
                [],
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
        (new Parser($from))->parse($this->generateTempFileObject($this->stripIndents($input)));
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            [
                '<?xml version="1.0" ?>
                 <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>
                <!-- text/plainでない -->',
                'キャッチフィーリング',
            ],
            [
                'ふごうかほうしき
                ',
                'キャッチフィーリング',
            ],
        ];
    }
}
