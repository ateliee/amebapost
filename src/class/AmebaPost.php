<?php
namespace Ameba;

/**
 * Class AmebaException
 * @package Ameba
 */
class AmebaException extends \Exception
{
}

/**
 * Class AmebaCurlData
 * @package Ameba
 */
class AmebaCurlData
{
    var $url;
    var $http_code;
    var $header;
    var $body;

    /**
     * AmebaCurlData constructor.
     * @param $url
     * @param $header
     * @param $body
     * @param $http_code
     */
    function __construct($url,$header,$body,$http_code)
    {
        $this->url = $url;
        $this->http_code = $http_code;
        $this->header = $header;
        $this->body = $body;
    }
}

/**
 * Class AmebaCurl
 * @package Ameba
 */
class AmebaCurl
{
    private $userAgent;
    private $curl;
    private $cookie;
    private $follow_location;
    private $curl_loops;
    private $curl_max_loops;
    /**
     * @var array[AmebaCurlData]
     */
    protected $curl_datas;

    function __construct($useragent)
    {
        $this->curl = null;
        $this->userAgent = $useragent;
        $this->cookie = null;
        $this->curl_max_loops = 20;
        $this->curl_datas = array();
    }

    function __destruct()
    {
        if($this->cookie){
            fclose($this->cookie);
        }
    }

    /**
     * @return mixed|null
     */
    public function getCurl(){
        return $this->curl;
    }

    /**
     * @return resource
     * @throws AmebaException
     */
    protected function openCookieFile()
    {
        if($this->cookie){
            return $this->cookie;
        }
//        $file = tempnam(sys_get_temp_dir(),'AMEBA');
//        if($this->cookie = fopen($file,'w+')){
        if($this->cookie = tmpfile()){
            //$d = stream_get_meta_data($fp);
            //$this->cookie_filename = $d['uri'];
            return $this->cookie;
        }
        throw new AmebaException('can not open tmp file.');
    }

    /**
     * @param $url
     * @param array $posts
     * @return mixed
     */
    protected function post($url,array $posts=array(),array $options=array())
    {
        $this->close();

        if($this->init($url)){
            $this->setOption(CURLOPT_POST,true);
            $this->setOption(CURLOPT_POSTFIELDS, http_build_query($posts));

            foreach($options as $k => $v){
                $this->setOption($k,$v);
            }
            $res = $this->exec();
            return $res;
        }
        return false;
    }

    /**
     * @param $url
     * @param array $posts
     * @param bool $cookie
     * @return mixed
     */
    protected function get($url,$posts=array(),$cookie=true)
    {
        $this->close();

        if(count($posts)){
            $url = implode('?',array($url,(count($posts) ? http_build_query($posts,'','&') : '')));
        }
        if($this->init($url,$cookie)){
            return $this->exec();
        }
    }

    /**
     * @param $url
     * @param bool $cookie
     * @return bool
     * @throws \Exception
     */
    protected function init($url,$cookie=true)
    {
        try{
            $this->follow_location = false;
            $this->openCookieFile();
            if($this->curl = curl_init($url)){
                $this->setOption(CURLOPT_SSL_VERIFYPEER, false);
                $this->setOption(CURLOPT_SSL_VERIFYHOST, false);
                $this->setOption(CURLOPT_USERAGENT, $this->userAgent);
                $this->setOption(CURLOPT_HEADER, true);
                $this->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->setOption(CURLOPT_COOKIEJAR, $this->cookie);
                if($cookie){
                    $this->setOption(CURLOPT_COOKIEFILE, $this->cookie);
                }
                $this->setOption(CURLOPT_CONNECTTIMEOUT,10);
                $this->setOption(CURLOPT_TIMEOUT,15);
                $this->setOption(CURLOPT_MAXREDIRS,30);
                $this->setOption(CURLOPT_AUTOREFERER,true);
                $this->setOption(CURLOPT_ENCODING, 'gzip, deflate');
                $this->setOption(CURLOPT_HTTPHEADER,array(
                    'Accept: ' . 'text/html,' . 'application/xhtml+xml,' . 'application/xml' . ';q=0.9,*/*;q=0.8',
                    'Accept-Language: ' . 'ja,en-us;q=0.7,en;q=0.3'
                ));
                //if(!$this->cookie){
                //$this->setOption(CURLOPT_WRITEHEADER, $fp);
                //}
                $this->setOption(CURLOPT_FOLLOWLOCATION, true);
            }else{
                throw new AmebaException('error curl_init with '.$url);
            }
        }catch (\Exception $e){
            throw $e;
        }
        return true;
    }

    /**
     *
     */
    protected function close()
    {
        if($this->curl){
            curl_close($this->curl);
            $this->curl = null;
        }
    }

