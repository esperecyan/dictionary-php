<?php
namespace esperecyan\dictionary_api\htmlpurifier;

class VideoFilenameTest extends \PHPUnit_Framework_TestCase
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
        // src属性のバリデーションを追加
        $def = $config->getHTMLDefinition(true);
        $def->addAttribute('video', 'src*', new VideoFilename());
        
        $this->assertSame($prified, (new \HTMLPurifier($config))->purify($html));
    }
    
    public function htmlProvider(): array
    {
        return [
            [
                '<video src="valid-extension/mpeg4-part14.mp4"></video>',
                '<video src="valid-extension/mpeg4-part14.mp4"></video>',
            ],
            [
                '<video src="invalid-extension/flash-video.flv"></video>',
                '',
            ],
        ];
    }
}
