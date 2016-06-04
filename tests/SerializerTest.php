<?php
namespace esperecyan\dictionary_php;

use Psr\Log\LogLevel;

class SerializerTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use LogLevelLoggerTrait;
    use PreprocessingTrait;
    
    /**
     * @param string $to
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @param string[] $expectedFile
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testSerialize(
        string $to,
        array $fieldsAsMultiDimensionalArrays,
        array $metadata,
        array $files,
        array $expectedFile,
        array $logLevels
    ) {
        $dictionary = $this->generateDictionary($fieldsAsMultiDimensionalArrays, $metadata, $files);
        
        $expectedFile['bytes'] = $this->stripIndentsAndToCRLF($expectedFile['bytes']);
        
        $serializer = new Serializer($to);
        $serializer->setLogger($this);
        $file = $serializer->serialize($dictionary);
        if ($to === '汎用辞書' && $files) {
            $archive = $this->generateArchive($file['bytes']);
            
            $finfo = new \esperecyan\dictionary_php\fileinfo\Finfo(FILEINFO_MIME_TYPE);
            for ($i = 0, $l = $archive->numFiles; $i < $l; $i++) {
                $actualTypes[$archive->getNameIndex($i)] = $finfo->buffer($archive->getFromIndex($i));
            }
            $this->assertEquals(array_map(function (string $file) use ($finfo): string {
                return $finfo->buffer($file);
            }, $files + ['dictionary.csv' => $expectedFile['bytes']]), $actualTypes);
            
            $file['bytes'] = $archive->getFromName('dictionary.csv');
            $this->assertEquals($expectedFile, $file);
        } else {
            if (strpos($expectedFile['type'], 'charset=Shift_JIS') !== false) {
                $file['bytes'] = mb_convert_encoding($file['bytes'], 'UTF-8', 'Windows-31J');
            }
            $this->assertEquals($expectedFile, $file);
        }
        
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                '汎用辞書',
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['たいよう', 'おひさま'],
                        'description' => ['恒星。'],
                    ],
                    [
                        'text' => ['地球'],
                        'image' => ['local/earth.png'],
                        'answer' => ['ちきゅう'],
                        'description' => ['惑星。'],
                    ],
                    [
                        'text' => ['カロン'],
                        'image' => ['local/charon.png'],
                        'description' => [$this->stripIndents(
                            '冥王星の衛星。

                            > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                            > その後、冥王星が冥府の王プルートーの名に因むことから、
                            > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                            > なおクリスティーは当初から一貫してCharonの「char」を
                            > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                            > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                            引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))'
                        )],
                    ],
                ],
                [
                    '@title' => '恒星/惑星/衛星',
                    '@summary' => '恒星、惑星、衛星などのリスト。',
                ],
                [],
                [
                    'bytes' => 'text,image,answer,answer,description,@title,@summary
                    太陽,local/sun.png,たいよう,おひさま,恒星。,恒星/惑星/衛星,恒星、惑星、衛星などのリスト。
                    地球,local/earth.png,ちきゅう,,惑星。,,
                    カロン,local/charon.png,,,"冥王星の衛星。

                    > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                    > その後、冥王星が冥府の王プルートーの名に因むことから、
                    > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                    > なおクリスティーは当初から一貫してCharonの「char」を
                    > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                    > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                    引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))",,
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=present',
                    'name' => '恒星／惑星／衛星.csv',
                ],
                [],
            ],
            [
                '汎用辞書',
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
                ],
                [],
                (function (): array {
                    $image = imagecreatetruecolor(1000, 1000);
                    ob_start();
                    imagepng($image);
                    $files['png.png'] = ob_get_clean();
                    
                    ob_start();
                    imagejpeg($image);
                    $files['jfif.jpg'] = ob_get_clean();
                    imagedestroy($image);
                    
                    $files['svg.svg'] = '<?xml version="1.0" ?>
                        <svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" /></svg>';
                    
                    return $files;
                })(),
                [
                    'bytes' => 'text,image
                    ピン,png.png
                    ジェイフィフ,jfif.jpg
                    エスブイジー,svg.svg
                    ',
                    'type' => 'application/zip',
                    'name' => 'dictionary.zip',
                ],
                [],
            ],
            [
                'キャッチフィーリング',
                [
                    [
                        'text' => ['𩸽'],
                    ],
                    [
                        'text' => ['剝'],
                        'answer' => ['剝', '剥'],
                    ],
                    [
                        'text' => ['塡'],
                        'answer' => ['塡', '填'],
                    ],
                    [
                        'text' => ['ゔゕゖ〜'],
                    ],
                    [
                        'text' => ['ヷヸヹヴァヴィヴヴェヴォㇰㇱㇲㇳㇴㇵㇶㇷㇸㇹㇻㇼㇽㇾㇿㇺ'],
                    ],
                    [
                        'text' => ['te//st'],
                    ],
                    [
                        'text' => ['/'],
                    ],
                    [
                        'text' => ['te//st/'],
                    ],
                    [
                        'text' => ['te//st/', 't/e/s/t'],
                    ],
                ],
                [],
                [],
                [
                    'bytes' =>
                    "剥
                    填
                    ヴヵヶ～
                    ヴぁヴぃヴぇヴぁヴぃヴヴぇヴぉくしすとぬはひふへほらりるれろむ
                    te／／st
                    /
                    te／／st／
                    t/e/s/t
                    ",
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => 'dictionary [語数 8].cfq',
                ],
                [LogLevel::ERROR],
            ],
            [
                'きゃっちま',
                [
                    [
                        'text' => ['𩸽'],
                    ],
                    [
                        'text' => ['剝'],
                        'answer' => ['剝', '剥'],
                    ],
                    [
                        'text' => ['塡'],
                        'answer' => ['塡', '填'],
                    ],
                    [
                        'text' => ['ゔゕゖ〜'],
                    ],
                    [
                        'text' => ['test'],
                    ],
                    [
                        'text' => ['test2'],
                        'answer' => ['/\\test', 'test,/;[']
                    ],
                ],
                [
                    '@title' => "テ\nス\nト",
                ],
                [],
                [
                    'bytes' =>
                    '[テ
                    // ス
                    // ト]
                    
                    剥
                    填
                    ヴヵヶ～
                    ｔｅｓｔ,test
                    ／＼ｔｅｓｔ,／\\test,test，／；［// 【test2】
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => 'テスト.dat',
                ],
                [LogLevel::ERROR],
            ],
            [
                'Inteligenceω しりとり',
                [
                    [
                        'text' => ['あ'],
                        'answer' => ['あ', '/あ.*/', '/.*お.*/', '/.*=/'],
                        'question' => ['部分一致'],
                    ],
                    [
                        'text' => ['ひらがな'],
                        'answer' => ['ゔゕゖ〜'],
                        'description' => ['ゔゕゖ〜%=,\\n'],
                    ],
                    [
                        'text' => ['ゐゑヰヱヷヸヹヴァヴィヴヴェヴォヵヶㇰㇱㇲㇳㇴㇵㇶㇷㇸㇹㇻㇼㇽㇾㇿㇺ'],
                    ],
                    [
                        'text' => ["\u{1B000}\u{1B001}"],
                    ],
                    [
                        'text' => ['末尾の長音'],
                        'answer' => ['あんー', 'あんーーー', 'あんーーーっ', 'っー', 'っーーー', 'ゎー', 'ゎーーー', 'かーー', 'かーーー'],
                    ],
                    [
                        'text' => ['𩸽 (ほっけ)'],
                        'answer' => ['ほっけ'],
                        'description' => ['𩸽 (ほっけ)'],
                    ],
                    [
                        'text' => ['んじゃめな'],
                        'description' => ['撥音が先頭にある。'],
                    ],
                    [
                        'text' => ['ーびる'],
                        'description' => ['長音が先頭にある。'],
                    ],
                ],
                [],
                [],
                [
                    'bytes' =>
                    'あ,あ
                    ひらがな,ぶかけー,@う゛ヵヶ～%=，\\n
                    ゐゑヰヱワ゛ヰ゛ヱ゛ヴァヴィヴヴェヴォヵヶクシストヌハヒフヘホラリルレロム,いえいえばびべばびぶべぼかけくしすとぬはひふへほらりるれろむ
                    エえ,ええ
                    末尾の長音,あんん,あんーーん,あんーーーっ,っっ,っーーっ,ゎあ,ゎーあー,かあー,かーあー
                    〓 (ほっけ),ほっけ,@〓 (ほっけ)
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => 'dictionary.txt',
                ],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                'Inteligenceω クイズ',
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['太陽', 'サン', 'たいよう', 'sun'],
                        'specifics' => ['magnification=10&last-magnification=1&bonus=0&bonus=0&bonus=10&bonus=0'],
                    ],
                    [
                        'text' => ['四季'],
                        'audio' => ['local/four-seasons.mp4'],
                        'answer' => ['しき', 'はる'],
                        'specifics' => ['start=60&repeat=3&length=0.5005&speed=0.1005&valume=2'],
                    ],
                    [
                        'text' => ['リンゴ'],
                        'question' => ['仲間外れはどれでしょう'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラクダ', 'ダチョウ'],
                        'answer' => ['リンゴ'],
                        'type' => ['selection'],
                        'description' => ['選択肢を表示しなければ問題が成立しない場合。'],
                    ],
                    [
                        'text' => ['「リンゴ」か「パン」'],
                        'question' => ["食べ物はどれでしょう\n(答えが複数ある場合はどれが1つだけ選択)"],
                        'option' => ['リンゴ', 'ゴリラ', 'ラッパ', 'パン'],
                        'answer' => ['リンゴ', 'パン'],
                        'specifics' => ['bonus=&bonus=100&score=100&last-score=200&no-random='],
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
                    [
                        'text' => ['2'],
                        'question' => ['1+1'],
                    ],
                    [
                        'text' => ['𩸽'],
                        'question' => ['ホッケはどれでしょう'],
                        'option' => ['𩸽', '鰆', '鰤'],
                    ],
                ],
                ['@title' => "選択\n並べ替え"],
                [],
                [
                    'bytes' =>
                    '% 【選択
                    %   並べ替え】

                    Q,2,,local/sun.png,zoom_start=10,zoom_end=1
                    A,0,太陽,サン,たいよう,\\bonus=10,sun,\\explain=太陽
                    Q,1,,local/four-seasons.mp4,start=60000,repeat=3,length=501,speed=10
                    A,0,しき,はる,\\explain=四季
                    Q,0,仲間外れはどれでしょう
                    A,1,リンゴ,\\seikai,ゴリラ,ラクダ,ダチョウ,\\explain=リンゴ\\n\\n選択肢を表示しなければ問題が成立しない場合。
                    Q,0,食べ物はどれでしょう\\n(答えが複数ある場合はどれが1つだけ選択),score=100,finalscore=200
                    A,1,リンゴ,\\seikai,ゴリラ,ラッパ,パン,\\seikai,\\norandom,\\explain=「リンゴ」か「パン」
                    Q,0,同じ種類のものを選びましょう
                    A,3,リンゴ,\\seikai,ゴリラ,ラッパ,パン,\\seikai,\\explain=「リンゴ」と「パン」
                    Q,0,しりとりが成立するように並べ替えてください
                    A,2,リンゴ,1,ゴリラ,2,ラッパ,3,パン,4,\\explain=リンゴ → ゴリラ → ラッパ → パン
                    Q,0,1+1
                    A,0,2,\\explain=2
                    Q,0,ホッケはどれでしょう
                    A,1,〓,\\seikai,鰆,鰤,\\explain=〓
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => '選択並べ替え.txt',
                ],
                [],
            ],
            [
                'ピクトセンス',
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['太陽', 'サン', 'たいよう', 'sun'],
                        'specifics' => ['magnification=10&last-magnification=1&bonus=0&bonus=0&bonus=10&bonus=0'],
                    ],
                    [
                        'text' => ['四季'],
                        'audio' => ['local/four-seasons.mp4'],
                        'answer' => ['しき', 'はる'],
                        'specifics' => ['start=60&repeat=3&length=0.5005&speed=0.1005&valume=2'],
                    ],
                    [
                        'text' => ['リンゴ'],
                        'question' => ['仲間外れはどれでしょう'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラクダ', 'ダチョウ'],
                        'answer' => ['リンゴ'],
                        'type' => ['selection'],
                        'description' => ['選択肢を表示しなければ問題が成立しない場合。'],
                    ],
                    [
                        'text' => ['「リンゴ」か「パン」'],
                        'question' => ["食べ物はどれでしょう\n(答えが複数ある場合はどれが1つだけ選択)"],
                        'option' => ['リンゴ', 'ゴリラ', 'ラッパ', 'パン'],
                        'answer' => ['リンゴ', 'パン'],
                        'specifics' => ['bonus=&bonus=100&score=100&last-score=200&no-random='],
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
                ['@title' => "選択・並べ替え"],
                [],
                [
                    'bytes' =>
                    'さん
                    しき
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => '選択・並べ替え.csv',
                ],
                [LogLevel::ERROR, LogLevel::ERROR, LogLevel::ERROR, LogLevel::ERROR, LogLevel::CRITICAL],
            ],
        ];
    }
}
