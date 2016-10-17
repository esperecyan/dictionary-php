<?php
namespace esperecyan\dictionary_php\validator;

class LightweightMarkupValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param bool $source
     * @param string $input
     * @param string $output
     * @dataProvider answerProvider
     */
    public function testCorrect(bool $source, string $input, string $output)
    {
        $validator = new LightweightMarkupValidator($source);
        $validator->setLogger($this);
        $this->assertSame(
            preg_replace('/^ {16}/um', '', $output),
            $validator->correct(preg_replace('/^ {16}/um', '', $input))
        );
        $this->assertEquals($input === $output ? [] : [\Psr\Log\LogLevel::ERROR], $this->logLevels);
    }
    
    public function answerProvider(): array
    {
        return [
            [
                false,
                '冥王星の衛星。

                > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                > その後、冥王星が冥府の王プルートーの名に因むことから、
                > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                > なおクリスティーは当初から一貫してCharonの「char」を
                > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))
                ',
                '冥王星の衛星。

                > カロンは1978年6月22日にアメリカの天文学者ジェームズ・クリスティーによって発見された。
                > その後、冥王星が冥府の王プルートーの名に因むことから、
                > この衛星はギリシア神話の冥府の川・アケローンの渡し守カローンにちなんで「カロン」と命名された。
                > なおクリスティーは当初から一貫してCharonの「char」を
                > 妻シャーリーン（Charlene） のニックネーム「シャー（Char）」と同じように発音していたため、
                > これが英語圏で定着して「シャーロン」と呼ばれるようになった。
                引用元: [カロン (衛星) - Wikipedia](https://ja.wikipedia.org/wiki/%E3%82%AB%E3%83%AD%E3%83%B3_(%E8%A1%9B%E6%98%9F))
                ',
            ],
            [
                true,
                '# 見出し1
                本文
                
                見出し2
                =======
                本文
                
                見出し3
                -------
                [リンク] **強調** <b>名前</b> _強勢_ <i style="font-weight: bold;">心の声</i> `コード`
                
                [リンク]: https://example.jp/
                ',
                '見出し1

                本文

                見出し2 本文

                見出し3 [リンク](https://example.jp/) 強調 名前 強勢 心の声 コード',
            ],
            [
                true,
                '[リンク] <b>名前</b> <i>心の声</i>

                [リンク]: https://example.jp/',
                '[リンク] <b>名前</b> <i>心の声</i>

                [リンク]: https://example.jp/',
            ],
            [
                false,
                '# 見出し1
                本文

                見出し2
                =======
                本文

                見出し3
                -------
                [リンク] **強調** <b>名前</b> _強勢_ <i>心の声</i> `コード`

                [リンク]: https://example.jp/',
                '# 見出し1
                本文

                見出し2
                =======
                本文

                見出し3
                -------
                [リンク] **強調** <b>名前</b> _強勢_ <i>心の声</i> `コード`

                [リンク]: https://example.jp/'
            ],
            [
                false,
                '- 主人公。
                - 年齢不詳。
                - <details>
                      <summary>主なセリフ</summary>
                      ○○○○○○○○○○○○○○○
                  </details>
                ',
                '- 主人公。
                - 年齢不詳。
                - <details>
                      <summary>主なセリフ</summary>
                      ○○○○○○○○○○○○○○○
                  </details>
                ',
            ],
        ];
    }
}
