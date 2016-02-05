<?php
namespace esperecyan\dictionary_api\htmlpurifier;

class HTMLConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $html
     * @param string $prified
     * @dataProvider htmlProvider
     */
    public function testCreate(string $html, string $prified)
    {
        $config = \HTMLPurifier_Config::createDefault();
        // キャッシュの保存先を vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer から /tmp に
        $config->set('Cache.SerializerPath', sys_get_temp_dir());
        // HTML Standard 対応
        $config = HTMLConfig::create($config);
        // 必須属性の設定
        $def = $config->getHTMLDefinition(true);
        $def->addAttribute('a', 'href*', 'URI');
        
        $purifier = new \HTMLPurifier($config);
        $this->assertSame($prified, $purifier->purify($html));
    }
    
    public function htmlProvider(): array
    {
        return [
            [
                '<a data-invalid="">テスト</a>',
                'テスト',
            ],
            [
                '<a href="ftp://test.test/" data-invalid="">テスト</a>',
                '<a href="ftp://test.test/">テスト</a>',
            ],
            [
                '<audio src="test.mp4" preload="auto" controls=""></audio>',
                '<audio src="test.mp4" preload="auto" controls=""></audio>',
            ],
            [
                '<audio src="test.mp4" preload="metadata"></audio>',
                '<audio src="test.mp4" preload="metadata"></audio>',
            ],
            [
                '<audio src="test.mp4" preload="none"></audio>',
                '<audio src="test.mp4" preload="none"></audio>',
            ],
            [
                '<audio src="test.mp4" preload="invalid"></audio>',
                '<audio src="test.mp4"></audio>',
            ],
            [
                '<bdi>テスト</bdi>',
                '<bdi>テスト</bdi>',
            ],
            [
                '<ruby><span>辞</span>書<rp>(</rp><rt>じしょ</rt><rp>)</rp></ruby>',
                '<ruby><span>辞</span>書<rp>(</rp><rt>じしょ</rt><rp>)</rp></ruby>',
            ],
            [
                '<span translate="">テスト</span>',
                '<span translate="">テスト</span>',
            ],
            [
                '<span translate="yes">テスト</span>',
                '<span translate="yes">テスト</span>',
            ],
            [
                '<span translate="no">if</span>',
                '<span translate="no">if</span>',
            ],
            [
                '<span dir="ltr">テスト</span>',
                '<span dir="ltr">テスト</span>',
            ],
            [
                '<span dir="rtl">テスト</span>',
                '<span dir="rtl">テスト</span>',
            ],
            [
                '<span dir="auto">テスト</span>',
                '<span dir="auto">テスト</span>',
            ],
            [
                '<bdo>テスト</bdo>',
                '<bdo dir="ltr">テスト</bdo>',
            ],
            [
                '<bdo dir="auto">テスト</bdo>',
                '<bdo dir="ltr">テスト</bdo>',
            ],
            [
                '<bdo dir="rtl">テスト</bdo>',
                '<bdo dir="rtl">テスト</bdo>',
            ],
            [
                '<ol reversed=""><li>2016-01-01</li><li>2015-12-31</li><li>1970-01-01</li></ol>',
                '<ol reversed=""><li>2016-01-01</li><li>2015-12-31</li><li>1970-01-01</li></ol>',
            ],
        ];
    }
}