    /**
     * @return mixed
     */
    protected function exec()
    {
        if($this->follow_location){
            // for safe mode CURLOPT_FOLLOWLOCATION
            $this->curl_loops = 0;
            return $this->curl_redir_exec();
        }
        return $this->_exec()->body;
    }

    /**
     * @return AmebaCurlData
     */
    protected function _exec(){
        $data = curl_exec($this->curl);
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        $d = new AmebaCurlData(
            curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL),
            $header,
            $body,
            $http_code
        );
        $this->curl_datas[] = $d;
        return $d;
    }

    /**
     * @return bool|mixed
     */
    protected function curl_redir_exec()
    {
        if ($this->curl_loops++ >= $this->curl_max_loops){
            return false;
        }
        $data = $this->_exec();

        if ($data->http_code == 301 || $data->http_code == 302) {
            $matches = array();

            if(!preg_match('/Location:(.*?)\n/', $data->header, $matches)){
                return $data->body;
            }
            $url = @parse_url(trim(array_pop($matches)));
            if (!$url) {
                return $data->body;
            }
            $last_url = parse_url(curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL));
            if (!isset($url['scheme']) || empty($url['scheme'])) {
                $url['scheme'] = $last_url['scheme'];
            }
            if (!isset($url['host']) || empty($url['host'])) {
                $url['host'] = $last_url['host'];
            }
            if (!isset($url['path'])) {
                $url['path'] = '';
            }
            $new_url = $url['scheme'].'://'.$url['host'].$url['path'].(array_key_exists('query', $url) && $url['query'] ? '?'.$url['query'] : '');

            curl_setopt($this->curl, CURLOPT_URL, $new_url);
            curl_setopt($this->curl, CURLOPT_POST, false);
            if(curl_getinfo($this->curl,CURLOPT_COOKIEJAR)){
                curl_setopt($this->curl, CURLOPT_COOKIEJAR, false);
            }
            return $this->curl_redir_exec();
        }
        return $data->body;
    }

    /**
     * @param $id
     * @param $value
     */
    protected function setOption($id,$value)
    {
        if($id == CURLOPT_FOLLOWLOCATION && !$this->followLocationEnable()){
            $this->follow_location = true;
        }else{
            curl_setopt($this->curl,$id,$value);
        }
    }

    /**
     * @return bool
     */
    private function followLocationEnable(){
        return (ini_get('open_basedir') == '' && in_array(ini_get('safe_mode'), array('Off','')));
    }

    /**
     * @return mixed
     */
    protected function getInfo($opt=null)
    {
        if($opt){
            return curl_getinfo($this->curl,$opt);
        }
        return curl_getinfo($this->curl);
    }

    /**
     * @return bool
     */
    protected function isSuccess()
    {
        if($result = $this->getInfo()){
            if(isset($result['http_code']) && ($result['http_code'] == 200)){
                return $result['url'];
            }
        }
        return false;
    }
}

/**
 * Class AmebaPost
 * @package Ameba
 */
class AmebaPost extends AmebaCurl
{
    static $URL_MYPAGE = 'http://mypage.ameba.jp';
    static $URL_LIST = 'https://blog.ameba.jp/ucs/entry/srventrylist.do';
    //static $URL_LOGIN = 'https://www.ameba.jp/loginForm.do';
    static $URL_LOGIN = 'https://dauth.user.ameba.jp/login/ameba';
    //static $DO_LOGIN = 'https://www.ameba.jp/login.do';
    static $DO_LOGIN = 'https://dauth.user.ameba.jp/accounts/login';
    static $URL_INSERT = 'https://blog.ameba.jp/ucs/entry/srventryinsertinput.do';
    static $DO_INSERT_PUBLISH = 'https://blog.ameba.jp/ucs/entry/srventryinsertend.do';
    static $DO_INSERT_DRAFT = 'https://blog.ameba.jp/ucs/entry/srventryinsdraft.do';
    static $DO_INSERT_PROTECTED = 'https://blog.ameba.jp/ucs/entry/srventryinsertend.do';
    static $URL_UPDATE = 'https://blog.ameba.jp/ucs/entry/srventryupdateinput.do';
    static $DO_UPDATE_PUBLISH = 'http://blog.ameba.jp/ucs/entry/srventryupdateend.do';
    static $DO_UPDATE_DRAFT = 'https://blog.ameba.jp/ucs/entry/srventryupddraft.do';
    static $DO_UPDATE_PROTECTED = 'https://blog.ameba.jp/ucs/entry/srventryupdateend.do';
    static $DO_DELETE = 'https://blog.ameba.jp/ucs/entry/srventrydeleteend.do';

