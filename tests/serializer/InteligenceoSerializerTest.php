<?php
namespace esperecyan\dictionary_php\serializer;

use Psr\Log\LogLevel;

class InteligenceoSerializerTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @param string $type
     * @param string[] $expectedFile
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testSerialize(
        array $fieldsAsMultiDimensionalArrays,
        array $metadata,
        array $files,
        string $type,
        array $expectedFile,
        array $logLevels
    ) {
        $expectedFile['bytes'] = $this->stripIndentsAndToCRLF($expectedFile['bytes']);
        
        $serializer = new InteligenceoSerializer($type);
        $serializer->setLogger($this);
        $dictionary = $this->generateDictionary($fieldsAsMultiDimensionalArrays, $metadata, $files);
        $file = $serializer->serialize($dictionary);
        
        if ($files) {
            $finfo = new \esperecyan\dictionary_php\fileinfo\Finfo(FILEINFO_MIME_TYPE);
            
            $archive = $this->generateArchive($file['bytes']);
            for ($i = 0, $l = $archive->numFiles; $i < $l; $i++) {
                $actualTypes[$archive->getNameIndex($i)] = $finfo->buffer($archive->getFromIndex($i));
            }
            
            $asciiTitle = (new \esperecyan\dictionary_php\validator\FilenameValidator())
                ->convertToValidFilenameWithoutExtensionInArchives($dictionary->getTitle());
            foreach ($files as $filename => $content) {
                $expectedTypes["$asciiTitle/$filename"] = $finfo->buffer($content);
            }
            $expectedTypes["$asciiTitle.txt"] = 'text/plain';
            
            $this->assertEquals($expectedTypes, $actualTypes);
            
            $file['bytes'] = $archive->getFromName("$asciiTitle.txt");
        }
        $file['bytes'] = mb_convert_encoding($file['bytes'], 'UTF-8', 'Windows-31J');
        $this->assertEquals($expectedFile, $file);
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
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
                    '@summary' => "次の天体のリスト。\n\n- 恒星\n- 惑星\n- 衛星",
                ],
                [],
                'Inteligenceω クイズ',
                [
                    'bytes' => '% 【恒星/惑星/衛星】
                    % 次の天体のリスト。
                    % 
                    % - 恒星
                    % - 惑星
                    % - 衛星

                    Q,2,,local/sun.png
                    A,0,たいよう,おひさま,\\explain=太陽\\n\\n恒星。
                    Q,2,,local/earth.png
                    A,0,ちきゅう,\\explain=地球\\n\\n惑星。
                    Q,2,,local/charon.png
                    A,0,カロン,\\explain=カロン\\n\\n冥王星の衛星。\\n\\n> カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。\\n> その後、冥王星が冥府の王プルートーの名に因むことから、\\n> この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。\\n> なおクリスティーは当初から一貫してCharonの「char」を\\n> 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、\\n> これが英語圏で定着して「シャーロン」と呼ばれるようになった。\\n引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => '恒星／惑星／衛星.txt',
                ],
                [],
            ],
            [
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
                        'specifics' => ['start=60&repeat=3&length=0.5005&speed=0.105&valume=2'],
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
                'Inteligenceω クイズ',
                [
                    'bytes' =>
                    '% 【選択
                    %   並べ替え】

                    Q,2,,local/sun.png,zoom_start=10,zoom_end=1
                    A,0,太陽,サン,たいよう,\\bonus=10,sun,\\explain=太陽
                    Q,1,,local/four-seasons.mp4,start=60000,repeat=3,length=501,speed=11
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
                [
                    [
                        'text' => ['あ'],
                        'answer' => ['あ', '/あ.*/', '/.*お.*/', '/.*=/'],
                        'question' => ['部分一致'],
                    ],
                    [
                        'text' => ['ゔゕゖ〜=,TEST'],
                        'question' => ['ゔゕゖ〜=,'],
                        'description' => ['ゔゕゖ〜=,\\n'],
                    ],
                ],
                [],
                [],
                'Inteligenceω クイズ',
                [
                    'bytes' =>
                    'Q,0,部分一致
                    A,0,あ,\\explain=あ
                    Q,0,う゛ヵヶ～＝，
                    A,0,ヴヵヶ～＝，TEST,\\explain=う゛ヵヶ～＝，TEST\\n\\nう゛ヵヶ～＝，\\\\n
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => 'dictionary.txt',
                ],
                [],
            ],
            [
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['たいよう', 'おひさま'],
                        'description' => ['恒星。'],
                        'weight' => ['0.3'],
                    ],
                    [
                        'text' => ['地球'],
                        'image' => ['local/earth.png'],
                        'answer' => ['ちきゅう'],
                        'description' => ['惑星。'],
                        'weight' => ['0.5'],
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
                    '@summary' => "次の天体のリスト。\n\n- 恒星\n- 惑星\n- 衛星",
                ],
                [],
                'Inteligenceω しりとり',
                [
                    'bytes' => '% 【恒星/惑星/衛星】
                    % 次の天体のリスト。
                    % 
                    % - 恒星
                    % - 惑星
                    % - 衛星

                    太陽,たいよう,おひさま,3,@恒星。
                    地球,ちきゅう,2,@惑星。
                    カロン,かろん,@冥王星の衛星。  > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。 > その後、冥王星が冥府の王プルートーの名に因むことから、 > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。 > なおクリスティーは当初から一貫してCharonの「char」を > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、 > これが英語圏で定着して「シャーロン」と呼ばれるようになった。 引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => '恒星／惑星／衛星.txt',
                ],
                [],
            ],
            [
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
                ],
                ['@title' => "選択\n並べ替え"],
                [],
                'Inteligenceω しりとり',
                [
                    'bytes' =>
                    '% 【選択
                    %   並べ替え】

                    太陽,さん,たいよう
                    四季,しき,はる
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => '選択並べ替え.txt',
                ],
                [LogLevel::ERROR],
            ],
            [
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
                'Inteligenceω しりとり',
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
                [
                    [
                        'text' => ['ピン'],
                        'image' => ['png.png'],
                        'weight' => ['1.5'],
                    ],
                    [
                        'text' => ['ジェイフィフ'],
                        'image' => ['jfif.jpg'],
                        'weight' => ['0.000001'],
                    ],
                    [
                        'text' => ['エスブイジー'],
                        'image' => ['svg.svg'],
                        'weight' => ['1000000000000000'],
                    ],
                ],
                [
                    '@title' => '画像ファイル形式',
                ],
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
                'Inteligenceω クイズ',
                [
                    'bytes' => '% 【画像ファイル形式】
                    
                    Q,2,,./gazo-fairu-keishiki/png.png
                    A,0,ピン,\\explain=ピン
                    Q,2,,./gazo-fairu-keishiki/jfif.jpg
                    A,0,ジェイフィフ,\\explain=ジェイフィフ
                    Q,2,,./gazo-fairu-keishiki/svg.svg
                    A,0,エスブイジー,\\explain=エスブイジー
                    ',
                    'type' => 'application/zip',
                    'name' => '画像ファイル形式.zip',
                ],
                [],
            ],
        ];
    }
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @param string $type
     * @expectedException \esperecyan\dictionary_php\exception\EmptyOutputException
     * @dataProvider invalidDictionaryProvider
     */
    public function testEmptyOutputException(
        array $fieldsAsMultiDimensionalArrays,
        array $metadata,
        array $files,
        string $type
    ) {
        (new InteligenceoSerializer($type))->serialize(
            $this->generateDictionary($fieldsAsMultiDimensionalArrays, $metadata, $files)
        );
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            [
                [
                    [
                        'text' => ['リンゴ'],
                    ],
                    [
                        'text' => ['ゴリラ'],
                    ],
                    [
                        'text' => ['ラッパ'],
                    ],
                    [
                        'text' => ['パン'],
                    ],
                ],
                ['@title' => '問題文や画像のない辞書'],
                [],
                'Inteligenceω クイズ',
            ],
        ];
    }
}
