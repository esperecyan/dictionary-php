<?php
namespace esperecyan\dictionary_php\serializer;

use Psr\Log\LogLevel;

class CatchmSerializerTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
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
        
        $serializer = new CatchmSerializer();
        $serializer->setLogger($this);
        $file = $serializer->serialize($this->generateDictionary($fieldsAsMultiDimensionalArrays, $metadata, $files));
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
                [
                    'bytes' => '[恒星/惑星/衛星]
                    // 次の天体のリスト。
                    // 
                    // - 恒星
                    // - 惑星
                    // - 衛星

                    たいよう,おひさま// 【太陽】恒星。
                    ちきゅう// 【地球】惑星。
                    かろん// 冥王星の衛星。  > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。 > その後、冥王星が冥府の王プルートーの名に因むことから、 > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。 > なおクリスティーは当初から一貫してCharonの「char」を > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、 > これが英語圏で定着して「シャーロン」と呼ばれるようになった。 引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => '恒星／惑星／衛星.dat',
                ],
                [],
            ],
            [
                [
                    [
                        'text' => ['太陽'],
                        'image' => ['local/sun.png'],
                        'answer' => ['太陽', 'サン', 'たいよう', 'sun'],
                    ],
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
                [
                    'bytes' => '[選択・並べ替え問題]
                    
                    さん,たいよう,sun,太陽// 【太陽】
                    ',
                    'type' => 'text/plain; charset=Shift_JIS',
                    'name' => '選択・並べ替え問題.dat',
                ],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
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
                        'answer' => ['/\\TEST', 'test,/;[']
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
        ];
    }
    
    /**
     * @param string[][][] $fieldsAsMultiDimensionalArrays
     * @param string[] $metadata
     * @param string[] $files
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidDictionaryProvider
     */
    public function testSyntaxException(array $fieldsAsMultiDimensionalArrays, array $metadata, array $files)
    {
        (new CatchmSerializer())->serialize(
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
