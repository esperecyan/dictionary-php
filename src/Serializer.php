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
        
        $this->to = in_array($to, ['キャッチフィーリング', 'きゃっちま', 'Inteligenceω クイズ',  'Inteligenceω しりとり']) ? $to : '汎用辞書';
    }
    
    /**
     * 直列化したデータを返します。
     * @see https://github.com/esperecyan/dictionary-php#void-esperecyandictionary_phpserializerserializedictionary-dictionary
     * @param Dictionary $dictionary
     * @return string[]
     */
    public function serialize(Dictionary $dictionary): array
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
                $serializer = new serializer\InteligenceoSerializer($this->to);
                break;
            case '汎用辞書':
                $serializer = new serializer\GenericDictionarySerializer();
                break;
        }
        
        $serializer->setLogger($this->logger);
        
        return $serializer->serialize($dictionary);
    }
}
