<?php
namespace esperecyan\dictionary_php;

class Serializer extends log\AbstractLoggerAware
{
    /** @var string */
    protected $to;
    
    /**
     * @param string $to
     */
    public function __construct(string $to = '汎用辞書')
    {
        parent::__construct();
        
        $this->to = in_array($to, ['キャッチフィーリング', 'きゃっちま', 'Inteligenceω クイズ',  'Inteligenceω しりとり', 'ピクトセンス'])
            ? $to
            : '汎用辞書';
    }
    
    /**
     * 直列化したデータを返します。
     * @see https://github.com/esperecyan/dictionary-php#void-esperecyandictionary_phpserializerserializedictionary-dictionary
     * @param Dictionary $dictionary
     * @param bool|string $csvOnly `汎用辞書` `Inteligenceω クイズ` の場合、ZIPファイルの代わりにCSVファイル、txtファイルのみを返すときに真に設定します。
            真の代わりに `https://example.ne.jp/dictionaries/1/files/%s` のような文字列を設定することで、`%s` をファイル名に置き換えて辞書ファイル中に記述します。
     * @return string[]
     */
    public function serialize(Dictionary $dictionary, $csvOnly = false): array
    {
        switch ($this->to) {
            case 'キャッチフィーリング':
                $serializer = new serializer\CatchfeelingSerializer();
                break;
            case 'きゃっちま':
                $serializer = new serializer\CatchmSerializer();
                break;
            case 'Inteligenceω クイズ':
            case 'Inteligenceω しりとり':
                $serializer = new serializer\InteligenceoSerializer($this->to, $csvOnly);
                break;
            case 'ピクトセンス':
                $serializer = new serializer\PictsenseSerializer();
                break;
            case '汎用辞書':
                $serializer = new serializer\GenericDictionarySerializer($csvOnly);
                break;
        }
        
        $serializer->setLogger($this->logger);
        
        return $serializer->serialize($dictionary);
    }
}