    static $PUBLISH = 0;
    static $PUBLISH_DRAFT = 1;
    static $PUBLISH_PROTECTED = 2;
    static $PUBLISH_DENY_COMMENT = 4;

    static $OPT_DENY_COMMENT = 1;
    static $OPT_FACEBOOK = 2;
    static $OPT_TWITTER = 4;

    static $LOG_SUCCESS = 0;
    static $LOG_ERROR = 1;
    static $LOG_WARNING = 10;

    private $id;
    private $password;
    private $errors;
    private $log;

    function __construct($id,$password,$log='')
    {
        parent::__construct(implode(' ',array(
            'Mozilla/5.0 (Windows NT 6.1) ',
            'AppleWebKit/537.36 (KHTML, like Gecko) ',
            'Chrome/28.0.1500.63 Safari/537.36'
        )));
        $this->log = $log;
        $this->errors = null;
        $this->login($id,$password);
    }

    /**
     * @param $type
     * @param $message
     */
    protected function outputLog($type,$message){
        if(!$this->log){
            return;
        }
        if($fp = fopen($this->log,'a')){
            $type_name = 'None';
            if($type == self::$LOG_SUCCESS) {
                $type_name = 'Success';
            }else if($type == self::$LOG_ERROR){
                $type_name = 'Error';
            }else if($type == self::$LOG_WARNING){
                $type_name = 'Warning';
            }
            $message = str_replace(PHP_EOL, '', $message);
            $message = sprintf('%s(%s):%s'.PHP_EOL,date('Y/m/d H:i:s'),$type_name,$message);
            fwrite($fp,$message);
            fclose($fp);
        }
    }

    /**
     * @return string
     */
    protected function getCsrfToken(){
        $res = $this->get(self::$URL_LOGIN,array(),false);
        if(preg_match('/name="csrf_token" +value="(.+)"/',$res,$matchs)){
            return $matchs[1];
        }
        return '';
    }

    /**
     * @return string
     */
    protected function getIntertCsrfToken(){
        $res = $this->get(self::$URL_INSERT,array());
        if(preg_match('/name="_csrf" +value="(.+)"/',$res,$matchs)){
            return $matchs[1];
        }
        return '';
    }

    /**
     * @param $id
     * @param $password
     * @return bool
     * @throws AmebaException
     */
    public function login($id,$password)
    {
        $posts = array(
//            'serviceId' => '0',
//            'amebaId' => $id,
//            'password' => md5($password),
//            'Submit.x' => '0',
//            'Submit.y' => '0',
//            'saveFlg' => '1'
            'csrf_token' => $this->getCsrfToken(),
            'accountId' => $id,
            'password' => ($password),
        );
        $this->post(self::$DO_LOGIN,$posts);
        // login success
        if(preg_match('/^'.preg_quote(self::$URL_MYPAGE,'/').'/',$this->isSuccess())){
            $this->id = $id;
            $this->password = $password;
            return true;
        }else{
            $this->outputLog(self::$LOG_ERROR,sprintf('login failed "%s".',$id));
            throw new AmebaException('failure login Ameba');
        }
    }

    /**
     * @param $title
     * @param $text
     * @param $theme_id
     * @param $publish
     * @param $datetime
     * @param $option
     * @throws AmebaException
     */
    public function insertEntry($title,$text,$theme_id,$publish,$datetime,$option)
    {
        if($publish == self::$PUBLISH){
            $url = self::$DO_INSERT_PUBLISH;
        }else if($publish == self::$PUBLISH_DRAFT){
            $url = self::$DO_INSERT_DRAFT;
        }else if($publish == self::$PUBLISH_PROTECTED){
            $url = self::$DO_INSERT_PROTECTED;
        }else{
            throw new AmebaException('publish paramater injustice');
        }
        return $this->postEntry($url,$this->getInsertParams(),$title,$text,$theme_id,$publish,$datetime,$option);
    }

    /**
     * @return array
     */
    protected function getInsertParams()
    {
        if (preg_match_all ('/<input +type *= *"hidden" +name *= *"([^"]+)" +value *= *"([^"]*)"/',$this->get(self::$URL_INSERT),$matchs)){
            return array_combine($matchs[1],$matchs[2]);
        }
        return array();
    }

    /**
     * @param $id
     * @param $title
     * @param $text
     * @param $theme_id
     * @param $publish
     * @param $datetime
     * @param $option
     * @throws AmebaException
     */
    public function updateEntry($id,$title,$text,$theme_id,$publish,$datetime,$option)
    {
        if($publish == self::$PUBLISH){
            $url = self::$DO_UPDATE_PUBLISH;
        }else if($publish == self::$PUBLISH_DRAFT){
            $url = self::$DO_UPDATE_DRAFT;
        }else if($publish == self::$PUBLISH_PROTECTED){
            $url = self::$DO_UPDATE_PROTECTED;
        }else{
            throw new AmebaException('publish paramater injustice');
        }
        return $this->postEntry($url,array_merge($this->getUpdateParams($id),array('id' => $id)),$title,$text,$theme_id,$publish,$datetime,$option);
    }

