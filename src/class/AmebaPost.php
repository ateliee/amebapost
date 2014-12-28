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
 * Class AmebaCurl
 * @package Ameba
 */
class AmebaCurl
{
    private $userAgent;
    private $curl;
    private $cookie_filename;
    private $cookie;

    function __construct($useragent)
    {
        $this->curl = null;
        $this->userAgent = $useragent;
        $this->cookie_filename = dirname(__FILE__).'/tmp';
        $this->cookie = false;
    }

    /**
     * @param $filename
     */
    public function setCookieFilename($filename)
    {
        if($this->cookie_filename != $filename){
            $this->cookie_filename = $filename;
            $this->cookie = false;
        }
    }

    /**
     * @param $url
     * @param array $posts
     * @return mixed
     */
    protected function post($url,array $posts=array())
    {
        $this->close();

        if($this->init($url)){
            $this->setOption(CURLOPT_POST,true);
            $this->setOption(CURLOPT_POSTFIELDS, http_build_query($posts));

            return $this->exec();
        }
    }

    /**
     * @param $url
     * @param array $posts
     * @return mixed
     */
    protected function get($url,$posts=array())
    {
        $this->close();

        if(count($posts)){
            $url = implode('?',array($url,(count($posts) ? http_build_query($posts,'','&') : '')));
        }
        if($this->init($url)){
            return $this->exec();
        }
    }

    /**
     * @param $url
     * @return bool
     */
    protected function init($url)
    {
        try{
            if(!$this->cookie){
                if(!($fp = @fopen($this->cookie_filename, "w"))){
                    throw new AmebaException('can not create cookie file '.$this->cookie_filename);
                }
            }
            if($this->curl = curl_init($url)){
                $this->setOption(CURLOPT_SSL_VERIFYPEER, false);
                $this->setOption(CURLOPT_SSL_VERIFYHOST, false);
                $this->setOption(CURLOPT_USERAGENT, $this->userAgent);
                $this->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->setOption(CURLOPT_COOKIEJAR,'cookie');
                $this->setOption(CURLOPT_COOKIEFILE, $this->cookie_filename);
                if(!$this->cookie){
                    $this->setOption(CURLOPT_WRITEHEADER, $fp);
                }
                $this->setOption(CURLOPT_FOLLOWLOCATION, true);
            }else{
                throw new AmebaException('error curl_init with '.$url);
            }
        }catch (\Exception $e){
            if($fp){
                fclose($fp);
            }
            throw $e;
            return false;
        }
        $this->cookie = true;
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
        return curl_exec($this->curl);
    }

    /**
     * @param $id
     * @param $value
     */
    protected function setOption($id,$value)
    {
        curl_setopt($this->curl,$id,$value);
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
    static $URL_LIST = 'http://blog.ameba.jp/ucs/entry/srventrylist.do';
    static $URL_LOGIN = 'https://www.ameba.jp/loginForm.do';
    static $DO_LOGIN = 'https://www.ameba.jp/login.do';
    static $URL_INSERT = 'http://blog.ameba.jp/ucs/entry/srventryinsertinput.do';
    static $DO_INSERT_PUBLISH = 'http://blog.ameba.jp/ucs/entry/srventryinsertend1.do';
    static $DO_INSERT_DRAFT = 'http://blog.ameba.jp/ucs/entry/srventryinsertdraft.do';
    static $DO_INSERT_PROTECTED = 'http://blog.ameba.jp/ucs/entry/srventryinsertend1.do';
    static $URL_UPDATE = 'http://blog.ameba.jp/ucs/entry/srventryupdateinput.do';
    static $DO_UPDATE_PUBLISH = 'http://blog.ameba.jp/ucs/entry/srventryupdateend1.do';
    static $DO_UPDATE_DRAFT = 'http://blog.ameba.jp/ucs/entry/srventryupdatedraft.do';
    static $DO_UPDATE_PROTECTED = 'http://blog.ameba.jp/ucs/entry/srventryupdateend1.do';
    static $DO_DELETE = 'http://blog.ameba.jp/ucs/entry/srventrydeleteend.do';

    static $PUBLISH = 0;
    static $PUBLISH_DRAFT = 1;
    static $PUBLISH_PROTECTED = 2;
    static $PUBLISH_DENY_COMMENT = 4;

    static $OPT_DENY_COMMENT = 1;
    static $OPT_FACEBOOK = 2;
    static $OPT_TWITTER = 4;

    private $id;
    private $password;
    private $errors;

    function __construct($id,$password)
    {
        parent::__construct(implode(' ',array(
            'Mozilla/5.0 (Windows NT 6.1)',
            'AppleWebKit/537.36 (KHTML, like Gecko)',
            'Chrome/28.0.1500.63 Safari/537.36'
        )));
        $this->login($id,$password);
        $this->errors = null;
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
            'serviceId' => '0',
            'amebaId' => $id,
            'password' => md5($password),
            'Submit.x' => '0',
            'Submit.y' => '0',
            'saveFlg' => '1'
        );
        $this->post(self::$DO_LOGIN,$posts);
        // login success
        if($this->isSuccess() == self::$URL_MYPAGE){
            $this->id = $id;
            $this->password = $password;
            return true;
        }else{
            throw new AmebaException('failure login Ameba');
        }
        return false;
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
        if (preg_match_all ('/<input type="hidden" name="([^"]*+)" value="([^"]*+)/',$this->get(self::$URL_INSERT),$matchs)){
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
            'entry_title' => $title,
            'entry_text' => $text,
            'theme_id' => $theme_id,
            'publish_flg' => $publish,
            'deny_comment' => ($option & self::$OPT_DENY_COMMENT) ? 1 : null,
            'facebook_feed_flg' => ($option & self::$OPT_FACEBOOK) ? 1 : null,
            'twitter_feed_flg' => ($option & self::$OPT_TWITTER) ? 1 : null,
            'entry_created_datetime' => date('Y-m-d H:i:s',$datetime)
        ));
        $result = $this->post($url,$posts);
        if($this->isSuccessHtml($result)){
            return true;
        }
        return false;
    }

    /**
     * @return array|null
     */
    public function getThemeIds()
    {
        if(preg_match_all('/<option value="(\d+)">([^<]+)/', $this->get(self::$URL_LIST),$matchs)){
            return array_combine($matchs[1],$matchs[2]);
        }
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
        }
        return $list;
    }

    /**
     * @param $html
     * @return bool
     */
    private function isSuccessHtml($html)
    {
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