<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\dictionary_php\htmlpurifier;

/**
 * image-source、audio-source、video-source、description、@summary フィールドの矯正。
 */
class LightweightMarkupValidator extends AbstractFieldValidator
{
    /** @var int エラーメッセージ中に表示するフィールド値の最大文字数。 */
    const FIELD_ELLIPSIS_LENGTH = 20;
    
    /** @var string description、@summary フィールドで利用できる要素・属性。 */
    const DESCRIPTION_WHITELIST = '*[dir|lang|title|translate],a[href],abbr,audio[src],b,bdi,bdo,blockquote[cite],br,caption,cite,code,col[span],colgroup[span],dd,del[cite|datetime],dfn,div,dl,dt,em,figcaption,figure,h1,h2,h3,h4,h5,h6,hr,i,img[alt|height|src|width],ins[cite|datetime],kbd,li,ol[reversed|start|type],p,pre,q[cite],rp,rt,ruby,s,samp,small,span,strong,sub,sup,table,tbody,td[colspan|rowspan],tfoot,th[abbr|colspan|rowspan|scope],thead,time[datetime],tr,u,ul,var,video[height|src|width],wbr';
    
    /** @var string image-source、audio-source、video-source フィールドで利用できる要素・属性。 */
    const SOURCE_WHITELIST = '*[dir|lang|title|translate],a[href],b,bdi,bdo,br,cite,i,p,rp,rt,ruby,sub,sup,time[datetime],u,wbr';
    
    /** @var bool */
    protected $source;
    
    /** @var string[] */
    protected $filenames = [];
    
    /**
     * @param bool $source image-source、audio-source、video-sourceフィールドの矯正であれば真を指定。
     * @param string[] $filenames src属性の検証に使用するファイル名のリスト。
     */
    public function __construct(bool $source = false, $filenames = [])
    {
        $this->source = $source;
        $this->filenames = $filenames;
    }
    
    /** @var (int|string|array)[][] purifyHTML メソッド実行時のエラーを記録する。 */
    protected $errors = [];
    
    /**
     * ホワイトリストに含まれない要素、属性を削除します。
     * @param string $html
     * @param string $whitelist
     * @return string
     */
    protected function purifyHTML(string $html, string $whitelist): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        // キャッシュの保存先を vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer から /tmp に
        $config->set('Cache.SerializerPath', sys_get_temp_dir());
        // 改行などを削除しないように
        $config->set('Core.LexerImpl', 'DirectLex');
        // ホワイトリストの設定
        $config->set('HTML.Allowed', $whitelist);
        // http、httpsスキーマのみ許可
        $config->set('URI.AllowedSchemes', ['http', 'https']);
        // 相対URLの禁止
        $config->set('URI.Base', 'example://relative.invalid/');
        $config->set('URI.MakeAbsolute', true);
        // エラーの収集
        $this->errors = [];
        $config->set('Core.CollectErrors', true);
        // HTML Standard 対応
        $config = htmlpurifier\HTMLConfig::create($config);
        // src属性のバリデーションを追加
        $def = $config->getHTMLDefinition(true);
        $def->addAttribute('img', 'src*', new htmlpurifier\ImageFilename($this->filenames));
        $def->addAttribute('audio', 'src*', new htmlpurifier\AudioFilename($this->filenames));
        $def->addAttribute('video', 'src*', new htmlpurifier\VideoFilename($this->filenames));
        // 必須属性の設定
        $def->addAttribute('a', 'href*', 'URI');
        //$def->addAttribute('data', 'value*', 'Text'); // 値がTextである属性を必須とすると正常に動作しなくなる
        
        $purifier = new \HTMLPurifier($config);
        set_time_limit(ini_get('max_execution_time'));
        $prified = $purifier->purify($html);
        $this->errors = $purifier->context->get('ErrorCollector')->getRaw();
        return $prified;
    }
    
    /**
     * CommonMark を HTML に変換します。
     * @param string $lightweightMarkup
     * @return string
     */
    protected function convertToHTML(string $lightweightMarkup): string
    {
        return (new \League\CommonMark\CommonMarkConverter())->convertToHtml($lightweightMarkup);
    }
    
    /**
     * HTML を CommonMark に変換します。
     * @param string $html
     * @return string
     */
    protected function convertFromHTML(string $html): string
    {
        return (new \League\HTMLToMarkdown\HtmlConverter())->convert($html);
    }
    
    public function correct(string $input): string
    {
        $purified = $this->purifyHTML(
            $this->convertToHTML($input),
            $this->source ? self::SOURCE_WHITELIST : self::DESCRIPTION_WHITELIST
        );
        if ($this->errors) {
            if ($this->logger) {
                $this->logger->error(sprintf(
                    _('「%s」に次のエラーが出ています:'),
                    mb_strlen($input) > self::FIELD_ELLIPSIS_LENGTH
                        ? mb_substr($input, 0, self::FIELD_ELLIPSIS_LENGTH) . '…'
                        : $input
                ) . array_reduce($this->errors, function ($errors, $error) {
                    return $errors .= "\n• $error[2]";
                }, ''));
            }
            
            // CommonMarkに変換
            $output = $this->convertFromHTML($purified);
            
            // 再チェック
            $purified = $this->purifyHTML(
                $this->convertToHTML($output),
                $this->source ? self::SOURCE_WHITELIST : self::DESCRIPTION_WHITELIST
            );
            if ($this->errors) {
                $output = $this->convertFromHTML($purified);
            }
            
            if (trim($output) === '') {
                $output = '';
            }
        } else {
            $output = $input;
        }
        return $output;
    }
}
