<?php
namespace esperecyan\dictionary_php\serializer;

use Psr\Log\LogLevel;

class PictsenseSerializerTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @param string[] $expectedFile
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testSerialize(
        array $fieldsAsMultiDimensionalArrays,
        array $metadata,
        array $files,
        array $expectedFile,
        array $logLevels
    ) {
        $expectedFile['bytes'] = $this->stripIndentsAndToCRLF($expectedFile['bytes']);
        
        $serializer = new PictsenseSerializer();
        $serializer->setLogger($this);
        
        $this->assertEquals(
            $expectedFile,
            $serializer->serialize($this->generateDictionary($fieldsAsMultiDimensionalArrays, $metadata, $files))
        );
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['カロン']],
                    ['text' => ['ケレス']],
                    ['text' => ['ハレーすいせい']],
                ],
                [],
                [],
                [
                    'bytes' =>
                    'たいよう
                    ちきゅう
                    かろん
                    けれす
                    はれーすいせい
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [],
            ],
            [
                [
                    ['text' => [str_repeat('あ', 32)]],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                    ['text' => ['か']],
                ],
                [],
                [],
                [
                    'bytes' =>
                    str_repeat('あ', 32) . '
                    い
                    う
                    え
                    お
                    か
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [],
            ],
            [
                [
                    ['text' => [str_repeat('あ', 33)]],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                    ['text' => ['か']],
                ],
                [],
                [],
                [
                    'bytes' =>
                    'い
                    う
                    え
                    お
                    か
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    [
                        'text' => ['あ'],
                        'answer' => [str_repeat('あ', 33), str_repeat('あ', 32)],
                    ],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                    ['text' => ['か']],
                ],
                [],
                [],
                [
                    'bytes' =>
                    str_repeat('あ', 32) . '
                    い
                    う
                    え
                    お
                    か
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [],
            ],
            [
                array_map(function (string $word): array {
                    return ['text' => [$word]];
                }, array_slice($this->generateHiraganaWords(), 0, 500)),
                [],
                [],
                [
                    'bytes' => implode("\n", array_slice($this->generateHiraganaWords(), 0, 500)) . "\n",
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [],
            ],
            [
                array_map(function (string $word): array {
                    return ['text' => [$word]];
                }, array_slice($this->generateHiraganaWords(), 0, 501)),
                [],
                [],
                [
                    'bytes' => implode("\n", array_slice($this->generateHiraganaWords(), 0, 501)) . "\n",
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [LogLevel::CRITICAL],
            ],
            [
                array_map(function (string $word): array {
                    return ['text' => [$word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'))]];
                }, array_slice($this->generateHiraganaWords(), 0, 500)),
                [],
                [],
                [
                    'bytes' => implode("\n", array_map(function (string $word): string {
                        return $word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'));
                    }, array_slice($this->generateHiraganaWords(), 0, 500))) . "\n",
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [],
            ],
            [
                array_map(function (string $word, bool $first = null): array {
                    return ['text' => [$word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8')) . ($first ? 'あ' : '')]];
                }, array_slice($this->generateHiraganaWords(), 0, 500), [true]),
                [],
                [],
                [
                    'bytes' => implode("\n", array_map(function (string $word, bool $first = null): string {
                        return $word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8')) . ($first ? 'あ' : '');
                    }, array_slice($this->generateHiraganaWords(), 0, 500), [true])) . "\n",
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [LogLevel::CRITICAL],
            ],
            [
                array_map(function (string $word): array {
                    return ['text' => [$word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'))]];
                }, array_slice($this->generateHiraganaWords(), 0, 501)),
                [],
                [],
                [
                    'bytes' => implode("\n", array_map(function (string $word): string {
                        return $word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'));
                    }, array_slice($this->generateHiraganaWords(), 0, 501))) . "\n",
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [LogLevel::CRITICAL, LogLevel::CRITICAL],
            ],
            [
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['太陽', 'サン', 'たいよう', 'sun'],
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
                    'bytes' =>
                    'さん
                    ちきゅう
                    かろん
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => '恒星／惑星／衛星.csv',
                ],
                [LogLevel::CRITICAL],
            ],
            [
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
                    'bytes' =>
                    'ぴん
                    じぇいふぃふ
                    えすぶいじー
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [LogLevel::CRITICAL],
            ],
            [
                [
                    [
                        'text' => ['𩸽'],
                    ],
                    [
                        'text' => ['剝'],
                        'answer' => ['剝', '剥', '~'],
                    ],
                    [
                        'text' => ['ゔゕゖヵヶ〜'],
                    ],
                    [
                        'text' => ['ヷヸヹヴァヴィヴヴェヴォㇰㇱㇲㇳㇴㇵㇶㇷㇸㇹㇻㇼㇽㇾㇿㇺ'],
                    ],
                ],
                [],
                [],
                [
                    'bytes' =>
                    'ヴかけかけー
                    ヴぁヴぃヴぇヴぁヴぃヴヴぇヴぉくしすとぬはひふへほらりるれろむ
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => 'dictionary.csv',
                ],
                [LogLevel::ERROR, LogLevel::ERROR, LogLevel::CRITICAL],
            ],
            [
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['カロン']],
                    ['text' => ['ケレス']],
                    ['text' => ['ハレーすいせい']],
                ],
                ['@title' => str_repeat('𩸽', 15)],
                [],
                [
                    'bytes' =>
                    'たいよう
                    ちきゅう
                    かろん
                    けれす
                    はれーすいせい
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => str_repeat('𩸽', 15) . '.csv',
                ],
                [],
            ],
            [
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['カロン']],
                    ['text' => ['ケレス']],
                    ['text' => ['ハレーすいせい']],
                ],
                ['@title' => str_repeat('𩸽', 16)],
                [],
                [
                    'bytes' =>
                    'たいよう
                    ちきゅう
                    かろん
                    けれす
                    はれーすいせい
                    ',
                    'type' => 'text/csv; charset=UTF-8; header=absent',
                    'name' => str_repeat('𩸽', 16) . '.csv',
                ],
                [LogLevel::ERROR],
            ],
        ];
    }
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @expectedException \esperecyan\dictionary_php\exception\EmptyOutputException
     * @dataProvider invalidDictionaryProvider
     */
    public function testEmptyOutputException(array $fieldsAsMultiDimensionalArrays, array $metadata, array $files)
    {
        (new PictsenseSerializer())->serialize(
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
                        'question' => ['仲間外れはどれでしょう'],
                        'option' => ['リンゴ', 'ゴリラ', 'ラクダ', 'ダチョウ'],
                        'answer' => ['リンゴ'],
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
                [],
            ],
        ];
    }
}
