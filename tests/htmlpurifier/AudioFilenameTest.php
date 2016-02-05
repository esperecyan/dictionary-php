<?php
namespace esperecyan\dictionary_api\htmlpurifier;

class AudioFilenameTest extends \PHPUnit_Framework_TestCase
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
        $def->addAttribute('audio', 'src*', new AudioFilename());
        
        $this->assertSame($prified, (new \HTMLPurifier($config))->purify($html));
    }
    
    public function htmlProvider(): array
    {
        return [
            [
                '<audio src="valid-extension/mpeg4-part14.mp4"></audio>',
                '<audio src="valid-extension/mpeg4-part14.mp4"></audio>',
            ],
            [
                '<audio src="valid-extension/mpeg4_part14.m4a"></audio>',
                '<audio src="valid-extension/mpeg4_part14.m4a"></audio>',
            ],
            [
                '<audio src="valid-extension/mpeg1-audio-layer3.mp3"></audio>',
                '<audio src="valid-extension/mpeg1-audio-layer3.mp3"></audio>',
            ],
            [
                '<audio src="invalid-extension/mpeg4-part14.m4v"></audio>',
                '',
            ],
        ];
    }
}
