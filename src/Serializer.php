<?php
namespace esperecyan\dictionary_php;

class Serializer implements \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    
    /** @var string */
    protected $to;
    
    /**
     * @param string $to
     */
    public function __construct(string $to = '汎用辞書')
    {
        $this->to = in_array($to, []) ? $to : '汎用辞書';
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
            case '汎用辞書':
                $serializer = new serializer\GenericDictionarySerializer();
                break;
        }
        
        if ($this->logger) {
            $serializer->setLogger($this->logger);
        }
        
        return $serializer->serialize($dictionary);
    }
}