    /**
     * @return array
     */
    protected function getUpdateParams($id)
    {
        return $this->getFormParams($this->get(self::$URL_UPDATE,array('id' => $id)));
    }

    /**
     * @param $html
     * @return array
     */
    protected function getFormParams($html)
    {
        $list = array();
        if(preg_match_all('/<input ([^>]*)>/',$html,$matchs)){
            foreach($matchs[1] as $str){
                $params = array();
                if(preg_match_all('/(\S+?)="([^"]*)"/',$str,$mt)){
                    for($i=0;$i<count($mt[1]);$i++){
                        $params[$mt[1][$i]] = $mt[2][$i];
                    }
                }
                if(isset($params['name']) && ($params['name'] != '') && isset($params['value'])){
                    $list[$params['name']] = $params['value'];
                }
            }
        }
        if(preg_match_all('/<textarea ([^>]*)>([\s\S]*)<\/textarea>/',$html,$matchs)){
            $params = array();
            if(preg_match_all('/(\S+?)="([^"]*)"/',$matchs[1][0],$mt)){
                for($i=0;$i<count($mt[1]);$i++){
                    $params[$mt[1][$i]] = $mt[2][$i];
                }
            }
            if(isset($params['name']) && ($params['name'] != '')){
                $list[$params['name']] = $matchs[2][0];
            }
        }
        return $list;
    }

    /**
     * @param $url
     * @param array $params
     * @param $title
     * @param $text
     * @param $theme_id
     * @param $publish
     * @param $datetime
     * @param $option
     * @return bool
     */
    private function postEntry($url,array $params,$title,$text,$theme_id,$publish,$datetime,$option)
    {
        $posts = array_merge($params,array(
            '_csrf' => $this->getIntertCsrfToken(),
            'entry_title' => $title,
            'entry_text' => $text,
            'theme_id' => $theme_id,
            'publish_flg' => $publish,
            //'entryFontSize' => '',
            //'hrefToEntryTextArea' => 'http://',
            //'linkTargetToEntryTextArea' => '_blank',
            'deny_comment' => ($option & self::$OPT_DENY_COMMENT) ? 1 : null,
            'facebook_feed_flg' => ($option & self::$OPT_FACEBOOK) ? 1 : null,
            'twitter_feed_flg' => ($option & self::$OPT_TWITTER) ? 1 : null,
            'entry_created_datetime' => date('Y-m-d H:i:s',$datetime)
        ));
        $result = $this->post($url,$posts);
        if($this->isSuccessHtml($result)){
            $this->outputLog(self::$LOG_SUCCESS,sprintf('post entry.'));
            return true;
        }
        $this->outputLog(self::$LOG_ERROR,sprintf('post entry failed.'));
        return false;
    }

    /**
     * @return array|null
     */
    public function getThemeIds()
    {
        if(preg_match_all('/<option *value="(\d+)" *>([^<]+)/', $this->get(self::$URL_LIST),$matchs)){
            $themes = array_combine($matchs[1],$matchs[2]);
            if(!$themes || count($themes) <= 0){
                $this->outputLog(self::$LOG_WARNING,sprintf('not found themes.'));
            }
            return $themes;
        }
        $this->outputLog(self::$LOG_ERROR,sprintf('failed get themes.'));
        return null;
    }

    /**
     * @param null $month
     * @return array
     */
    public function getEntry($month=null)
    {
        $result = $this->get(self::$URL_LIST,($month ? array('entry_ym' => $month) : array()));
        $list = array();
        if(preg_match_all('/<a href="http:\/\/ameblo\.jp\/(.+)\/entry\-(\d+)\.html">(.+)<\/a>/',$result,$matchs)){
            $list = array_combine($matchs[2],$matchs[3]);
        }else{
            $this->outputLog(self::$LOG_WARNING,sprintf('failed get entrys.'));
        }
        return $list;
    }

    /**
     * @param $html
     * @return bool
     */
    private function isSuccessHtml($html)
    {
        if(!$html || ($html == "")){
            return false;
        }
        if (preg_match ('@<span class="error">(.+?)</span>@s', $html, $matchs)) {
            $errors = preg_split ('@\s*+<br />@', $matchs[1], - 1, PREG_SPLIT_NO_EMPTY);
            $this->errors = array();
            foreach ($errors as $error) {
                $this->errors[] = trim ($error);
            }
            return false;
        }
        return true;
    }
}
