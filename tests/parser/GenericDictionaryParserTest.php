<?php
namespace esperecyan\dictionary_php\parser;

use Psr\Log\LogLevel;

class GenericDictionaryParserTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * @param string|\Closure $input
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float)[][][] $jsonable
     * @param (string|string[])[] $metadata
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testParse(
        $input,
        string $filename = null,
        string $title = null,
        array $jsonable = null,
        array $metadata = null,
        array $logLevels = []
    ) {
        $parser = new GenericDictionaryParser();
        $parser->setLogger($this);
        
        if ($input instanceof \Closure) {
            $archive = $input();
            $file = new \SplFileInfo($archive->filename);
            $archive->close();
            $dictionary = $parser->parse($file, $filename, $title);
        } else {
            $dictionary = $parser->parse($this->generateTempFileObject(
                mb_check_encoding($input, 'UTF-8') ? $this->stripIndents($input) : $input
            ), $filename, $title);
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
                'text,image,answer,answer,description,@title,@summary
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
                ',
                null,
                null,
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['tag:pokemori.jp,2016:local:sun.png'],
                        'answer' => ['たいよう', 'おひさま'],
                        'description' => [['lml' => '恒星。', 'html' => "<p>恒星。</p>\n"]],
                    ],
                    [
                        'text' => ['地球'],
                        'image' => ['tag:pokemori.jp,2016:local:earth.png'],
                        'answer' => ['ちきゅう'],
                        'description' => [['lml' => '惑星。', 'html' => "<p>惑星。</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                        'image' => ['tag:pokemori.jp,2016:local:charon.png'],
                        'description' => [[
                            'lml' => '冥王星の衛星。

                                > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                                > その後、冥王星が冥府の王プルートーの名に因むことから、
                                > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                                > なおクリスティーは当初から一貫してCharonの「char」を
                                > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                                > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                                引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))',
                            'html' => '<p>冥王星の衛星。</p>
                                <blockquote>
                                <p>カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                                その後、冥王星が冥府の王プルートーの名に因むことから、
                                この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                                なおクリスティーは当初から一貫してCharonの「char」を
                                妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                                これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                                引用元: <a href="https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F)">カロン (衛星) - Wikipedia</a></p>
                                </blockquote>
                                ',
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
                '太陽,たいよう,おひさま
                地球,ちきゅう
                カロン
                ',
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
                'たいよう
                ',
                null,
                '天体',
                [
                    [
                        'text' => ['たいよう'],
                    ],
                ],
                [
                    '@title' => '天体',
                ],
            ],
            [
                'たいよう
                ',
                '天体 Ver.0.1.0.cfq',
                null,
                [
                    [
                        'text' => ['たいよう'],
                    ],
                ],
                [
                    '@title' => '天体 Ver',
                ],
            ],
            [
                'text,@title
                たいよう,テスト
                ',
                null,
                '天体',
                [
                    [
                        'text' => ['たいよう'],
                    ],
                ],
                [
                    '@title' => 'テスト',
                ],
            ],
            [
                mb_convert_encoding('テスト', 'Windows-31J', 'UTF-8'),
                null,
                null,
                [
                    [
                        'text' => ['テスト'],
                    ],
                ],
                [],
                [LogLevel::ERROR],
            ],
            [
                'text,image,image-source,description
                テスト,https://resource.test/image.png,"# 見出し1
                本文

                見出し2
                =======
                本文

                見出し3
                -------
                [リンク] **強調** <b>名前</b> _強勢_ <i style=""font-weight: bold;"">心の声</i> `コード`

                [リンク]: https://example.jp/","# 見出し1
                本文
                
                見出し2
                =======
                本文
                
                見出し3
                -------
                [リンク] **強調** <b>名前</b> _強勢_ <i style=""font-weight: bold;"">心の声</i> `コード`
                
                [リンク]: https://example.jp/"
                ',
                null,
                null,
                [
                    [
                        'text' => ['テスト'],
                        'image' => ['https://resource.test/image.png'],
                        'image-source' => [[
                            'lml' => '見出し1

                                本文

                                見出し2 本文

                                見出し3 [リンク](https://example.jp/) 強調 名前 強勢 心の声 コード',
                            'html' => '<p>見出し1</p>
                                <p>本文</p>
                                <p>見出し2 本文</p>
                                <p>見出し3 <a href="https://example.jp/">リンク</a> 強調 名前 強勢 心の声 コード</p>
                                ',
                        ]],
                        'description' => [[
                            'lml' => '見出し1
                                ====

                                本文

                                見出し2
                                ====

                                本文

                                見出し3
                                ----

                                [リンク](https://example.jp/) **強調** **名前** _強勢_ _心の声_ `コード`',
                            'html' => '<h1>見出し1</h1>
                                <p>本文</p>
                                <h1>見出し2</h1>
                                <p>本文</p>
                                <h2>見出し3</h2>
                                <p><a href="https://example.jp/">リンク</a> <strong>強調</strong> <strong>名前</strong> <em>強勢</em> <em>心の声</em> <code>コード</code></p>
                                ',
                        ]],
                    ],
                ],
                [],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                function (): \ZipArchive {
                    $archive = $this->generateArchive();
                    
                    $archive->addFromString('dictionary.csv', $this->stripIndents(
                        'text,image,audio,video,specifics
                        ピン,png.png,,,
                        ジェイフィフ,jfif.jpg,,,
                        エスブイジー,svg.svg,,,
                        エーエーシー,,mpeg4-aac.m4a,,
                        エムピースリー,,mpeg1-audio-layer3.mp3,,
                        エイチニーロクヨン,,,mpeg4-h264.mp4,
                        ウェブエム,,,webm-vb8.webm,score=1.0E%2B15
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
                    
                    $archive->addFile(__DIR__ . '/../resources/mpeg4-aac.m4a', 'mpeg4-aac.m4a');
                    $archive->addFile(__DIR__ . '/../resources/mpeg1-audio-layer3.mp3', 'mpeg1-audio-layer3.mp3');
                    
                    $archive->addFile(__DIR__ . '/../resources/mpeg4-h264.mp4', 'mpeg4-h264.mp4');
                    
                    return $archive;
                },
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
                        'video' => ['tag:pokemori.jp,2016:local:webm-vb8.webm'],
                        'specifics' => ['score=1000000000000000'],
                    ],
                ],
                [],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                "text,answer,description
                水平タブ,タブ,「\t」
                ",
                null,
                null,
                [
                    [
                        'text' => ['水平タブ'],
                        'answer' => ['タブ'],
                        'description' => [[
                            'lml' => '「    」',
                            'html' => '<p>「    」</p>
                                ',
                        ]],
                    ],
                ],
                [],
                [],
            ],
            [
                "text,水平タブを\t含むフィールド名
                テスト,test
                ",
                null,
                null,
                [
                    [
                        'text' => ['テスト'],
                        '水平タブを含むフィールド名' => ['test'],
                    ],
                ],
                [],
                [],
            ],
        ];
    }
    
    /**
     * @param string|\Closure $input
     * @param string[] $filenames
     * @dataProvider invalidDictionaryProvider
     */
    public function testSyntaxException($input, $filenames = [])
    {
        if ($input instanceof \Closure) {
            $archive = $input();
            $archivePath = $archive->filename;
            $archive->close();
            $file = new \SplFileObject($archivePath);
        } else {
            $file = $this->generateTempFileObject($this->stripIndents($input));
        }
        
        $this->expectException(\esperecyan\dictionary_php\exception\SyntaxException::class);
        (new GenericDictionaryParser(null, $filenames))->parse($file);
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('test.csv', $this->stripIndents(
                    'text,image
                    エスブイジー,svg.svg
                    '
                ));
                $archive->addFromString('svg.svg', '<?xml version="1.0" ?>
                    <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>');
                
                return $archive;
            }],
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('dictionary.csv', '<?xml version="1.0" ?>
                    <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>');
                
                return $archive;
            }],
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('dictionary.csv', $this->stripIndents(
                    'text,image
                    エスブイジー,SVG.svg
                    '
                ));
                $archive->addFromString('SVG.svg', '<?xml version="1.0" ?>
                    <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>');
                
                return $archive;
            }],
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('dictionary.csv', $this->stripIndents(
                    'text,image
                    ジフ,gif.gif
                    '
                ));
                $image = imagecreatetruecolor(1000, 1000);
                ob_start();
                imagegif($image);
                $archive->addFromString('gif.gif', ob_get_clean());
                imagedestroy($image);
                
                return $archive;
            }],
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('dictionary.csv', $this->stripIndents(
                    'text,audio
                    オッグ,ogg-vorbis.ogg
                    '
                ));
                $archive->addFile(__DIR__ . '/../resources/ogg-vorbis.ogg', 'ogg-vorbis.ogg');
                
                return $archive;
            }],
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('dictionary.csv', $this->stripIndents(
                    'text,video
                    ウェブエム,webm-vp8.webm
                    '
                ));
                $archive->addFile(__DIR__ . '/../resources/webm-vp8.webm', 'webm-vp8.webm');
                
                return $archive;
            }],
            [function (): \ZipArchive {
                $archive = $this->generateArchive();

                $archive->addFromString('dictionary.csv', $this->stripIndents(
                    'text,image
                    ジェイフィフ,jfif.jpe
                    '
                ));
                $image = imagecreatetruecolor(1000, 1000);
                ob_start();
                imagejpeg($image);
                $archive->addFromString('jfif.jpe', ob_get_clean());
                imagedestroy($image);
                
                return $archive;
            }],
            [''],
            ['text,description
            おだい,説明,ヘッダ行よりフィールド数が多いレコード
            '],
            ['テスト', array_map(function ($index) {
                return "$index.png";
            }, range(1, 10000))],
        ];
    }
}
