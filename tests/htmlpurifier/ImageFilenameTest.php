<?php
namespace esperecyan\dictionary_php\htmlpurifier;

class ImageFilenameTest extends \PHPUnit_Framework_TestCase
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
        // src属性のバリデーションを追加
        $def = $config->getHTMLDefinition(true);
        $def->addAttribute('img', 'src*', new ImageFilename());
        
        $this->assertSame($prified, (new \HTMLPurifier($config))->purify($html));
    }
    
    public function htmlProvider(): array
    {
        return [
            [
                '<img src="valid-extension/portable-network-graphics.png" alt="" />',
                '<img src="valid-extension/portable-network-graphics.png" alt="" />',
            ],
            [
                '<img src="valid-extension/jpeg-file-interchange-format.jpg" alt="" />',
                '<img src="valid-extension/jpeg-file-interchange-format.jpg" alt="" />',
            ],
            [
                '<img src="valid-extension/jpeg_file_interchange_format.jpeg" alt="" />',
                '<img src="valid-extension/jpeg_file_interchange_format.jpeg" alt="" />',
            ],
            [
                '<img src="valid-extension/scalable-vector-graphics.svg" alt="" />',
                '<img src="valid-extension/scalable-vector-graphics.svg" alt="" />',
            ],
            [
                '<img src="invalid-extension/graphics-interchange-format.gif" alt="" />',
                '',
            ],
            [
                '<img src="local/マルチバイト文字.png" alt="" />',
                '<img src="local/マルチバイト文字.png" alt="" />',
            ],
            [
                '<img src="local/フルストップを.含む.png" alt="" />',
                '',
            ],
            [
                '<img src="local/con.png" alt="" />',
                '',
            ],
            [
                '<img src="local/疑問符を含む?.png" alt="" />',
                '',
            ],
            [
                '<img src="local/NFC適用済み' . \Normalizer::normalize('で', \Normalizer::FORM_D) . 'ない" alt="" />',
                '',
            ],
        ];
    }
}
